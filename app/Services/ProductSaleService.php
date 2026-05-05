<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductSaleService
{
    public function __construct(
        private readonly CashRegisterService $cashRegisterService,
        private readonly ServiceOrderService $serviceOrderService,
    ) {}

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

        $soldAt = ! empty($data['sold_at'])
            ? CarbonImmutable::parse($data['sold_at'])
            : CarbonImmutable::now();

        return DB::transaction(function () use ($actor, $data, $productIds, $soldAt, $companyId): ProductSale {
            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Client $client */
            $client = Client::query()
                ->where('company_id', $companyId)
                ->findOrFail($data['client_id']);

            $grossAmount = collect($data['items'])->sum(function (array $item) use ($products): float {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                return round((float) ($product?->price ?? 0) * (int) $item['quantity'], 2);
            });

            $requestedByProduct = collect($data['items'])
                ->groupBy('product_id')
                ->map(fn (Collection $items): int => $items->sum(fn (array $item): int => (int) $item['quantity']));

            foreach ($requestedByProduct as $productId => $quantity) {
                /** @var Product|null $product */
                $product = $products->get((int) $productId);

                if (! $product || $product->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => 'Estoque insuficiente para um ou mais produtos selecionados.',
                    ]);
                }
            }

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

                $product->decrement('stock_quantity', (int) $item['quantity']);
            }

            $client->update([
                'last_visit_at' => $soldAt,
            ]);

            $this->cashRegisterService->recordProductSale($sale->load('client'));

            return $sale->load(['client', 'user', 'items.product']);
        });
    }

    /**
     * Register a standalone service order without creating an appointment.
     *
     * @param  array{
     *     client_id:int,
     *     user_id:int,
     *     appointment_id?:int|null,
     *     payment_method:string,
     *     sold_at?:string|null,
     *     notes?:string|null,
     *     service_items?:array<int, array{service_id:int}>,
     *     items?:array<int, array{product_id:int, quantity:int}>
     * }  $data
     */
    public function registerStandaloneOrder(User $actor, array $data): ServiceOrder
    {
        $companyId = $actor->company_id;
        $soldAt = ! empty($data['sold_at'])
            ? CarbonImmutable::parse($data['sold_at'])
            : CarbonImmutable::now();

        return DB::transaction(function () use ($actor, $data, $soldAt, $companyId): ServiceOrder {
            $appointmentId = $data['appointment_id'] ?? null;

            $appointment = null;
            if ($appointmentId) {
                $appointment = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereKey($appointmentId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $professionalId = (int) ($data['user_id'] ?? $appointment?->user_id ?? $actor->id);

            /** @var User $professional */
            $professional = User::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->findOrFail($professionalId);

            if ($appointmentId) {
                /** @var Client $client */
                $client = Client::query()
                    ->where('company_id', $companyId)
                    ->findOrFail($appointment->client_id);

                $order = ServiceOrder::query()
                    ->where('company_id', $companyId)
                    ->where('appointment_id', $appointmentId)
                    ->lockForUpdate()
                    ->first();

                if ($order && $order->status === ServiceOrder::STATUS_PAID) {
                    throw ValidationException::withMessages([
                        'appointment_id' => 'Este agendamento ja foi finalizado no caixa.',
                    ]);
                }

                if (! $order) {
                    $order = ServiceOrder::create([
                        'company_id' => $companyId,
                        'appointment_id' => $appointmentId,
                        'client_id' => $client->id,
                        'professional_id' => $professional->id,
                        'status' => ServiceOrder::STATUS_OPEN,
                        'opened_at' => $soldAt,
                    ]);
                } else {
                    $this->serviceOrderService->clearAllItems($order);
                    $order->update([
                        'client_id' => $client->id,
                        'professional_id' => $professional->id,
                    ]);
                    $order = $order->fresh();
                }
            } else {
                /** @var Client $client */
                $client = Client::query()
                    ->where('company_id', $companyId)
                    ->findOrFail($data['client_id']);

                $order = ServiceOrder::create([
                    'company_id' => $companyId,
                    'appointment_id' => null,
                    'client_id' => $client->id,
                    'professional_id' => $professional->id,
                    'status' => ServiceOrder::STATUS_OPEN,
                    'opened_at' => $soldAt,
                ]);
            }

            $serviceIds = collect($data['service_items'] ?? [])->pluck('service_id')->filter()->all();

            if ($serviceIds !== []) {
                /** @var Collection<int, Service> $services */
                $services = Service::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->whereIn('id', $serviceIds)
                    ->get()
                    ->keyBy('id');

                foreach ($serviceIds as $serviceId) {
                    /** @var Service|null $service */
                    $service = $services->get((int) $serviceId);

                    if (! $service) {
                        throw ValidationException::withMessages([
                            'service_items' => 'Um ou mais servicos nao estao disponiveis.',
                        ]);
                    }

                    $this->serviceOrderService->addService($order, $service, $professional);
                }
            }

            foreach ($data['items'] ?? [] as $item) {
                $product = Product::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->findOrFail($item['product_id']);

                $this->serviceOrderService->addProduct($order, $product, (int) $item['quantity']);
            }

            $this->serviceOrderService->close($order, $actor, $data['payment_method'], $data['notes'] ?? null, $soldAt);

            return $order->fresh(['client', 'professional', 'items.service', 'items.product']);
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
