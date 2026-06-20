<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CustomerMembership;
use App\Models\Payment;
use App\Models\ProductSale;
use App\Models\ProductSaleItem;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PdvSaleCancellationService
{
    public function __construct(
        private readonly CashRegisterService $cashRegisterService,
        private readonly StockService $stockService,
        private readonly ClientCommercialHistoryService $commercialHistoryService,
    ) {}

    public function cancel(ServiceOrder $order, User $actor, string $reason): ServiceOrder
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancel_reason' => 'Informe o motivo do cancelamento.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $reason): ServiceOrder {
            /** @var ServiceOrder $locked */
            $locked = ServiceOrder::query()
                ->with([
                    'appointment',
                    'client',
                    'payment',
                    'productSale.items.product',
                    'items',
                ])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === ServiceOrder::STATUS_CANCELLED) {
                return $locked;
            }

            if ($locked->status !== ServiceOrder::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'sale' => 'Somente vendas finalizadas podem ser canceladas.',
                ]);
            }

            $memberships = CustomerMembership::query()
                ->where('company_id', $locked->company_id)
                ->where('service_order_id', $locked->id)
                ->lockForUpdate()
                ->get();

            foreach ($memberships as $membership) {
                if ($membership->usages()->exists()) {
                    throw ValidationException::withMessages([
                        'cancel_reason' => 'Plano já possui utilizações vinculadas',
                    ]);
                }
            }

            $cancelledAt = now();

            if ($locked->payment) {
                $this->cancelPayment($locked->payment, $actor, $reason, $cancelledAt);
            }

            if ($locked->productSale) {
                $this->cancelProductSale($locked->productSale, $actor, $reason, $cancelledAt);
            }

            $membershipRefundAmount = $memberships->isNotEmpty()
                ? round((float) $locked->items->where('type', 'membership')->sum('total_price') / max(1, $memberships->count()), 2)
                : 0.0;

            foreach ($memberships as $membership) {
                $this->cancelMembership($membership, $locked, $actor, $cancelledAt, $membershipRefundAmount);
            }

            $locked->update([
                'status' => ServiceOrder::STATUS_CANCELLED,
                'cancelled_at' => $cancelledAt,
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);

            if ($locked->productSale) {
                $this->commercialHistoryService->markSaleCanceled((int) $locked->productSale->id);
            }

            if ($locked->appointment) {
                $this->commercialHistoryService->markAppointmentCanceled($locked->appointment);
            }

            return $locked->fresh([
                'client',
                'professional',
                'appointment',
                'items.service',
                'items.product',
                'payment',
                'productSale',
                'cancelledBy',
            ]);
        });
    }

    public function forceDelete(ServiceOrder $order, User $actor, string $confirmation): void
    {
        if (trim($confirmation) !== 'EXCLUIR') {
            throw ValidationException::withMessages([
                'confirmation' => 'Digite EXCLUIR para confirmar a exclusao definitiva.',
            ]);
        }

        if ($order->status === ServiceOrder::STATUS_PAID) {
            $this->cancel($order, $actor, 'Exclusao definitiva por Super Admin.');
        }

        DB::transaction(function () use ($order, $actor): void {
            /** @var ServiceOrder $locked */
            $locked = ServiceOrder::query()
                ->with(['payment', 'productSale', 'items'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            Log::warning('PDV sale force delete requested.', [
                'service_order_id' => $locked->id,
                'company_id' => $locked->company_id,
                'status' => $locked->status,
                'total' => (string) $locked->total,
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
            ]);

            $locked->delete();
        });
    }

    private function cancelPayment(Payment $payment, User $actor, string $reason, mixed $cancelledAt): void
    {
        if ($payment->status !== Payment::STATUS_CANCELLED && (float) $payment->gross_amount > 0) {
            $this->cashRegisterService->recordMovement(
                (int) $payment->company_id,
                $cancelledAt,
                CashMovement::TYPE_OUTFLOW,
                (float) $payment->gross_amount,
                'Estorno venda PDV - servico',
                (string) $payment->payment_method,
                Payment::class,
                (int) $payment->id,
                $actor->id,
            );
        }

        $payment->update([
            'status' => Payment::STATUS_CANCELLED,
            'cancelled_at' => $cancelledAt,
            'cancelled_by' => $actor->id,
            'cancel_reason' => $reason,
        ]);
    }

    private function cancelProductSale(ProductSale $sale, User $actor, string $reason, mixed $cancelledAt): void
    {
        if ($sale->status !== ProductSale::STATUS_CANCELLED && (float) $sale->gross_amount > 0) {
            $this->cashRegisterService->recordMovement(
                (int) $sale->company_id,
                $cancelledAt,
                CashMovement::TYPE_OUTFLOW,
                (float) $sale->gross_amount,
                'Estorno venda PDV - produto',
                (string) $sale->payment_method,
                ProductSale::class,
                (int) $sale->id,
                $actor->id,
            );
        }

        foreach ($sale->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->tracksStock()) {
                continue;
            }

            $alreadyReversed = StockMovement::query()
                ->where('company_id', $sale->company_id)
                ->where('type', StockMovement::TYPE_SALE_REVERSAL)
                ->where('source_type', ProductSaleItem::class)
                ->where('source_id', $item->id)
                ->exists();

            if ($alreadyReversed) {
                continue;
            }

            $this->stockService->increase(
                $product,
                (float) $item->quantity,
                'Estorno de venda PDV',
                ProductSaleItem::class,
                (int) $item->id,
                $actor,
                $cancelledAt,
                StockMovement::TYPE_SALE_REVERSAL,
            );
        }

        $sale->update([
            'status' => ProductSale::STATUS_CANCELLED,
            'cancelled_at' => $cancelledAt,
            'cancelled_by' => $actor->id,
            'cancel_reason' => $reason,
        ]);
    }

    private function cancelMembership(CustomerMembership $membership, ServiceOrder $order, User $actor, mixed $cancelledAt, float $amount): void
    {
        $membership->update([
            'status' => CustomerMembership::STATUS_CANCELED,
            'auto_renew' => false,
            'canceled_at' => $cancelledAt,
        ]);

        if ($amount <= 0) {
            return;
        }

        $this->cashRegisterService->recordMovement(
            (int) $order->company_id,
            $cancelledAt,
            CashMovement::TYPE_OUTFLOW,
            $amount,
            'Estorno venda PDV - assinatura',
            (string) $order->payment_method,
            CustomerMembership::class,
            (int) $membership->id,
            $actor->id,
        );
    }
}
