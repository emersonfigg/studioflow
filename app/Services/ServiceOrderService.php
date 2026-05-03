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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceOrderService
{
    public function __construct(
        private readonly CashRegisterService $cashRegisterService,
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

    public function addProduct(ServiceOrder $order, Product $product, int $quantity): ServiceOrder
    {
        $this->ensureOpen($order);
        abort_unless($product->company_id === $order->company_id, 404);

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

    public function close(ServiceOrder $order, User $actor, string $paymentMethod, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($order, $paymentMethod, $notes): Payment {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['appointment.user', 'appointment.client', 'items.product'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureOpen($lockedOrder);
            $this->recalculate($lockedOrder);

            if ((float) $lockedOrder->total <= 0 || $lockedOrder->items()->count() === 0) {
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
            $professional = $appointment->user;
            $serviceSubtotal = (float) $lockedOrder->subtotal_services;
            $commissionAmount = $this->calculateCommissionAmount($professional, $serviceSubtotal);
            $commissionRate = $professional->commission_type === 'percent'
                ? round((float) ($professional->commission_value ?? 0), 2)
                : null;

            $payment = Payment::create([
                'company_id' => $lockedOrder->company_id,
                'appointment_id' => $appointment->id,
                'service_order_id' => $lockedOrder->id,
                'user_id' => $professional->id,
                'client_id' => $lockedOrder->client_id,
                'service_id' => $appointment->service_id,
                'gross_amount' => $serviceSubtotal,
                'payment_method' => $paymentMethod,
                'commission_type' => $professional->commission_type,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'net_amount' => round($serviceSubtotal - $commissionAmount, 2),
                'paid_at' => now(),
                'notes' => $notes,
            ]);

            $appointment->update(['status' => 'completed']);
            $this->cashRegisterService->recordPayment($payment->load('client'));

            if ($productItems->isNotEmpty()) {
                $sale = ProductSale::create([
                    'company_id' => $lockedOrder->company_id,
                    'client_id' => $lockedOrder->client_id,
                    'appointment_id' => $appointment->id,
                    'service_order_id' => $lockedOrder->id,
                    'user_id' => $lockedOrder->professional_id,
                    'gross_amount' => $lockedOrder->subtotal_products,
                    'payment_method' => $paymentMethod,
                    'sold_at' => now(),
                    'notes' => $notes,
                ]);

                foreach ($productItems as $item) {
                    $sale->items()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                    ]);

                    $item->product->decrement('stock_quantity', $item->quantity);
                }

                $this->cashRegisterService->recordProductSale($sale->load('client'));
            }

            $lockedOrder->update([
                'status' => ServiceOrder::STATUS_PAID,
                'closed_at' => now(),
            ]);

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
}
