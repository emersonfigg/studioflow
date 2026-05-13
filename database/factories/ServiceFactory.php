<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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
            'name' => fake()->words(2, true),
            'description' => null,
            'duration_minutes' => fake()->numberBetween(15, 180),
            'price' => fake()->randomFloat(2, 30, 500),
            'active' => true,
            'recommended_return_days' => null,
        ];
    }

    /**
     * Provide a deterministic short description for the service.
     */
    public function withDescription(?string $description = null): self
    {
        return $this->state(fn (): array => [
            'description' => $description ?? 'Atendimento personalizado com acabamento detalhado e aplicacao de produtos profissionais.',
        ]);
    }
}
