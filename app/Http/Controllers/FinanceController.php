<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashOutflowRequest;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CommissionSettlement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductSaleItem;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\DashboardPerformanceService;
use App\Support\BrazilianCurrency;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FinanceController extends Controller
{
    /**
     * Display the finance dashboard.
     */
    public function index(Request $request): View
    {
        [$from, $to, $selectedUserId, $users, $payments] = $this->baseData($request);

        $grossAmount = (float) $payments->sum(fn (Payment $payment) => (float) $payment->gross_amount);
        $commissionAmount = (float) $payments->sum(fn (Payment $payment) => (float) $payment->commission_amount);
        $netAmount = (float) $payments->sum(fn (Payment $payment) => (float) $payment->net_amount);
        $appointmentsCount = $payments->count();
        $averageTicket = $appointmentsCount > 0 ? round($grossAmount / $appointmentsCount, 2) : 0.0;
        $paymentMethodTotals = $payments
            ->groupBy('payment_method')
            ->map(fn (Collection $group): float => (float) $group->sum(fn (Payment $payment) => (float) $payment->gross_amount));

        $recentPayments = $payments
            ->sortByDesc(fn (Payment $payment) => $payment->paid_at)
            ->take(10)
            ->values();

        return view('finance.index', [
            'from' => $from,
            'to' => $to,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'grossAmount' => $grossAmount,
            'commissionAmount' => $commissionAmount,
            'netAmount' => $netAmount,
            'appointmentsCount' => $appointmentsCount,
            'averageTicket' => $averageTicket,
            'paymentMethodTotals' => $paymentMethodTotals,
            'recentPayments' => $recentPayments,
            'page' => 'dashboard',
        ]);
    }

    /**
     * Display production by professional.
     */
    public function production(Request $request): View
    {
        [$from, $to, $selectedUserId, $users, $payments] = $this->baseData($request);

        $rows = $payments
            ->groupBy('user_id')
            ->map(function (Collection $group) {
                /** @var Payment $first */
                $first = $group->first();
                $gross = (float) $group->sum(fn (Payment $payment) => (float) $payment->gross_amount);
                $commission = (float) $group->sum(fn (Payment $payment) => (float) $payment->commission_amount);
                $net = (float) $group->sum(fn (Payment $payment) => (float) $payment->net_amount);

                return [
                    'user' => $first->user,
                    'completed_appointments' => $group->count(),
                    'gross_amount' => $gross,
                    'commission_amount' => $commission,
                    'net_amount' => $net,
                    'average_ticket' => $group->count() > 0 ? round($gross / $group->count(), 2) : 0.0,
                ];
            })
            ->sortBy(fn (array $row) => $row['user']->name)
            ->values();

        return view('finance.production', [
            'rows' => $rows,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'from' => $from,
            'to' => $to,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'page' => 'production',
        ]);
    }

    /**
     * Display commissions by professional.
     */
    public function commissions(Request $request): View
    {
        [$from, $to, $selectedUserId, $users, $payments] = $this->baseData($request);
        $companyId = $request->user()->company_id;

        $commissionPayments = $payments
            ->filter(fn (Payment $payment): bool => (float) $payment->commission_amount > 0)
            ->values();

        $settledPaymentIds = CommissionSettlement::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->when($selectedUserId !== null, fn ($query) => $query->where('user_id', $selectedUserId))
            ->with('payments:id')
            ->get()
            ->flatMap(fn (CommissionSettlement $settlement) => $settlement->payments->pluck('id'))
            ->unique()
            ->values();

        $pendingPayments = $commissionPayments
            ->reject(fn (Payment $payment) => $settledPaymentIds->contains($payment->id))
            ->values();

        $settlements = CommissionSettlement::query()
            ->with(['user', 'creator', 'payments.client', 'payments.service'])
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->when($selectedUserId !== null, fn ($query) => $query->where('user_id', $selectedUserId))
            ->orderByDesc('paid_at')
            ->get();

        $rows = $users
            ->filter(fn (User $user): bool => $selectedUserId === null || $user->id === $selectedUserId)
            ->map(function (User $user) use ($commissionPayments, $pendingPayments, $settlements): array {
                $userPayments = $commissionPayments->where('user_id', $user->id);
                $userPendingPayments = $pendingPayments->where('user_id', $user->id);
                $userSettlements = $settlements->where('user_id', $user->id);
                $grossAmount = (float) $userPayments->sum(fn (Payment $payment) => (float) $payment->gross_amount);
                $pendingCommission = (float) $userPendingPayments->sum(fn (Payment $payment) => (float) $payment->commission_amount);
                $paidCommission = (float) $userSettlements->sum(fn (CommissionSettlement $settlement) => (float) $settlement->commission_amount);
                $firstPayment = $userPayments->first();

                return [
                    'user' => $user,
                    'commission_type' => $firstPayment?->commission_type ?? $user->commission_type,
                    'commission_rate' => $firstPayment?->commission_rate ?? ($user->commission_type === 'percent' ? $user->commission_value : null),
                    'services_count' => $userPayments->count(),
                    'gross_amount' => $grossAmount,
                    'pending_commission_amount' => $pendingCommission,
                    'paid_commission_amount' => $paidCommission,
                    'effective_rate' => $grossAmount > 0
                        ? round((((float) $userPayments->sum(fn (Payment $payment) => (float) $payment->commission_amount)) / $grossAmount) * 100, 2)
                        : 0.0,
                    'can_settle' => $pendingCommission > 0,
                ];
            })
            ->filter(fn (array $row): bool => $row['services_count'] > 0 || $row['paid_commission_amount'] > 0)
            ->values();

        $recentSettlements = CommissionSettlement::query()
            ->with(['user', 'creator'])
            ->where('company_id', $companyId)
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderByDesc('paid_at')
            ->limit(12)
            ->get();

        return view('finance.commissions', [
            'rows' => $rows,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'from' => $from,
            'to' => $to,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'recentSettlements' => $recentSettlements,
            'page' => 'commissions',
        ]);
    }

    /**
     * Display daily cash register operations.
     */
    public function cash(Request $request, CashRegisterService $cashRegisterService): View
    {
        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->string('date'))
            : CarbonImmutable::today();

        $register = $cashRegisterService->registerForDate($request->user()->company_id, $date);

        return view('finance.cash', [
            'date' => $date,
            'register' => $register?->load('movements'),
            'page' => 'cash',
        ]);
    }

    /**
     * Open daily cash register.
     */
    public function openCash(Request $request, CashRegisterService $cashRegisterService): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->merge([
            'opening_amount' => BrazilianCurrency::normalize($request->input('opening_amount')),
        ]);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $date = CarbonImmutable::parse($data['date']);

        $cashRegisterService->open(
            $request->user(),
            (float) $data['opening_amount'],
            $data['notes'] ?? null,
            $date,
        );

        return redirect()
            ->route('finance.cash', ['date' => $date->format('Y-m-d')])
            ->with('status', 'cash-opened');
    }

    /**
     * Close daily cash register.
     */
    public function closeCash(Request $request, CashRegisterService $cashRegisterService): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->merge([
            'closing_amount' => BrazilianCurrency::normalize($request->input('closing_amount')),
        ]);

        $data = $request->validate([
            'cash_register_id' => ['required', 'integer'],
            'closing_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $register = CashRegister::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($data['cash_register_id']);

        $cashRegisterService->close(
            $register,
            $request->user(),
            isset($data['closing_amount']) ? (float) $data['closing_amount'] : null,
            $data['notes'] ?? null,
        );

        return redirect()
            ->route('finance.cash', ['date' => $register->date->format('Y-m-d')])
            ->with('status', 'cash-closed');
    }

    /**
     * Register a manual cash outflow on the open register.
     */
    public function registerCashOutflow(StoreCashOutflowRequest $request, CashRegisterService $cashRegisterService): RedirectResponse
    {
        $data = $request->validated();
        $register = CashRegister::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($data['cash_register_id']);

        $occurredAt = CarbonImmutable::parse($register->date->format('Y-m-d'))
            ->setTimeFromTimeString(now()->format('H:i:s'));

        $cashRegisterService->recordManualOutflow(
            $register,
            $request->user(),
            (float) $data['amount'],
            $data['category'],
            $data['description'] ?? null,
            $data['payment_method'] ?? null,
            $occurredAt,
        );

        return redirect()
            ->route('finance.cash', ['date' => $register->date->format('Y-m-d')])
            ->with('status', 'cash-outflow-registered');
    }

    /**
     * Display consolidated financial report.
     */
    public function report(Request $request): View
    {
        [$from, $to, $selectedUserId, $users] = $this->baseFilters($request);
        $companyId = $request->user()->company_id;

        $payments = Payment::query()
            ->with(['client', 'user', 'service'])
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [$from, $to])
            ->when($selectedUserId !== null, fn ($query) => $query->where('user_id', $selectedUserId))
            ->get();

        $productSales = ProductSale::query()
            ->with(['client', 'user', 'items.product'])
            ->where('company_id', $companyId)
            ->whereBetween('sold_at', [$from, $to])
            ->when($selectedUserId !== null, fn ($query) => $query->where('user_id', $selectedUserId))
            ->get();

        $settlements = CommissionSettlement::query()
            ->with('user')
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [$from, $to])
            ->when($selectedUserId !== null, fn ($query) => $query->where('user_id', $selectedUserId))
            ->get();

        $movements = CashMovement::query()
            ->with('cashRegister')
            ->where('company_id', $companyId)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->get();

        $cashRegisters = CashRegister::query()
            ->with('movements')
            ->where('company_id', $companyId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('date')
            ->get();

        $serviceRevenue = (float) $payments->sum(fn (Payment $payment) => (float) $payment->gross_amount);
        $productRevenue = (float) $productSales->sum(fn (ProductSale $sale) => (float) $sale->gross_amount);
        $settlementsAmount = (float) $settlements->sum(fn (CommissionSettlement $settlement) => (float) $settlement->commission_amount);
        $cashInflows = (float) $movements->where('type', CashMovement::TYPE_INFLOW)->sum('amount');
        $cashOutflows = (float) $movements->where('type', CashMovement::TYPE_OUTFLOW)->sum('amount');

        return view('finance.report', [
            'from' => $from,
            'to' => $to,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'serviceRevenue' => $serviceRevenue,
            'productRevenue' => $productRevenue,
            'totalRevenue' => $serviceRevenue + $productRevenue,
            'settlementsAmount' => $settlementsAmount,
            'cashInflows' => $cashInflows,
            'cashOutflows' => $cashOutflows,
            'payments' => $payments,
            'productSales' => $productSales,
            'settlements' => $settlements,
            'cashRegisters' => $cashRegisters,
            'page' => 'report',
        ]);
    }

    /**
     * Display professional performance dashboard.
     */
    public function performance(Request $request, DashboardPerformanceService $dashboardPerformanceService): View
    {
        [$from, $to, $selectedUserId, $users] = $this->baseFilters($request);

        if ($request->string('period')->value() !== '') {
            [$from, $to] = $this->resolvePeriodRange(
                $request->string('period')->value(),
                $request->filled('from') ? CarbonImmutable::parse($request->string('from'))->startOfDay() : $from,
                $request->filled('to') ? CarbonImmutable::parse($request->string('to'))->endOfDay() : $to,
            );
        }

        $period = $request->string('period')->value() ?: 'custom';
        $periodLabel = $this->periodLabel($period);
        $metrics = $dashboardPerformanceService->build($request->user()->company_id, [
            'from' => $from,
            'to' => $to,
            'selected_user_id' => $selectedUserId,
            'period' => $period,
            'period_label' => $periodLabel,
        ]);

        return view('finance.performance', [
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'metrics' => $metrics,
            'page' => 'performance',
        ]);
    }

    /**
     * Display product commissions per seller, with filters and grouping options.
     */
    public function productCommissions(Request $request): View
    {
        [$from, $to, $selectedUserId, $users] = $this->baseFilters($request);
        $companyId = (int) $request->user()->company_id;
        $selectedProductId = $request->integer('product_id') ?: null;
        $groupBy = in_array($request->string('group_by')->value(), ['seller', 'product'], true)
            ? $request->string('group_by')->value()
            : 'detail';

        $items = ProductSaleItem::query()
            ->with(['product', 'seller', 'sale.client'])
            ->select('product_sale_items.*')
            ->join('product_sales', 'product_sales.id', '=', 'product_sale_items.product_sale_id')
            ->where('product_sales.company_id', $companyId)
            ->whereBetween('product_sales.sold_at', [$from, $to])
            ->when($selectedUserId !== null, fn ($query) => $query->where('product_sale_items.seller_id', $selectedUserId))
            ->when($selectedProductId !== null, fn ($query) => $query->where('product_sale_items.product_id', $selectedProductId))
            ->orderByDesc('product_sales.sold_at')
            ->orderBy('product_sale_items.id')
            ->get();

        $commissionItems = $items->filter(fn (ProductSaleItem $item): bool => (float) $item->commission_amount > 0)->values();

        $totalGrossCommissioned = (float) $commissionItems->sum(fn (ProductSaleItem $item) => (float) $item->total_price);
        $totalCommission = (float) $commissionItems->sum(fn (ProductSaleItem $item) => (float) $item->commission_amount);
        $totalQuantity = (int) $commissionItems->sum(fn (ProductSaleItem $item) => (int) $item->quantity);

        $byUserTotals = $commissionItems
            ->groupBy('seller_id')
            ->map(function (\Illuminate\Support\Collection $group): array {
                $first = $group->first();

                return [
                    'seller' => $first?->seller,
                    'quantity' => (int) $group->sum(fn (ProductSaleItem $item) => (int) $item->quantity),
                    'gross' => (float) $group->sum(fn (ProductSaleItem $item) => (float) $item->total_price),
                    'commission' => (float) $group->sum(fn (ProductSaleItem $item) => (float) $item->commission_amount),
                ];
            })
            ->values();

        $topSellerRow = $byUserTotals->sortByDesc('commission')->first();

        $byProductTotals = $commissionItems
            ->groupBy('product_id')
            ->map(function (\Illuminate\Support\Collection $group): array {
                $first = $group->first();

                return [
                    'product' => $first?->product,
                    'quantity' => (int) $group->sum(fn (ProductSaleItem $item) => (int) $item->quantity),
                    'gross' => (float) $group->sum(fn (ProductSaleItem $item) => (float) $item->total_price),
                    'commission' => (float) $group->sum(fn (ProductSaleItem $item) => (float) $item->commission_amount),
                ];
            })
            ->sortByDesc('commission')
            ->values();

        $products = Product::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('finance.product-commissions', [
            'from' => $from,
            'to' => $to,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'products' => $products,
            'selectedProductId' => $selectedProductId,
            'groupBy' => $groupBy,
            'items' => $commissionItems,
            'totalGrossCommissioned' => $totalGrossCommissioned,
            'totalCommission' => $totalCommission,
            'totalQuantity' => $totalQuantity,
            'byUserTotals' => $byUserTotals->sortByDesc('commission')->values(),
            'byProductTotals' => $byProductTotals,
            'topSeller' => $topSellerRow,
            'page' => 'product-commissions',
        ]);
    }

    public function productSalesReport(Request $request): View
    {
        [$from, $to, $selectedUserId, $users] = $this->baseFilters($request);
        $companyId = (int) $request->user()->company_id;
        $selectedProductId = $request->integer('product_id') ?: null;
        $selectedCategory = trim((string) $request->input('category', ''));
        $selectedPaymentMethod = trim((string) $request->input('payment_method', ''));

        $items = ProductSaleItem::query()
            ->with(['product', 'seller', 'sale'])
            ->select('product_sale_items.*')
            ->join('product_sales', 'product_sales.id', '=', 'product_sale_items.product_sale_id')
            ->leftJoin('service_orders', 'service_orders.id', '=', 'product_sales.service_order_id')
            ->where('product_sales.company_id', $companyId)
            ->whereBetween('product_sales.sold_at', [$from, $to])
            ->where(function ($query): void {
                $query->whereNull('product_sales.service_order_id')
                    ->orWhere('service_orders.status', ServiceOrder::STATUS_PAID);
            })
            ->when($selectedUserId !== null, fn ($query) => $query->where('product_sale_items.seller_id', $selectedUserId))
            ->when($selectedProductId !== null, fn ($query) => $query->where('product_sale_items.product_id', $selectedProductId))
            ->when($selectedPaymentMethod !== '', fn ($query) => $query->where('product_sales.payment_method', $selectedPaymentMethod))
            ->when($selectedCategory !== '', fn ($query) => $query->join('products', 'products.id', '=', 'product_sale_items.product_id')->where('products.category', $selectedCategory))
            ->orderByDesc('product_sales.sold_at')
            ->get();

        $saleGrossById = $items->groupBy('product_sale_id')->map(fn (Collection $group): float => (float) $group->sum(fn (ProductSaleItem $item) => (float) $item->total_price));
        $rows = $items->map(function (ProductSaleItem $item) use ($saleGrossById): array {
            $gross = round((float) $item->total_price, 2);
            $saleGross = max(0.01, (float) ($saleGrossById[$item->product_sale_id] ?? $gross));
            $saleNet = round((float) ($item->sale?->gross_amount ?? $gross), 2);
            $net = round($gross * ($saleNet / $saleGross), 2);

            return [
                'item' => $item,
                'gross' => $gross,
                'discount' => max(0, round($gross - $net, 2)),
                'net' => $net,
            ];
        });

        $products = Product::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'category']);
        $categories = $products->pluck('category')->filter()->unique()->sort()->values();
        $ranking = $rows->groupBy(fn (array $row) => $row['item']->product_id)
            ->map(function (Collection $group): array {
                $first = $group->first()['item'];

                return [
                    'product' => $first->product,
                    'quantity' => (int) $group->sum(fn (array $row) => (int) $row['item']->quantity),
                    'net' => (float) $group->sum('net'),
                ];
            })
            ->sortByDesc('quantity')
            ->values();

        return view('finance.product-sales', [
            'from' => $from,
            'to' => $to,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'products' => $products,
            'categories' => $categories,
            'paymentMethods' => Payment::paymentMethodOptions(),
            'selectedProductId' => $selectedProductId,
            'selectedCategory' => $selectedCategory,
            'selectedPaymentMethod' => $selectedPaymentMethod,
            'rows' => $rows,
            'totalQuantity' => (int) $items->sum('quantity'),
            'totalGross' => (float) $rows->sum('gross'),
            'totalDiscount' => (float) $rows->sum('discount'),
            'totalNet' => (float) $rows->sum('net'),
            'ranking' => $ranking,
            'page' => 'product-sales',
        ]);
    }

    /**
     * Revenue and volume by service line (paid orders only).
     */
    public function serviceReport(Request $request): View
    {
        [$from, $to, $selectedUserId, $users] = $this->baseFilters($request);
        $companyId = (int) $request->user()->company_id;

        $totalsQuery = $this->serviceReportItemsQuery($companyId, $from, $to, $selectedUserId);

        $totalQuantity = (float) (clone $totalsQuery)->sum('service_order_items.quantity');
        $totalGross = (float) (clone $totalsQuery)->sum('service_order_items.total_price');

        $rows = (clone $totalsQuery)
            ->select('services.id as service_row_id', 'services.name as service_row_name')
            ->selectRaw('SUM(service_order_items.quantity) as qty_sold')
            ->selectRaw('SUM(service_order_items.total_price) as gross')
            ->groupBy('services.id', 'services.name')
            ->orderBy('services.name')
            ->get()
            ->map(function ($row) use ($totalGross): array {
                $qty = (float) $row->qty_sold;
                $gross = (float) $row->gross;
                $ticket = $qty > 0 ? round($gross / $qty, 2) : 0.0;

                return [
                    'name' => (string) $row->service_row_name,
                    'quantity' => $qty,
                    'gross' => $gross,
                    'ticket' => $ticket,
                    'pct' => $totalGross > 0 ? round(($gross / $totalGross) * 100, 2) : 0.0,
                ];
            });

        return view('finance.service-report', [
            'from' => $from,
            'to' => $to,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'totalQuantity' => $totalQuantity,
            'totalGross' => $totalGross,
            'averageTicket' => $totalQuantity > 0 ? round($totalGross / $totalQuantity, 2) : 0.0,
            'rows' => $rows,
            'page' => 'service-report',
        ]);
    }

    /**
     * Build the shared finance dataset with filters and company isolation.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null, 3: \Illuminate\Database\Eloquent\Collection<int, User>, 4: Collection<int, Payment>}
     */
    private function baseData(Request $request): array
    {
        [$from, $to, $selectedUserId, $users] = $this->baseFilters($request);

        $paymentsQuery = Payment::query()
            ->with(['user', 'client', 'service', 'appointment'])
            ->where('company_id', $request->user()->company_id)
            ->whereBetween('paid_at', [$from, $to]);

        if ($selectedUserId !== null) {
            $paymentsQuery->where('user_id', $selectedUserId);
        }

        $payments = $paymentsQuery
            ->orderBy('paid_at')
            ->get();

        return [$from, $to, $selectedUserId, $users, $payments];
    }

    /**
     * Shared finance filters.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null, 3: \Illuminate\Database\Eloquent\Collection<int, User>}
     */
    private function baseFilters(Request $request): array
    {
        $companyId = $request->user()->company_id;
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from'))->startOfDay()
            : CarbonImmutable::today()->startOfMonth();
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to'))->endOfDay()
            : CarbonImmutable::today()->endOfDay();

        $users = User::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $selectedUserId = $request->user()->isAdmin()
            ? ($request->integer('user_id') ?: null)
            : $request->user()->id;

        return [$from, $to, $selectedUserId, $users];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriodRange(string $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($period) {
            'today' => [CarbonImmutable::today()->startOfDay(), CarbonImmutable::today()->endOfDay()],
            '7d' => [CarbonImmutable::today()->subDays(6)->startOfDay(), CarbonImmutable::today()->endOfDay()],
            '30d' => [CarbonImmutable::today()->subDays(29)->startOfDay(), CarbonImmutable::today()->endOfDay()],
            'this_month' => [CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfDay()],
            'last_month' => [
                CarbonImmutable::today()->subMonthNoOverflow()->startOfMonth(),
                CarbonImmutable::today()->subMonthNoOverflow()->endOfMonth(),
            ],
            default => [$from, $to],
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'today' => 'Hoje',
            '7d' => 'Últimos 7 dias',
            '30d' => 'Últimos 30 dias',
            'this_month' => 'Este mês',
            'last_month' => 'Mês anterior',
            default => 'Período personalizado',
        };
    }

    /**
     * @return Builder<ServiceOrderItem>
     */
    private function serviceReportItemsQuery(int $companyId, CarbonImmutable $from, CarbonImmutable $to, ?int $professionalId): Builder
    {
        $query = ServiceOrderItem::query()
            ->join('service_orders', 'service_orders.id', '=', 'service_order_items.service_order_id')
            ->leftJoin('appointments', 'appointments.id', '=', 'service_orders.appointment_id')
            ->join('services', 'services.id', '=', 'service_order_items.service_id')
            ->where('service_order_items.type', ServiceOrderItem::TYPE_SERVICE)
            ->where('service_orders.company_id', $companyId)
            ->where('service_orders.status', ServiceOrder::STATUS_PAID)
            ->whereNotNull('service_order_items.service_id')
            ->whereBetween('service_orders.closed_at', [$from, $to]);

        if ($professionalId !== null) {
            $query->whereRaw(
                'COALESCE(service_order_items.professional_id, service_orders.professional_id, appointments.user_id) = ?',
                [$professionalId]
            );
        }

        return $query;
    }
}
