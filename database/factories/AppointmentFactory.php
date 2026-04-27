<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('now', '+30 days');
        $endTime = (clone $startTime)->modify('+60 minutes');

        return [
            'company_id' => Company::factory(),
            'client_id' => fn (array $attributes) => Client::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'user_id' => fn (array $attributes) => User::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'service_id' => fn (array $attributes) => Service::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'scheduled',
            'source' => 'internal',
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
