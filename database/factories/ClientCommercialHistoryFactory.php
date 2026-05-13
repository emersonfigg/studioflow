<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientCommercialHistory;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientCommercialHistory>
 */
class ClientCommercialHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $occurredAt = CarbonImmutable::now()->subDays(fake()->numberBetween(0, 200));

        return [
            'company_id' => Company::factory(),
            'client_id' => fn (array $attributes) => Client::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'item_type' => ClientCommercialHistory::ITEM_TYPE_PRODUCT,
            'item_id' => null,
            'item_name_snapshot' => fake()->words(2, true),
            'quantity' => 1,
            'unit_price_snapshot' => fake()->randomFloat(2, 10, 200),
            'total_amount_snapshot' => fake()->randomFloat(2, 10, 200),
            'professional_id' => null,
            'sale_id' => null,
            'sale_item_id' => null,
            'appointment_id' => null,
            'occurred_at' => $occurredAt,
            'recommendation_days' => null,
            'next_recommendation_date' => null,
            'source' => ClientCommercialHistory::SOURCE_PDV,
            'metadata' => null,
        ];
    }

    public function product(?int $productId = null, ?int $days = null): self
    {
        return $this->state(function (array $attributes) use ($productId, $days): array {
            $occurredAt = $attributes['occurred_at'] instanceof CarbonImmutable
                ? $attributes['occurred_at']
                : CarbonImmutable::parse((string) ($attributes['occurred_at'] ?? CarbonImmutable::now()));

            return [
                'item_type' => ClientCommercialHistory::ITEM_TYPE_PRODUCT,
                'item_id' => $productId,
                'recommendation_days' => $days,
                'next_recommendation_date' => $days
                    ? $occurredAt->startOfDay()->addDays($days)->toDateString()
                    : null,
            ];
        });
    }

    public function service(?int $serviceId = null, ?int $days = null): self
    {
        return $this->state(function (array $attributes) use ($serviceId, $days): array {
            $occurredAt = $attributes['occurred_at'] instanceof CarbonImmutable
                ? $attributes['occurred_at']
                : CarbonImmutable::parse((string) ($attributes['occurred_at'] ?? CarbonImmutable::now()));

            return [
                'item_type' => ClientCommercialHistory::ITEM_TYPE_SERVICE,
                'item_id' => $serviceId,
                'recommendation_days' => $days,
                'next_recommendation_date' => $days
                    ? $occurredAt->startOfDay()->addDays($days)->toDateString()
                    : null,
            ];
        });
    }
}
