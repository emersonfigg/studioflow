<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardPerformanceService
{
    /**
     * Build dashboard data with applied filters.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, selected_user_id:int|null, period:string, period_label:string}  $filters
     * @return array<string, mixed>
     */
    public function build(int $companyId, array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $selectedUserId = $filters['selected_user_id'];
        $period = $filters['period'];
        $periodLabel = $filters['period_label'];

        $orders = ServiceOrder::query()
            ->where('company_id', $companyId)
            ->where('status', ServiceOrder::STATUS_PAID)
            ->whereBetween('closed_at', [$from, $to])
            ->when($selectedUserId !== null, fn ($query) => $query->where('professional_id', $selectedUserId))
            ->with(['client:id,name,last_visit_at', 'professional:id,name', 'items'])
            ->get();

        $orderIds = $orders->pluck('id');

        $items = ServiceOrderItem::query()
            ->whereIn('service_order_id', $orderIds)
            ->with(['service:id,name', 'product:id,name,stock_quantity'])
            ->get();

        $payments = Payment::query()
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [$from, $to])
            ->when($selectedUserId !== null, fn ($query) => $query->where('user_id', $selectedUserId))
            ->where(function ($query) {
                $query->whereNull('appointment_id')
                    ->orWhereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->where('status', '!=', 'cancelled'));
            })
            ->get();

        $previousRange = $this->previousRange($from, $to);
        $previousRevenue = (float) ServiceOrder::query()
            ->where('company_id', $companyId)
            ->where('status', ServiceOrder::STATUS_PAID)
            ->whereBetween('closed_at', [$previousRange['from'], $previousRange['to']])
            ->when($selectedUserId !== null, fn ($query) => $query->where('professional_id', $selectedUserId))
            ->sum('total');

        $revenueTotal = (float) $orders->sum(fn (ServiceOrder $order): float => (float) $order->total);
        $serviceRevenue = (float) $orders->sum(fn (ServiceOrder $order): float => (float) $order->subtotal_services);
        $productRevenue = (float) $orders->sum(fn (ServiceOrder $order): float => (float) $order->subtotal_products);
        $completedAppointments = $orders->count();
        $clientsAttended = $orders->pluck('client_id')->filter()->unique()->count();
        $ticketAverage = $completedAppointments > 0 ? round($revenueTotal / $completedAppointments, 2) : 0.0;

        $ordersByClient = $orders
            ->groupBy('client_id')
            ->filter(fn (Collection $group, $clientId): bool => $clientId !== null);
        $recurringClients = $ordersByClient->filter(fn (Collection $group): bool => $group->count() >= 2)->count();
        $returnRate = $clientsAttended > 0 ? round(($recurringClients / $clientsAttended) * 100, 2) : 0.0;

        $serviceItems = $items->where('type', ServiceOrderItem::TYPE_SERVICE)->values();
        $productItems = $items->where('type', ServiceOrderItem::TYPE_PRODUCT)->values();
        $servicesSold = (int) $serviceItems->sum('quantity');
        $productsSold = (int) $productItems->sum('quantity');

        $serviceOrdersWithServices = $orders->filter(
            fn (ServiceOrder $order): bool => $order->items->where('type', ServiceOrderItem::TYPE_SERVICE)->isNotEmpty()
        );
        $serviceOrdersWithUpsell = $serviceOrdersWithServices->filter(
            fn (ServiceOrder $order): bool => $order->items->where('type', ServiceOrderItem::TYPE_SERVICE)->sum('quantity') >= 2
        );
        $upsellRate = $serviceOrdersWithServices->count() > 0
            ? round(($serviceOrdersWithUpsell->count() / $serviceOrdersWithServices->count()) * 100, 2)
            : 0.0;

        $revenueByDay = $orders
            ->groupBy(fn (ServiceOrder $order): string => $order->closed_at?->format('Y-m-d') ?? '')
            ->filter(fn (Collection $dayOrders, string $day): bool => $day !== '')
            ->map(function (Collection $dayOrders, string $day): array {
                return [
                    'date' => CarbonImmutable::parse($day),
                    'amount' => (float) $dayOrders->sum(fn (ServiceOrder $order): float => (float) $order->total),
                ];
            })
            ->sortBy(fn (array $row): string => $row['date']->toDateString())
            ->values();

        $newClients = $ordersByClient
            ->filter(function (Collection $clientOrders): bool {
                /** @var ServiceOrder $firstOrder */
                $firstOrder = $clientOrders->sortBy('closed_at')->first();

                return ServiceOrder::query()
                    ->where('company_id', $firstOrder->company_id)
                    ->where('status', ServiceOrder::STATUS_PAID)
                    ->where('client_id', $firstOrder->client_id)
                    ->where('closed_at', '<', $firstOrder->closed_at)
                    ->doesntExist();
            })
            ->count();

        $inactiveClients = Client::query()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereNull('last_visit_at')
                    ->orWhere('last_visit_at', '<', now()->subDays(30));
            })
            ->count();

        $topClients = $ordersByClient
            ->map(function (Collection $group): array {
                /** @var ServiceOrder $first */
                $first = $group->first();

                return [
                    'client' => $first->client,
                    'revenue' => (float) $group->sum(fn (ServiceOrder $order): float => (float) $order->total),
                    'orders' => $group->count(),
                ];
            })
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        $clientsAtRisk = Client::query()
            ->where('company_id', $companyId)
            ->whereNotNull('last_visit_at')
            ->where('last_visit_at', '<=', now()->subDays(30))
            ->orderBy('last_visit_at')
            ->limit(5)
            ->get(['id', 'name', 'last_visit_at']);

        $professionalBase = $orders
            ->groupBy('professional_id')
            ->filter(fn (Collection $group, $professionalId): bool => $professionalId !== null)
            ->map(function (Collection $group): array {
                /** @var ServiceOrder $first */
                $first = $group->first();
                $clients = $group->pluck('client_id')->filter();
                $uniqueClients = $clients->unique()->count();
                $recurringClients = $clients->countBy()->filter(fn (int $count): bool => $count >= 2)->count();

                return [
                    'professional' => $first->professional,
                    'revenue' => (float) $group->sum(fn (ServiceOrder $order): float => (float) $order->total),
                    'appointments' => $group->count(),
                    'ticket_average' => $group->count() > 0
                        ? round((float) $group->sum(fn (ServiceOrder $order): float => (float) $order->total) / $group->count(), 2)
                        : 0.0,
                    'return_rate' => $uniqueClients > 0 ? round(($recurringClients / $uniqueClients) * 100, 2) : 0.0,
                ];
            });

        $commissionByProfessional = $payments
            ->groupBy('user_id')
            ->map(fn (Collection $group): float => (float) $group->sum(fn (Payment $payment): float => (float) $payment->commission_amount));

        $professionalRanking = $professionalBase
            ->map(function (array $row) use ($commissionByProfessional): array {
                $professionalId = $row['professional']?->id;
                $row['commission'] = (float) ($commissionByProfessional->get($professionalId) ?? 0.0);

                return $row;
            })
            ->sortByDesc('revenue')
            ->take(10)
            ->values();

        $servicesByPerformance = $serviceItems
            ->groupBy('service_id')
            ->filter(fn (Collection $group, $serviceId): bool => $serviceId !== null)
            ->map(function (Collection $group): array {
                /** @var ServiceOrderItem $first */
                $first = $group->first();

                return [
                    'service' => $first->service,
                    'quantity' => (int) $group->sum('quantity'),
                    'revenue' => (float) $group->sum(fn (ServiceOrderItem $item): float => (float) $item->total_price),
                ];
            })
            ->sortByDesc('quantity')
            ->values();

        $topServices = $servicesByPerformance->take(5)->values();
        $lowServices = $servicesByPerformance->sortBy('quantity')->take(5)->values();

        $productsByPerformance = $productItems
            ->groupBy('product_id')
            ->filter(fn (Collection $group, $productId): bool => $productId !== null)
            ->map(function (Collection $group): array {
                /** @var ServiceOrderItem $first */
                $first = $group->first();

                return [
                    'product' => $first->product,
                    'quantity' => (int) $group->sum('quantity'),
                    'revenue' => (float) $group->sum(fn (ServiceOrderItem $item): float => (float) $item->total_price),
                ];
            })
            ->sortByDesc('quantity')
            ->values();

        $topProducts = $productsByPerformance->take(5)->values();
        $lowStockProducts = Product::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->where('stock_quantity', '<=', 5)
            ->orderBy('stock_quantity')
            ->limit(5)
            ->get(['id', 'name', 'stock_quantity']);

        $insights = $this->buildInsights([
            'clients_at_risk' => $clientsAtRisk,
            'top_services' => $topServices,
            'upsell_rate' => $upsellRate,
            'professional_ranking' => $professionalRanking,
            'low_stock_products' => $lowStockProducts,
        ]);

        return [
            'filters' => [
                'from' => $from,
                'to' => $to,
                'period' => $period,
                'period_label' => $periodLabel,
                'selected_user_id' => $selectedUserId,
            ],
            'summary' => [
                'revenue_total' => $revenueTotal,
                'service_revenue' => $serviceRevenue,
                'product_revenue' => $productRevenue,
                'completed_appointments' => $completedAppointments,
                'ticket_average' => $ticketAverage,
                'clients_attended' => $clientsAttended,
                'recurring_clients' => $recurringClients,
                'return_rate' => $returnRate,
                'upsell_rate' => $upsellRate,
                'services_sold' => $servicesSold,
                'products_sold' => $productsSold,
                'previous_revenue' => $previousRevenue,
                'growth_rate' => $this->growthRate($revenueTotal, $previousRevenue),
            ],
            'revenue' => [
                'by_day' => $revenueByDay,
            ],
            'clients' => [
                'new' => $newClients,
                'recurring' => $recurringClients,
                'inactive' => $inactiveClients,
                'top' => $topClients,
                'at_risk' => $clientsAtRisk,
            ],
            'professionals' => [
                'ranking' => $professionalRanking,
            ],
            'services' => [
                'top' => $topServices,
                'low' => $lowServices,
            ],
            'products' => [
                'top' => $topProducts,
                'low_stock' => $lowStockProducts,
            ],
            'insights' => $insights,
            'has_data' => $orders->isNotEmpty(),
        ];
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    private function previousRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $seconds = max(1, $to->diffInSeconds($from) + 1);
        $previousTo = $from->subSecond();
        $previousFrom = $previousTo->subSeconds($seconds - 1);

        return [
            'from' => $previousFrom,
            'to' => $previousTo,
        ];
    }

    private function growthRate(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * @param  array{clients_at_risk:Collection<int,Client>, top_services:Collection<int,array{service:mixed,quantity:int,revenue:float}>, upsell_rate:float, professional_ranking:Collection<int,array<string,mixed>>, low_stock_products:Collection<int,Product>}  $context
     * @return list<string>
     */
    private function buildInsights(array $context): array
    {
        $insights = [];

        if ($context['clients_at_risk']->isNotEmpty()) {
            $insights[] = sprintf(
                '%d cliente(s) sem retorno há 30+ dias.',
                $context['clients_at_risk']->count()
            );
        }

        $bestService = $context['top_services']->first();
        if ($bestService && $bestService['service']) {
            $insights[] = sprintf(
                'Serviço mais vendido: %s (%d venda(s)).',
                $bestService['service']->name,
                $bestService['quantity']
            );
        }

        $insights[] = sprintf(
            'Taxa de upsell de serviços: %s%%.',
            number_format($context['upsell_rate'], 2, ',', '.')
        );

        $bestProfessional = $context['professional_ranking']->first();
        if ($bestProfessional && $bestProfessional['professional']) {
            $insights[] = sprintf(
                'Profissional com maior receita: %s (R$ %s).',
                $bestProfessional['professional']->name,
                number_format((float) $bestProfessional['revenue'], 2, ',', '.')
            );
        }

        if ($context['low_stock_products']->isNotEmpty()) {
            $insights[] = sprintf(
                '%d produto(s) com estoque baixo.',
                $context['low_stock_products']->count()
            );
        }

        return $insights;
    }
}
