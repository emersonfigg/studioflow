<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Register a payment for an appointment and mark it as completed.
     *
     * @param  array{gross_amount:numeric-string|float|int,payment_method:string,notes?:string|null}  $data
     */
    public function register(Appointment $appointment, array $data): Payment
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
