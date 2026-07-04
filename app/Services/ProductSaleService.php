<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\CustomerMembership;
use App\Models\MembershipPlan;
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
        private readonly ProductCommissionCalculator $productCommissionCalculator,
        private readonly ClientCommercialHistoryService $commercialHistoryService,
        private readonly StockService $stockService,
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

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Estoque insuficiente para um ou mais produtos selecionados.',
                    ]);
                }

                if ($product->tracksStock()) {
                    $this->stockService->validateStockAvailable($product, (float) $quantity);
                }
            }

            $sellerCache = [];
            $resolveSeller = function (?int $sellerId) use (&$sellerCache, $companyId): ?User {
                if (! $sellerId) {
                    return null;
                }
                if (! array_key_exists($sellerId, $sellerCache)) {
                    $sellerCache[$sellerId] = User::query()
                        ->where('company_id', $companyId)
                        ->where('active', true)
                        ->find($sellerId);
                }

                if (! $sellerCache[$sellerId]) {
                    throw ValidationException::withMessages([
                        'items' => 'Vendedor invalido para sua empresa.',
                    ]);
                }

                return $sellerCache[$sellerId];
            };

            foreach ($data['items'] as $item) {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);
                if ($product && $product->hasCommission() && empty($item['seller_id'])) {
                    throw ValidationException::withMessages([
                        'items' => 'Selecione o vendedor responsavel para produtos com comissao.',
                    ]);
                }
                if (! empty($item['seller_id'])) {
                    $resolveSeller((int) $item['seller_id']);
                }
            }

            $sale = ProductSale::create([
                'company_id' => $companyId,
                'client_id' => $client->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'status' => ProductSale::STATUS_COMPLETED,
                'user_id' => $data['user_id'] ?? $actor->id,
                'gross_amount' => round($grossAmount, 2),
                'payment_method' => $data['payment_method'],
                'sold_at' => $soldAt,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $index => $item) {
                /** @var Product $product */
                $product = $products->get($item['product_id']);
                $quantity = (int) $item['quantity'];
                $totalPrice = round((float) $product->price * $quantity, 2);
                $sellerId = ! empty($item['seller_id']) ? (int) $item['seller_id'] : null;
                $commission = $this->productCommissionCalculator->calculate($product, $quantity, $totalPrice);

                $sale->items()->create([
                    'product_id' => $product->id,
                    'seller_id' => $sellerId,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'total_price' => $totalPrice,
                    'commission_type_snapshot' => $commission['type'],
                    'commission_value_snapshot' => $commission['value'],
                    'commission_amount' => $commission['amount'],
                ]);
            }

            $this->stockService->applyProductSaleMovements($sale->fresh(['items.product']), $actor);

            $client->update([
                'last_visit_at' => $soldAt,
            ]);

            $this->cashRegisterService->recordProductSale($sale->load('client'));

            $this->commercialHistoryService->recordProductSale($sale->load(['items.product']));

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
     *     service_items?:array<int, array{service_id:int, unit_price?:float|null, price_adjustment_reason?:string|null}>,
     *     membership_items?:array<int, array{membership_plan_id:int}>,
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
                    ->availableForPos()
                    ->whereIn('id', $serviceIds)
                    ->get()
                    ->keyBy('id');

                foreach ($data['service_items'] ?? [] as $serviceRow) {
                    $serviceId = (int) ($serviceRow['service_id'] ?? 0);
                    /** @var Service|null $service */
                    $service = $services->get((int) $serviceId);

                    if (! $service) {
                        throw ValidationException::withMessages([
                            'service_items' => 'Um ou mais servicos nao estao disponiveis.',
                        ]);
                    }

                    $this->serviceOrderService->addService(
                        $order,
                        $service,
                        $professional,
                        array_key_exists('unit_price', $serviceRow) && $serviceRow['unit_price'] !== null ? (float) $serviceRow['unit_price'] : null,
                        $serviceRow['price_adjustment_reason'] ?? null,
                        $actor,
                    );
                }
            }

            foreach ($data['items'] ?? [] as $item) {
                $product = Product::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->findOrFail($item['product_id']);

                $seller = null;
                if (! empty($item['seller_id'])) {
                    $seller = User::query()
                        ->where('company_id', $companyId)
                        ->where('active', true)
                        ->find((int) $item['seller_id']);

                    if (! $seller) {
                        throw ValidationException::withMessages([
                            'items' => 'Vendedor invalido para sua empresa.',
                        ]);
                    }
                }

                if (! $seller && $product->hasCommission()) {
                    $seller = $professional;
                }

                $this->serviceOrderService->addProduct($order, $product, (int) $item['quantity'], $seller);
            }

            $membershipTotal = $this->registerMembershipItems($actor, $order, $data, $soldAt);

            $order = $order->fresh(['items.service', 'items.product']);
            $order = $this->serviceOrderService->recalculate($order);

            $discount = round(max(0, (float) ($data['discount'] ?? 0)), 2);
            $maxDiscount = round((float) $order->subtotal_services + (float) $order->subtotal_products + $membershipTotal, 2);

            if ($discount > $maxDiscount) {
                throw ValidationException::withMessages([
                    'discount' => 'O desconto nao pode ser maior que a soma dos subtotais.',
                ]);
            }

            $order->update(['discount' => $discount]);
            $order = $this->serviceOrderService->recalculate($order->fresh(['items.service', 'items.product']));

            $this->serviceOrderService->close($order, $actor, $data['payment_method'], $data['notes'] ?? null, $soldAt);

            return $order->fresh(['client', 'professional', 'items.service', 'items.product']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function registerMembershipItems(User $actor, ServiceOrder $order, array $data, CarbonImmutable $soldAt): float
    {
        $rows = collect($data['membership_items'] ?? [])
            ->filter(fn (array $row): bool => ! empty($row['membership_plan_id']))
            ->values();

        if ($rows->isEmpty()) {
            return 0.0;
        }

        $companyId = (int) $actor->company_id;
        $clientId = (int) $data['client_id'];
        $planIds = $rows->pluck('membership_plan_id')->map(fn ($id): int => (int) $id)->unique()->values();

        /** @var Collection<int, MembershipPlan> $plans */
        $plans = MembershipPlan::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->whereIn('id', $planIds)
            ->get()
            ->keyBy('id');

        if ($plans->count() !== $planIds->count()) {
            throw ValidationException::withMessages([
                'membership_items' => 'Um ou mais planos de assinatura sao invalidos.',
            ]);
        }

        $total = 0.0;

        foreach ($rows as $row) {
            /** @var MembershipPlan $plan */
            $plan = $plans->get((int) $row['membership_plan_id']);
            $startsAt = $soldAt->toDateString();
            $endsAtDate = $soldAt->addDays(max(0, $plan->resolvedCycleDays() - 1));
            $endsAt = $endsAtDate->toDateString();

            $membership = CustomerMembership::query()->create([
                'company_id' => $companyId,
                'client_id' => $clientId,
                'membership_plan_id' => $plan->id,
                'service_order_id' => $order->id,
                'status' => CustomerMembership::STATUS_ACTIVE,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'renews_at' => $plan->auto_renew ? $soldAt->addDays($plan->resolvedCycleDays())->toDateString() : null,
                'current_cycle_starts_at' => $startsAt,
                'current_cycle_ends_at' => $endsAt,
                'auto_renew' => (bool) $plan->auto_renew,
                'accepted_terms_at' => $soldAt,
            ]);

            $amount = round((float) $plan->price, 2);
            $total += $amount;

            $this->serviceOrderService->addMembership($order, $plan, $soldAt, $endsAtDate);

            if ($amount > 0) {
                $this->cashRegisterService->recordMovement(
                    $companyId,
                    $soldAt,
                    CashMovement::TYPE_INFLOW,
                    $amount,
                    'Venda de assinatura - '.$plan->name,
                    (string) $data['payment_method'],
                    CustomerMembership::class,
                    $membership->id,
                    $actor->id,
                );
            }
        }

        return round($total, 2);
    }

    /**
     * Register a product sale during appointment conclusion.
     *
     * @param  array<int, array{product_id:int, quantity:int, seller_id?:int|null}>  $items
     */
    public function registerForAppointment(
        User $actor,
        Appointment $appointment,
        array $items,
        string $paymentMethod,
        ?string $notes = null,
    ): ProductSale {
        $defaultSellerId = $appointment->user_id;
        $items = collect($items)->map(function (array $item) use ($defaultSellerId): array {
            $item['seller_id'] = $item['seller_id'] ?? $defaultSellerId;

            return $item;
        })->all();

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
