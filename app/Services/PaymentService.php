<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly ServiceOrderService $serviceOrderService,
    ) {}

    /**
     * Register a payment for an appointment and mark it as completed.
     *
     * @param  array{
     *   gross_amount:numeric-string|float|int,
     *   payment_method:string,
     *   notes?:string|null,
     *   items?:array<int, array{product_id:int, quantity:int}>
     * }  $data
     */
    public function register(Appointment $appointment, User $actor, array $data): Payment
    {
        return DB::transaction(function () use ($appointment, $actor, $data): Payment {
            $order = $this->serviceOrderService->ensureForAppointment($appointment);

            if (isset($data['gross_amount'])) {
                $this->serviceOrderService->syncSingleServiceAmount($order, round((float) $data['gross_amount'], 2));
            }

            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $product = Product::query()
                        ->where('company_id', $appointment->company_id)
                        ->findOrFail($item['product_id']);

                    $this->serviceOrderService->addProduct($order, $product, (int) $item['quantity']);
                }
            }

            return $this->serviceOrderService->close($order, $actor, $data['payment_method'], $data['notes'] ?? null);
        });
    }

    /**
     * Calculate commission amount for the professional.
     */
    public function calculateCommissionAmount(User $professional, float $grossAmount): float
    {
        return match ($professional->commission_type) {
            'percent' => round($grossAmount * ((float) ($professional->commission_value ?? 0) / 100), 2),
            'fixed' => round((float) ($professional->commission_value ?? 0), 2),
            default => 0.0,
        };
    }
}
