<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductSaleService
{
    public function __construct(
        private readonly CashRegisterService $cashRegisterService,
    ) {
    }

    /**
     * Register a product sale with items and cash entry.
     *
     * @param  array{
     *     client_id:int,
     *     appointment_id?:int|null,
     *     user_id?:int|null,
     *     payment_method:string,
     *     sold_at?:string|null,
     *     notes?:string|null,
     *     items:array<int, array{product_id:int, quantity:int}>
     * }  $data
     */
    public function register(User $actor, array $data): ProductSale
    {
        $companyId = $actor->company_id;
        $productIds = collect($data['items'])->pluck('product_id')->all();

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $soldAt = ! empty($data['sold_at'])
            ? CarbonImmutable::parse($data['sold_at'])
            : CarbonImmutable::now();

        return DB::transaction(function () use ($actor, $data, $products, $soldAt, $companyId): ProductSale {
            /** @var Client $client */
            $client = Client::query()
                ->where('company_id', $companyId)
                ->findOrFail($data['client_id']);

            $grossAmount = collect($data['items'])->sum(function (array $item) use ($products): float {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                return round((float) ($product?->price ?? 0) * (int) $item['quantity'], 2);
            });

            $sale = ProductSale::create([
                'company_id' => $companyId,
                'client_id' => $client->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'user_id' => $data['user_id'] ?? $actor->id,
                'gross_amount' => round($grossAmount, 2),
                'payment_method' => $data['payment_method'],
                'sold_at' => $soldAt,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $index => $item) {
                /** @var Product $product */
                $product = $products->get($item['product_id']);

                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => round((float) $product->price * (int) $item['quantity'], 2),
                ]);
            }

            $client->update([
                'last_visit_at' => $soldAt,
            ]);

            $this->cashRegisterService->recordProductSale($sale->load('client'));

            return $sale->load(['client', 'user', 'items.product']);
        });
    }

    /**
     * Register a product sale during appointment conclusion.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function registerForAppointment(
        User $actor,
        Appointment $appointment,
        array $items,
        string $paymentMethod,
        ?string $notes = null,
    ): ProductSale {
        return $this->register($actor, [
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'user_id' => $appointment->user_id,
            'payment_method' => $paymentMethod,
            'sold_at' => $appointment->end_time?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            'notes' => $notes,
            'items' => $items,
        ]);
    }
}
