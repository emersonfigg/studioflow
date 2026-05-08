<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'active' => true,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'google_id' => null,
            'avatar' => null,
            'email_verified_at' => null,
            'birthday' => fake()->optional()->date(),
            'notes' => fake()->optional()->paragraph(),
            'last_visit_at' => fake()->optional()->dateTimeBetween('-1 year'),
        ];
    }
}
