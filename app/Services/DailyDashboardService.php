<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\ProductSale;
use App\Models\ProductSaleItem;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DailyDashboardService
{
    /**
     * @param  array{date:CarbonImmutable,user_id:int|null,status:string|null,payment_method:string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(int $companyId, array $filters): array
    {
        $date = $filters['date'];
        $from = $date->startOfDay();
        $to = $date->endOfDay();
        $selectedUserId = $filters['user_id'];
        $selectedStatus = $filters['status'];
        $selectedPaymentMethod = $filters['payment_method'];

        $users = User::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $appointments = Appointment::query()
            ->with(['client:id,name', 'user:id,name', 'service:id,name', 'services:id,name'])
            ->where('company_id', $companyId)
            ->whereBetween('start_time', [$from, $to])
            ->when($selectedUserId !== null, fn ($query) => $query->where('user_id', $selectedUserId))
            ->when($selectedStatus !== null, fn ($query) => $query->where('status', $selectedStatus))
            ->orderBy('start_time')
            ->get();

        $orders = ServiceOrder::query()
            ->with([
                'appointment:id,company_id,client_id,user_id,service_id,start_time,status',
                'appointment.service:id,name',
                'appointment.services:id,name',
                'client:id,name',
                'professional:id,name',
                'payment:id,service_order_id,payment_method,commission_amount,net_amount,gross_amount',
                'productSale:id,service_order_id,payment_method,gross_amount',
                'productSale.items.product:id,name,stock_quantity,minimum_stock,cost_price,track_stock,low_stock_alert,unit',
                'productSale.items.seller:id,name',
                'items.service:id,name',
                'items.product:id,name,stock_quantity,minimum_stock,cost_price,track_stock,low_stock_alert,unit',
                'items.professional:id,name',
                'items.seller:id,name',
            ])
            ->where('company_id', $companyId)
            ->where('status', ServiceOrder::STATUS_PAID)
            ->whereBetween('closed_at', [$from, $to])
            ->when($selectedUserId !== null, function ($query) use ($selectedUserId): void {
                $query->where(function ($inner) use ($selectedUserId): void {
                    $inner->where('professional_id', $selectedUserId)
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                            ->where('professional_id', $selectedUserId)
                            ->orWhere('seller_id', $selectedUserId));
                });
            })
            ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                $query->where(function ($inner) use ($selectedStatus): void {
                    $inner->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->where('status', $selectedStatus));

                    if ($selectedStatus === ServiceOrder::STATUS_PAID) {
                        $inner->orWhere('status', ServiceOrder::STATUS_PAID);
                    }
                });
            })
            ->when($selectedPaymentMethod !== null, function ($query) use ($selectedPaymentMethod): void {
                $query->where(function ($inner) use ($selectedPaymentMethod): void {
                    $inner->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('payment_method', $selectedPaymentMethod))
                        ->orWhereHas('productSale', fn ($saleQuery) => $saleQuery->where('payment_method', $selectedPaymentMethod));
                });
            })
            ->orderBy('closed_at')
            ->get();

        $standaloneSales = ProductSale::query()
            ->with(['client:id,name', 'user:id,name', 'items.product:id,name,stock_quantity,minimum_stock,cost_price,track_stock,low_stock_alert,unit', 'items.seller:id,name'])
            ->where('company_id', $companyId)
            ->where('status', ProductSale::STATUS_COMPLETED)
            ->whereNull('service_order_id')
            ->whereBetween('sold_at', [$from, $to])
            ->when($selectedUserId !== null, function ($query) use ($selectedUserId): void {
                $query->where(function ($inner) use ($selectedUserId): void {
                    $inner->where('user_id', $selectedUserId)
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery->where('seller_id', $selectedUserId));
                });
            })
            ->when($selectedPaymentMethod !== null, fn ($query) => $query->where('payment_method', $selectedPaymentMethod))
            ->orderBy('sold_at')
            ->get();

        $serviceItems = $orders
            ->flatMap(fn (ServiceOrder $order): Collection => $order->items->where('type', ServiceOrderItem::TYPE_SERVICE)->values())
            ->values();
        $productItems = $this->productSaleItems($orders, $standaloneSales);
        $grossRevenue = (float) $orders->sum(fn (ServiceOrder $order): float => (float) $order->total)
            + (float) $standaloneSales->sum(fn (ProductSale $sale): float => (float) $sale->gross_amount);
        $commissionAmount = (float) $orders->sum(fn (ServiceOrder $order): float => (float) ($order->payment?->commission_amount ?? 0))
            + (float) $productItems->sum(fn (ProductSaleItem $item): float => (float) $item->commission_amount);
        $netRevenue = max(0, round($grossRevenue - $commissionAmount, 2));
        $completedOrdersCount = $orders->filter(fn (ServiceOrder $order): bool => $order->items->where('type', ServiceOrderItem::TYPE_SERVICE)->isNotEmpty())->count();
        $ticketsCount = max(1, $completedOrdersCount + $standaloneSales->count());

        $attendedClientIds = $orders->pluck('client_id')
            ->merge($standaloneSales->pluck('client_id'))
            ->filter()
            ->unique()
            ->values();

        $appointmentRows = $this->appointmentRows($orders);
        $productRows = $this->productRows($productItems);
        $serviceRows = $this->serviceRows($serviceItems, $orders);

        return [
            'filters' => [
                'date' => $date,
                'user_id' => $selectedUserId,
                'status' => $selectedStatus,
                'payment_method' => $selectedPaymentMethod,
            ],
            'users' => $users,
            'payment_methods' => Payment::paymentMethodOptions(),
            'status_options' => $this->statusOptions(),
            'kpis' => [
                'gross_revenue' => $grossRevenue,
                'net_revenue' => $netRevenue,
                'commissions' => $commissionAmount,
                'completed_appointments' => $completedOrdersCount,
                'scheduled_appointments' => $appointments->where('status', '!=', 'cancelled')->count(),
                'cancelled_appointments' => $appointments->where('status', 'cancelled')->count(),
                'average_ticket' => $grossRevenue > 0 ? round($grossRevenue / $ticketsCount, 2) : 0.0,
                'products_sold' => (int) $productItems->sum('quantity'),
                'services_done' => (int) $serviceItems->sum('quantity'),
                'clients_attended' => $attendedClientIds->count(),
                'new_clients' => Client::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
            ],
            'appointments' => $appointmentRows,
            'products' => $productRows,
            'services' => $serviceRows,
            'rankings' => [
                'professionals' => $this->professionalRanking($orders, $productItems),
                'services' => $serviceRows->sortByDesc('quantity')->take(6)->values(),
                'products' => $productRows->sortByDesc('quantity')->take(6)->values(),
                'clients' => $this->clientRanking($orders, $standaloneSales),
            ],
            'charts' => [
                'revenue_by_hour' => $this->revenueByHour($orders, $standaloneSales),
                'revenue_by_payment_method' => $this->revenueByPaymentMethod($orders, $standaloneSales),
                'production_by_professional' => $this->productionByProfessional($orders, $productItems),
                'mix' => [
                    ['label' => 'Servicos', 'value' => (float) $orders->sum(fn (ServiceOrder $order): float => (float) $order->subtotal_services)],
                    ['label' => 'Produtos', 'value' => (float) $orders->sum(fn (ServiceOrder $order): float => (float) $order->subtotal_products) + (float) $standaloneSales->sum('gross_amount')],
                ],
                'appointment_status' => $appointments
                    ->groupBy('status')
                    ->map(fn (Collection $group, string $status): array => [
                        'label' => $this->statusOptions()[$status] ?? ucfirst($status),
                        'value' => $group->count(),
                    ])
                    ->values(),
            ],
            'has_data' => $orders->isNotEmpty() || $standaloneSales->isNotEmpty() || $appointments->isNotEmpty(),
        ];
    }

    /**
     * @param  Collection<int, ServiceOrder>  $orders
     * @param  Collection<int, ProductSale>  $standaloneSales
     * @return Collection<int, ProductSaleItem>
     */
    private function productSaleItems(Collection $orders, Collection $standaloneSales): Collection
    {
        return $orders
            ->pluck('productSale')
            ->filter()
            ->merge($standaloneSales)
            ->flatMap(fn (ProductSale $sale): Collection => $sale->items->values())
            ->values();
    }

    /**
     * @param  Collection<int, ServiceOrder>  $orders
     * @return Collection<int, array<string, mixed>>
     */
    private function appointmentRows(Collection $orders): Collection
    {
        return $orders->map(function (ServiceOrder $order): array {
            $serviceItems = $order->items->where('type', ServiceOrderItem::TYPE_SERVICE)->values();
            $productItems = $order->productSale?->items ?? $order->items->where('type', ServiceOrderItem::TYPE_PRODUCT)->values();
            $commission = (float) ($order->payment?->commission_amount ?? 0)
                + (float) $productItems->sum(fn ($item): float => (float) ($item->commission_amount ?? 0));

            return [
                'time' => $order->closed_at ?? $order->appointment?->start_time,
                'client' => $order->client?->name ?? $order->appointment?->client?->name ?? 'Cliente nao informado',
                'professional' => $order->professional?->name ?? $order->appointment?->user?->name ?? 'Equipe',
                'services' => $serviceItems->map(fn (ServiceOrderItem $item): string => $item->service?->name ?? $item->description ?? 'Servico')->implode(', '),
                'products' => $productItems->map(fn ($item): string => ($item->product?->name ?? 'Produto').' x'.(int) $item->quantity)->implode(', '),
                'gross' => (float) $order->total,
                'discount' => (float) $order->discount,
                'commission' => $commission,
                'net' => max(0, round((float) $order->total - $commission, 2)),
                'payment_method' => Payment::labelForPaymentMethod((string) ($order->payment?->payment_method ?? $order->productSale?->payment_method ?? '')),
                'status' => $order->appointment?->statusLabel() ?? 'Pago',
            ];
        })->values();
    }

    /**
     * @param  Collection<int, ProductSaleItem>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function productRows(Collection $items): Collection
    {
        return $items
            ->groupBy('product_id')
            ->filter(fn (Collection $group, mixed $productId): bool => $productId !== null)
            ->map(function (Collection $group): array {
                /** @var ProductSaleItem $first */
                $first = $group->first();
                $product = $first->product;
                $quantity = (int) $group->sum('quantity');
                $revenue = (float) $group->sum(fn (ProductSaleItem $item): float => (float) $item->total_price);
                $cost = $product?->cost_price !== null ? round((float) $product->cost_price * $quantity, 2) : null;
                $margin = $cost !== null ? round($revenue - $cost, 2) : null;
                $lowStock = $product
                    && (bool) $product->track_stock
                    && (bool) $product->low_stock_alert
                    && $product->minimum_stock !== null
                    && (float) $product->stock_quantity <= (float) $product->minimum_stock;

                return [
                    'product' => $product,
                    'name' => $product?->name ?? 'Produto removido',
                    'quantity' => $quantity,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'margin' => $margin,
                    'stock' => $product?->stock_quantity,
                    'unit' => $product?->unit,
                    'low_stock' => $lowStock,
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * @param  Collection<int, ServiceOrderItem>  $items
     * @param  Collection<int, ServiceOrder>  $orders
     * @return Collection<int, array<string, mixed>>
     */
    private function serviceRows(Collection $items, Collection $orders): Collection
    {
        $ordersById = $orders->keyBy('id');

        return $items
            ->groupBy(fn (ServiceOrderItem $item): string => ($item->service_id ?? 0).'-'.($item->professional_id ?? $ordersById->get($item->service_order_id)?->professional_id ?? 0))
            ->map(function (Collection $group) use ($ordersById): array {
                /** @var ServiceOrderItem $first */
                $first = $group->first();
                $order = $ordersById->get($first->service_order_id);
                $professional = $first->professional ?? $order?->professional;
                $quantity = (int) $group->sum('quantity');
                $revenue = (float) $group->sum(fn (ServiceOrderItem $item): float => (float) $item->total_price);

                return [
                    'service' => $first->service,
                    'name' => $first->service?->name ?? $first->description ?? 'Servico',
                    'quantity' => $quantity,
                    'revenue' => $revenue,
                    'professional' => $professional?->name ?? 'Equipe',
                    'ticket_average' => $quantity > 0 ? round($revenue / $quantity, 2) : 0.0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * @param  Collection<int, ServiceOrder>  $orders
     * @param  Collection<int, ProductSaleItem>  $productItems
     * @return Collection<int, array<string, mixed>>
     */
    private function professionalRanking(Collection $orders, Collection $productItems): Collection
    {
        $serviceRows = $orders
            ->groupBy('professional_id')
            ->filter(fn (Collection $group, mixed $professionalId): bool => $professionalId !== null)
            ->map(function (Collection $group): array {
                /** @var ServiceOrder $first */
                $first = $group->first();

                return [
                    'name' => $first->professional?->name ?? 'Equipe',
                    'gross' => (float) $group->sum(fn (ServiceOrder $order): float => (float) $order->total),
                    'commission' => (float) $group->sum(fn (ServiceOrder $order): float => (float) ($order->payment?->commission_amount ?? 0)),
                    'count' => $group->count(),
                ];
            });

        $productRows = $productItems
            ->groupBy('seller_id')
            ->filter(fn (Collection $group, mixed $sellerId): bool => $sellerId !== null)
            ->map(function (Collection $group): array {
                /** @var ProductSaleItem $first */
                $first = $group->first();

                return [
                    'name' => $first->seller?->name ?? 'Equipe',
                    'gross' => (float) $group->sum(fn (ProductSaleItem $item): float => (float) $item->total_price),
                    'commission' => (float) $group->sum(fn (ProductSaleItem $item): float => (float) $item->commission_amount),
                    'count' => (int) $group->sum('quantity'),
                ];
            });

        return $serviceRows
            ->merge($productRows)
            ->groupBy('name')
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'gross' => (float) $group->sum('gross'),
                'commission' => (float) $group->sum('commission'),
                'count' => (int) $group->sum('count'),
            ])
            ->sortByDesc('gross')
            ->take(6)
            ->values();
    }

    /**
     * @param  Collection<int, ServiceOrder>  $orders
     * @param  Collection<int, ProductSale>  $standaloneSales
     * @return Collection<int, array<string, mixed>>
     */
    private function clientRanking(Collection $orders, Collection $standaloneSales): Collection
    {
        return $orders
            ->map(fn (ServiceOrder $order): array => [
                'client_id' => $order->client_id,
                'name' => $order->client?->name ?? 'Cliente nao informado',
                'gross' => (float) $order->total,
            ])
            ->merge($standaloneSales->map(fn (ProductSale $sale): array => [
                'client_id' => $sale->client_id,
                'name' => $sale->client?->name ?? 'Cliente nao informado',
                'gross' => (float) $sale->gross_amount,
            ]))
            ->filter(fn (array $row): bool => $row['client_id'] !== null)
            ->groupBy('client_id')
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'name' => $first['name'],
                    'gross' => (float) $group->sum('gross'),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('gross')
            ->take(6)
            ->values();
    }

    /**
     * @param  Collection<int, ServiceOrder>  $orders
     * @param  Collection<int, ProductSale>  $standaloneSales
     * @return Collection<int, array{label:string,value:float}>
     */
    private function revenueByHour(Collection $orders, Collection $standaloneSales): Collection
    {
        $rows = collect(range(7, 22))->mapWithKeys(fn (int $hour): array => [
            sprintf('%02dh', $hour) => 0.0,
        ]);

        foreach ($orders as $order) {
            $hour = (int) ($order->closed_at?->format('H') ?? 0);
            $key = sprintf('%02dh', $hour);
            if ($rows->has($key)) {
                $rows[$key] += (float) $order->total;
            }
        }

        foreach ($standaloneSales as $sale) {
            $hour = (int) ($sale->sold_at?->format('H') ?? 0);
            $key = sprintf('%02dh', $hour);
            if ($rows->has($key)) {
                $rows[$key] += (float) $sale->gross_amount;
            }
        }

        return $rows->map(fn (float $value, string $label): array => ['label' => $label, 'value' => $value])->values();
    }

    /**
     * @param  Collection<int, ServiceOrder>  $orders
     * @param  Collection<int, ProductSale>  $standaloneSales
     * @return Collection<int, array{label:string,value:float}>
     */
    private function revenueByPaymentMethod(Collection $orders, Collection $standaloneSales): Collection
    {
        return $orders
            ->map(fn (ServiceOrder $order): array => [
                'method' => (string) ($order->payment?->payment_method ?? $order->productSale?->payment_method ?? ''),
                'gross' => (float) $order->total,
            ])
            ->merge($standaloneSales->map(fn (ProductSale $sale): array => [
                'method' => (string) $sale->payment_method,
                'gross' => (float) $sale->gross_amount,
            ]))
            ->filter(fn (array $row): bool => $row['method'] !== '')
            ->groupBy('method')
            ->map(fn (Collection $group, string $method): array => [
                'label' => Payment::labelForPaymentMethod($method),
                'value' => (float) $group->sum('gross'),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * @param  Collection<int, ServiceOrder>  $orders
     * @param  Collection<int, ProductSaleItem>  $productItems
     * @return Collection<int, array{label:string,value:float}>
     */
    private function productionByProfessional(Collection $orders, Collection $productItems): Collection
    {
        return $this->professionalRanking($orders, $productItems)
            ->map(fn (array $row): array => ['label' => $row['name'], 'value' => (float) $row['gross']])
            ->values();
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return [
            'scheduled' => 'Agendado',
            'pending_payment' => 'Aguardando pagamento',
            'confirmed' => 'Confirmado',
            'in_progress' => 'Em atendimento',
            'completed' => 'Concluido',
            'cancelled' => 'Cancelado',
            'no_show' => 'Falta',
            ServiceOrder::STATUS_PAID => 'Pago',
        ];
    }
}
