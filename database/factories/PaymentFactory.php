<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $appointment = Appointment::factory()->create();

        return [
            'company_id' => $appointment->company_id,
            'appointment_id' => $appointment->id,
            'user_id' => $appointment->user_id,
            'client_id' => $appointment->client_id,
            'service_id' => $appointment->service_id,
            'gross_amount' => '80.00',
            'payment_method' => 'pix',
            'commission_type' => null,
            'commission_rate' => null,
            'commission_amount' => '0.00',
            'net_amount' => '80.00',
            'paid_at' => now(),
            'notes' => null,
        ];
    }
}
