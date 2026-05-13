<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceOrderService
{
    public function __construct(
        private readonly CashRegisterService $cashRegisterService,
        private readonly ProductCommissionCalculator $productCommissionCalculator,
        private readonly ClientCommercialHistoryService $commercialHistoryService,
    ) {}

    public function ensureForAppointment(Appointment $appointment): ServiceOrder
    {
        return DB::transaction(function () use ($appointment): ServiceOrder {
            $order = ServiceOrder::query()
                ->where('appointment_id', $appointment->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                $order = ServiceOrder::create([
                    'company_id' => $appointment->company_id,
                    'appointment_id' => $appointment->id,
                    'client_id' => $appointment->client_id,
                    'professional_id' => $appointment->user_id,
                    'status' => ServiceOrder::STATUS_OPEN,
                    'opened_at' => now(),
                ]);
            }

            if ($order->items()->where('type', ServiceOrderItem::TYPE_SERVICE)->doesntExist()) {
                foreach ($appointment->loadMissing(['service', 'services'])->bookedServices() as $service) {
                    $this->createServiceItem(
                        $order,
                        $service,
                        $appointment->user_id,
                        (float) ($service->pivot->price_snapshot ?? $service->price)
                    );
                }
            }

            return $this->recalculate($order)->load(['items.service', 'items.product', 'items.professional', 'client', 'professional', 'appointment']);
        });
    }

    public function addService(ServiceOrder $order, Service $service, ?User $professional = null): ServiceOrder
    {
        $this->ensureOpen($order);
        abort_unless($service->company_id === $order->company_id, 404);

        if ($professional) {
            abort_unless($professional->company_id === $order->company_id, 404);
        }

        $this->createServiceItem($order, $service, $professional?->id ?? $order->professional_id, (float) $service->price);

        return $this->recalculate($order);
    }

    public function addProduct(ServiceOrder $order, Product $product, int $quantity, ?User $seller = null): ServiceOrder
    {
        $this->ensureOpen($order);
        abort_unless($product->company_id === $order->company_id, 404);

        if ($seller) {
            abort_unless($seller->company_id === $order->company_id, 404);
        }

        if (! $seller && $product->hasCommission()) {
            throw ValidationException::withMessages([
                'seller_id' => 'Informe o vendedor responsavel para produtos com comissao.',
            ]);
        }

        $quantity = max(1, $quantity);
        $alreadyInOrder = (int) $order->items()
            ->where('type', ServiceOrderItem::TYPE_PRODUCT)
            ->where('product_id', $product->id)
            ->sum('quantity');

        if ($product->stock_quantity < ($alreadyInOrder + $quantity)) {
            throw ValidationException::withMessages([
                'product_id' => 'Estoque insuficiente para adicionar este produto.',
            ]);
        }

        $order->items()->create([
            'type' => ServiceOrderItem::TYPE_PRODUCT,
            'product_id' => $product->id,
            'seller_id' => $seller?->id,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'total_price' => round((float) $product->price * $quantity, 2),
        ]);

        return $this->recalculate($order);
    }

    public function removeItem(ServiceOrderItem $item): ServiceOrder
    {
        $order = $item->order()->firstOrFail();
        $this->ensureOpen($order);

        $item->delete();

        return $this->recalculate($order);
    }

    /**
     * Remove all line items from an open order (used when rebuilding cart from PDV).
     */
    public function clearAllItems(ServiceOrder $order): ServiceOrder
    {
        $this->ensureOpen($order);
        $order->items()->delete();

        return $this->recalculate($order);
    }

    public function syncSingleServiceAmount(ServiceOrder $order, float $amount): ServiceOrder
    {
        $serviceItems = $order->items()->where('type', ServiceOrderItem::TYPE_SERVICE)->get();

        if ($order->isOpen() && $serviceItems->count() === 1) {
            $serviceItems->first()->update([
                'unit_price' => round($amount, 2),
                'total_price' => round($amount, 2),
            ]);
        }

        return $this->recalculate($order);
    }

    public function close(ServiceOrder $order, User $actor, string $paymentMethod, ?string $notes = null, ?CarbonInterface $closedAt = null): ?Payment
    {
        $closedAt ??= now();

        return DB::transaction(function () use ($order, $paymentMethod, $notes, $closedAt): ?Payment {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['appointment.user', 'appointment.client', 'items.product', 'items.service', 'professional', 'client'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureOpen($lockedOrder);
            $this->recalculate($lockedOrder);

            if ($lockedOrder->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Nao e possivel fechar uma comanda vazia.',
                ]);
            }

            $productItems = $lockedOrder->items->where('type', ServiceOrderItem::TYPE_PRODUCT);

            if ($productItems->isNotEmpty()) {
                $products = Product::query()
                    ->where('company_id', $lockedOrder->company_id)
                    ->whereIn('id', $productItems->pluck('product_id')->filter()->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($productItems->groupBy('product_id') as $productId => $items) {
                    $product = $products->get((int) $productId);
                    $quantity = (int) $items->sum('quantity');

                    if (! $product || $product->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'payment_method' => 'Estoque insuficiente para fechar a comanda.',
                        ]);
                    }
                }
            }

            $appointment = $lockedOrder->appointment;
            $professional = $appointment?->user ?? $lockedOrder->professional;
            $discountTotal = round((float) $lockedOrder->discount, 2);
            $serviceSubtotal = round((float) $lockedOrder->subtotal_services, 2);
            $productSubtotal = round((float) $lockedOrder->subtotal_products, 2);
            [$serviceChargeAmount, $productChargeAmount] = $this->applyDiscountAcrossSubtotals(
                $discountTotal,
                $serviceSubtotal,
                $productSubtotal
            );
            $payment = null;

            if ($serviceChargeAmount > 0) {
                if (! $professional) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Informe um profissional para fechar servicos na comanda.',
                    ]);
                }

                /** @var ServiceOrderItem|null $firstServiceItem */
                $firstServiceItem = $lockedOrder->items->firstWhere('type', ServiceOrderItem::TYPE_SERVICE);
                $commissionAmount = $this->calculateCommissionAmount($professional, $serviceChargeAmount);
                $commissionRate = $professional->commission_type === 'percent'
                    ? round((float) ($professional->commission_value ?? 0), 2)
                    : null;

                $payment = Payment::create([
                    'company_id' => $lockedOrder->company_id,
                    'appointment_id' => $appointment?->id,
                    'service_order_id' => $lockedOrder->id,
                    'user_id' => $professional->id,
                    'client_id' => $lockedOrder->client_id,
                    'service_id' => $appointment?->service_id ?? $firstServiceItem?->service_id,
                    'gross_amount' => $serviceChargeAmount,
                    'payment_method' => $paymentMethod,
                    'commission_type' => $professional->commission_type,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    'net_amount' => round($serviceChargeAmount - $commissionAmount, 2),
                    'paid_at' => $closedAt,
                    'notes' => $notes,
                ]);

                $this->cashRegisterService->recordPayment($payment->load('client'));
            }

            $appointment?->update(['status' => 'completed']);

            if ($productItems->isNotEmpty()) {
                foreach ($productItems as $item) {
                    if ($item->product && $item->product->hasCommission() && empty($item->seller_id)) {
                        throw ValidationException::withMessages([
                            'seller_id' => 'Informe o vendedor responsavel pelos produtos com comissao antes de fechar a venda.',
                        ]);
                    }
                }

                $sale = ProductSale::create([
                    'company_id' => $lockedOrder->company_id,
                    'client_id' => $lockedOrder->client_id,
                    'appointment_id' => $appointment?->id,
                    'service_order_id' => $lockedOrder->id,
                    'user_id' => $lockedOrder->professional_id,
                    'gross_amount' => $productChargeAmount,
                    'payment_method' => $paymentMethod,
                    'sold_at' => $closedAt,
                    'notes' => $notes,
                ]);

                $productEffectiveRatio = $productSubtotal > 0
                    ? ($productChargeAmount / $productSubtotal)
                    : 0.0;

                foreach ($productItems as $item) {
                    $effectiveSubtotal = round((float) $item->total_price * $productEffectiveRatio, 2);

                    $commission = $item->product
                        ? $this->productCommissionCalculator->calculate(
                            $item->product,
                            (int) $item->quantity,
                            $effectiveSubtotal,
                        )
                        : ['type' => null, 'value' => null, 'amount' => 0.0];

                    if ($commission['amount'] > 0 && empty($item->seller_id)) {
                        throw ValidationException::withMessages([
                            'seller_id' => 'Informe o vendedor responsavel pelos produtos com comissao antes de fechar a venda.',
                        ]);
                    }

                    $sale->items()->create([
                        'product_id' => $item->product_id,
                        'seller_id' => $item->seller_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                        'commission_type_snapshot' => $commission['type'],
                        'commission_value_snapshot' => $commission['value'],
                        'commission_amount' => $commission['amount'],
                    ]);

                    $item->product->decrement('stock_quantity', $item->quantity);
                }

                if ($productChargeAmount > 0) {
                    $this->cashRegisterService->recordProductSale($sale->load('client'));
                }

                $this->commercialHistoryService->recordProductSale($sale, $lockedOrder);
            }

            $lockedOrder->update([
                'status' => ServiceOrder::STATUS_PAID,
                'closed_at' => $closedAt,
            ]);

            $lockedOrder->client?->update([
                'last_visit_at' => $closedAt,
            ]);

            $this->commercialHistoryService->recordServiceOrderServices(
                $lockedOrder->loadMissing(['items.service', 'appointment']),
                $closedAt,
            );

            return $payment;
        });
    }

    private function createServiceItem(ServiceOrder $order, Service $service, ?int $professionalId, float $unitPrice): void
    {
        $order->items()->create([
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $professionalId,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => round($unitPrice, 2),
            'total_price' => round($unitPrice, 2),
        ]);
    }

    public function recalculate(ServiceOrder $order): ServiceOrder
    {
        $items = $order->items()->get();
        $subtotalServices = round((float) $items->where('type', ServiceOrderItem::TYPE_SERVICE)->sum('total_price'), 2);
        $subtotalProducts = round((float) $items->where('type', ServiceOrderItem::TYPE_PRODUCT)->sum('total_price'), 2);
        $discount = round((float) $order->discount, 2);

        $order->update([
            'subtotal_services' => $subtotalServices,
            'subtotal_products' => $subtotalProducts,
            'total' => max(0, round($subtotalServices + $subtotalProducts - $discount, 2)),
        ]);

        return $order->fresh(['items.service', 'items.product', 'items.professional', 'client', 'professional', 'appointment']);
    }

    private function ensureOpen(ServiceOrder $order): void
    {
        if (! $order->isOpen()) {
            throw ValidationException::withMessages([
                'order' => 'Comanda fechada nao permite alteracao.',
            ]);
        }
    }

    private function calculateCommissionAmount(User $professional, float $grossAmount): float
    {
        return match ($professional->commission_type) {
            'percent' => round($grossAmount * ((float) ($professional->commission_value ?? 0) / 100), 2),
            'fixed' => round((float) ($professional->commission_value ?? 0), 2),
            default => 0.0,
        };
    }

    /**
     * Apply a fixed discount to service subtotal first; remainder applies to products.
     *
     * @return array{0: float, 1: float} [amount charged for services, amount charged for products]
     */
    private function applyDiscountAcrossSubtotals(float $discount, float $services, float $products): array
    {
        $towardServices = round(min($services, max(0, $discount)), 2);
        $remainder = round(max(0, $discount - $towardServices), 2);
        $towardProducts = round(min($products, $remainder), 2);

        return [
            round(max(0, $services - $towardServices), 2),
            round(max(0, $products - $towardProducts), 2),
        ];
    }
}
