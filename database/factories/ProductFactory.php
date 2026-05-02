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
        ];
    }
}
