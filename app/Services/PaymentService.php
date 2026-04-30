<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly CashRegisterService $cashRegisterService,
        private readonly ProductSaleService $productSaleService,
    ) {
    }

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
        /** @var User $professional */
        $professional = $appointment->user;

        $grossAmount = round((float) $data['gross_amount'], 2);
        $commissionAmount = $this->calculateCommissionAmount($professional, $grossAmount);
        $commissionRate = $professional->commission_type === 'percent'
            ? round((float) ($professional->commission_value ?? 0), 2)
            : null;

        return DB::transaction(function () use (
            $appointment,
            $professional,
            $actor,
            $data,
            $grossAmount,
            $commissionAmount,
            $commissionRate,
        ): Payment {
            $payment = Payment::create([
                'company_id' => $appointment->company_id,
                'appointment_id' => $appointment->id,
                'user_id' => $professional->id,
                'client_id' => $appointment->client_id,
                'service_id' => $appointment->service_id,
                'gross_amount' => $grossAmount,
                'payment_method' => $data['payment_method'],
                'commission_type' => $professional->commission_type,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'net_amount' => round($grossAmount - $commissionAmount, 2),
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $appointment->update([
                'status' => 'completed',
            ]);

            $this->cashRegisterService->recordPayment($payment->load('client'));

            if (! empty($data['items'])) {
                $this->productSaleService->registerForAppointment(
                    $actor,
                    $appointment->loadMissing('client'),
                    $data['items'],
                    $data['payment_method'],
                    $data['notes'] ?? null,
                );
            }

            return $payment;
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
