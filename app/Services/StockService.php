<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductSaleItem;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceProductConsumption;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * @throws ValidationException
     */
    public function validateStockAvailable(Product $product, float $quantity): void
    {
        if (! $product->tracksStock()) {
            return;
        }

        if (round((float) $product->stock_quantity, 2) + 1e-6 < round($quantity, 2)) {
            throw ValidationException::withMessages([
                'stock' => 'Estoque insuficiente para '.$product->name.'. Disponível: '.$product->stock_quantity.'.',
            ]);
        }
    }

    /**
     * @return Collection<int, Product>
     */
    public function getLowStockProducts(int $companyId): Collection
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->where('low_stock_alert', true)
            ->where('track_stock', true)
            ->whereNotNull('minimum_stock')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    public function increase(
        Product $product,
        float $quantity,
        string $reason,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?User $actor = null,
        ?CarbonInterface $occurredAt = null,
    ): StockMovement {
        $quantity = max(0.0, round($quantity, 2));

        return DB::transaction(function () use ($product, $quantity, $reason, $sourceType, $sourceId, $actor, $occurredAt): StockMovement {
            /** @var Product $locked */
            $locked = Product::query()
                ->where('company_id', $product->company_id)
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previous = round((float) $locked->stock_quantity, 2);
            $newQty = round($previous + $quantity, 2);
            $unitCost = $locked->cost_price !== null ? round((float) $locked->cost_price, 2) : null;
            $totalCost = $unitCost !== null ? round($unitCost * $quantity, 2) : null;

            $locked->update(['stock_quantity' => $newQty]);

            return StockMovement::query()->create([
                'company_id' => $locked->company_id,
                'product_id' => $locked->id,
                'user_id' => $actor?->id,
                'type' => StockMovement::TYPE_IN,
                'quantity' => $quantity,
                'previous_quantity' => $previous,
                'new_quantity' => $newQty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reason' => $reason,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'occurred_at' => $occurredAt ? CarbonImmutable::instance($occurredAt) : now(),
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function decrease(
        Product $product,
        float $quantity,
        string $reason,
        string $type,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?User $actor = null,
        ?CarbonInterface $occurredAt = null,
    ): ?StockMovement {
        $quantity = max(0.0, round($quantity, 2));

        if ($quantity <= 0) {
            return null;
        }

        if (! $product->tracksStock()) {
            return null;
        }

        return DB::transaction(function () use ($product, $quantity, $reason, $type, $sourceType, $sourceId, $actor, $occurredAt): StockMovement {
            /** @var Product $locked */
            $locked = Product::query()
                ->where('company_id', $product->company_id)
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previous = round((float) $locked->stock_quantity, 2);

            if ($previous + 1e-6 < $quantity) {
                throw ValidationException::withMessages([
                    'stock' => 'Estoque insuficiente para '.$locked->name.'. Disponível: '.$previous.'.',
                ]);
            }

            $newQty = round($previous - $quantity, 2);
            $unitCost = $locked->cost_price !== null ? round((float) $locked->cost_price, 2) : null;
            $totalCost = $unitCost !== null ? round($unitCost * $quantity, 2) : null;

            $locked->update(['stock_quantity' => $newQty]);

            return StockMovement::query()->create([
                'company_id' => $locked->company_id,
                'product_id' => $locked->id,
                'user_id' => $actor?->id,
                'type' => $type,
                'quantity' => $quantity,
                'previous_quantity' => $previous,
                'new_quantity' => $newQty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reason' => $reason,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'occurred_at' => $occurredAt ? CarbonImmutable::instance($occurredAt) : now(),
            ]);
        });
    }

    /**
     * Set absolute stock level (difference recorded as adjustment).
     */
    public function adjust(
        Product $product,
        float $newQuantity,
        string $reason,
        ?User $actor = null,
        ?CarbonInterface $occurredAt = null,
    ): StockMovement {
        $newQuantity = max(0.0, round($newQuantity, 2));

        return DB::transaction(function () use ($product, $newQuantity, $reason, $actor, $occurredAt): StockMovement {
            /** @var Product $locked */
            $locked = Product::query()
                ->where('company_id', $product->company_id)
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previous = round((float) $locked->stock_quantity, 2);
            $delta = round($newQuantity - $previous, 2);

            $locked->update(['stock_quantity' => $newQuantity]);

            return StockMovement::query()->create([
                'company_id' => $locked->company_id,
                'product_id' => $locked->id,
                'user_id' => $actor?->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity' => abs($delta),
                'previous_quantity' => $previous,
                'new_quantity' => $newQuantity,
                'unit_cost' => null,
                'total_cost' => null,
                'reason' => $reason,
                'source_type' => null,
                'source_id' => null,
                'occurred_at' => $occurredAt ? CarbonImmutable::instance($occurredAt) : now(),
            ]);
        });
    }

    /**
     * Record stock movements for each line of a finalized product sale.
     *
     * @throws ValidationException
     */
    public function applyProductSaleMovements(ProductSale $sale, User $actor): void
    {
        $sale->loadMissing(['items.product']);

        foreach ($sale->items as $item) {
            $product = $item->product;

            if (! $product) {
                continue;
            }

            $qty = round((float) $item->quantity, 2);

            if ($qty <= 0) {
                continue;
            }

            $already = StockMovement::query()
                ->where('company_id', $product->company_id)
                ->where('type', StockMovement::TYPE_SALE)
                ->where('source_type', ProductSaleItem::class)
                ->where('source_id', (int) $item->id)
                ->exists();

            if ($already) {
                continue;
            }

            $this->decrease(
                $product,
                $qty,
                'Venda de produto',
                StockMovement::TYPE_SALE,
                ProductSaleItem::class,
                (int) $item->id,
                $actor,
                $sale->sold_at,
            );
        }
    }

    /**
     * Apply consumptions linked to services on a paid service order (idempotent per order + product).
     *
     * @throws ValidationException
     */
    public function applyServiceOrderConsumptions(ServiceOrder $order, User $actor, ?CarbonInterface $occurredAt = null): void
    {
        $order->loadMissing(['items.service', 'items']);

        $serviceItems = $order->items->where('type', ServiceOrderItem::TYPE_SERVICE);

        if ($serviceItems->isEmpty()) {
            return;
        }

        /** @var array<int, float> $needed */
        $needed = [];

        foreach ($serviceItems as $item) {
            if (! $item->service_id) {
                continue;
            }

            $lineQty = max(1.0, round((float) ($item->quantity ?: 1), 2));

            $links = ServiceProductConsumption::query()
                ->where('company_id', $order->company_id)
                ->where('service_id', (int) $item->service_id)
                ->where('active', true)
                ->get();

            foreach ($links as $link) {
                $consume = round((float) $link->quantity * $lineQty, 2);
                $pid = (int) $link->product_id;
                $needed[$pid] = ($needed[$pid] ?? 0.0) + $consume;
            }
        }

        if ($needed === []) {
            return;
        }

        DB::transaction(function () use ($order, $needed, $actor, $occurredAt): void {
            $products = Product::query()
                ->where('company_id', $order->company_id)
                ->whereIn('id', array_keys($needed))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($needed as $productId => $qty) {
                if ($qty <= 0) {
                    continue;
                }

                $already = StockMovement::query()
                    ->where('company_id', $order->company_id)
                    ->where('type', StockMovement::TYPE_SERVICE_CONSUMPTION)
                    ->where('source_type', ServiceOrder::class)
                    ->where('source_id', $order->id)
                    ->where('product_id', $productId)
                    ->exists();

                if ($already) {
                    continue;
                }

                /** @var Product|null $product */
                $product = $products->get($productId);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'stock' => 'Produto de consumo vinculado ao serviço não foi encontrado.',
                    ]);
                }

                $this->decrease(
                    $product,
                    $qty,
                    'Consumo por serviço (atendimento)',
                    StockMovement::TYPE_SERVICE_CONSUMPTION,
                    ServiceOrder::class,
                    $order->id,
                    $actor,
                    $occurredAt,
                );
            }
        });
    }

    /**
     * Validate that all service-linked consumptions can be fulfilled for the order.
     *
     * @throws ValidationException
     */
    public function assertServiceOrderConsumptionsAvailable(ServiceOrder $order): void
    {
        $order->loadMissing(['items']);

        $needed = $this->aggregateConsumptionsForOrder($order);

        if ($needed === []) {
            return;
        }

        $products = Product::query()
            ->where('company_id', $order->company_id)
            ->whereIn('id', array_keys($needed))
            ->get()
            ->keyBy('id');

        foreach ($needed as $productId => $qty) {
            $product = $products->get($productId);

            if (! $product) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Configuração de consumo do serviço referencia um produto inexistente.',
                ]);
            }

            if (! $product->tracksStock()) {
                continue;
            }

            $this->validateStockAvailable($product, $qty);
        }
    }

    /**
     * @return array<int, float>
     */
    public function aggregateConsumptionsForOrder(ServiceOrder $order): array
    {
        $order->loadMissing(['items']);

        $serviceItems = $order->items->where('type', ServiceOrderItem::TYPE_SERVICE);

        $needed = [];

        foreach ($serviceItems as $item) {
            if (! $item->service_id) {
                continue;
            }

            $lineQty = max(1.0, round((float) ($item->quantity ?: 1), 2));

            $links = ServiceProductConsumption::query()
                ->where('company_id', $order->company_id)
                ->where('service_id', (int) $item->service_id)
                ->where('active', true)
                ->get();

            foreach ($links as $link) {
                $consume = round((float) $link->quantity * $lineQty, 2);
                $pid = (int) $link->product_id;
                $needed[$pid] = ($needed[$pid] ?? 0.0) + $consume;
            }
        }

        return $needed;
    }

    public function serviceConsumptionsAlreadyApplied(ServiceOrder $order): bool
    {
        return StockMovement::query()
            ->where('company_id', $order->company_id)
            ->where('type', StockMovement::TYPE_SERVICE_CONSUMPTION)
            ->where('source_type', ServiceOrder::class)
            ->where('source_id', $order->id)
            ->exists();
    }
}
