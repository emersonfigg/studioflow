<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSale>
 */
class ProductSaleFactory extends Factory
{
    protected $model = ProductSale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $client = Client::factory()->create();
        $user = User::factory()->for($client->company)->create();

        return [
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'appointment_id' => null,
            'user_id' => $user->id,
            'gross_amount' => 45.00,
            'payment_method' => 'pix',
            'sold_at' => now(),
            'notes' => null,
        ];
    }
}
