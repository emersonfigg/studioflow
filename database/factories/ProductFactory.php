<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->bothify('PRD-###')),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 10, 120),
            'stock_quantity' => fake()->numberBetween(5, 50),
            'active' => true,
            'commission_type' => null,
            'commission_value' => null,
            'recommended_repurchase_days' => null,
        ];
    }

    /**
     * Configure a fixed (per-unit) commission for the product.
     */
    public function withFixedCommission(float $value): self
    {
        return $this->state(fn (): array => [
            'commission_type' => Product::COMMISSION_TYPE_FIXED,
            'commission_value' => round($value, 2),
        ]);
    }

    /**
     * Configure a percentage commission for the product.
     */
    public function withPercentageCommission(float $percent): self
    {
        return $this->state(fn (): array => [
            'commission_type' => Product::COMMISSION_TYPE_PERCENTAGE,
            'commission_value' => round($percent, 2),
        ]);
    }
}
