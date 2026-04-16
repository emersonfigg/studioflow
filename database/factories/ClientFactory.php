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
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'birthday' => fake()->optional()->date(),
            'notes' => fake()->optional()->paragraph(),
            'last_visit_at' => fake()->optional()->dateTimeBetween('-1 year'),
        ];
    }
}
