<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Requests\StoreStockCountRequest;
use App\Models\DailyStockCheck;
use App\Models\DailyStockCheckItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductSaleItem;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $lowStock = $this->stockService->getLowStockProducts($companyId);
        $todayOut = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('direction', StockMovement::DIRECTION_OUT)
            ->whereDate('movement_date', now()->toDateString())
            ->sum('quantity');
        $todayIn = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('direction', StockMovement::DIRECTION_IN)
            ->whereDate('movement_date', now()->toDateString())
            ->sum('quantity');

        return view('stock.index', compact('lowStock', 'todayOut', 'todayIn'));
    }

    public function diary(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $products = $this->products($companyId)->get(['id', 'name']);
        $users = DB::table('users')->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']);

        $movements = StockMovement::query()
            ->with(['product:id,name,unit', 'user:id,name'])
            ->where('company_id', $companyId)
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->date('date_to')))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $summaryQuery = StockMovement::query()
            ->where('company_id', $companyId)
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->date('date_to')));

        $summary = [
            'in' => (clone $summaryQuery)->where('direction', StockMovement::DIRECTION_IN)->sum('quantity'),
            'out' => (clone $summaryQuery)->where('direction', StockMovement::DIRECTION_OUT)->sum('quantity'),
            'sold' => (clone $summaryQuery)->where('type', StockMovement::TYPE_SALE)->sum('quantity'),
        ];

        return view('stock.diary', compact('movements', 'products', 'users', 'summary'));
    }

    public function createAdjustment(Request $request): View
    {
        $products = $this->products((int) $request->user()->company_id)->get();
        $dailyStockCheck = null;
        $stockCount = null;

        if ($request->filled('daily_stock_check_id')) {
            $dailyStockCheck = DailyStockCheck::query()
                ->with(['items.product:id,name,unit'])
                ->where('company_id', $request->user()->company_id)
                ->whereKey($request->integer('daily_stock_check_id'))
                ->first();
        }

        if ($request->filled('stock_count_id')) {
            $stockCount = StockCount::query()
                ->with(['items.product:id,name,unit'])
                ->where('company_id', $request->user()->company_id)
                ->whereKey($request->integer('stock_count_id'))
                ->first();
        }

        return view('stock.adjustments.create', compact('products', 'dailyStockCheck', 'stockCount'));
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $product = $this->products((int) $request->user()->company_id)->findOrFail($data['product_id']);
        $reason = $data['reason'] === 'correction' || $data['reason'] === 'other'
            ? StockMovement::TYPE_MANUAL_ADJUSTMENT
            : $data['reason'];
        $sourceType = null;
        $sourceId = null;

        if (! empty($data['daily_stock_check_id'])) {
            $sourceType = DailyStockCheck::class;
            $sourceId = (int) $data['daily_stock_check_id'];
        } elseif (! empty($data['stock_count_id'])) {
            $sourceType = StockCount::class;
            $sourceId = (int) $data['stock_count_id'];
        }

        $movement = $this->stockService->manualMovement(
            $product,
            $data['direction'],
            (float) $data['quantity'],
            $reason,
            isset($data['unit_cost']) && $data['unit_cost'] !== null ? (float) $data['unit_cost'] : null,
            $data['notes'] ?? null,
            $request->user(),
            $sourceType,
            $sourceId,
        );

        if ($sourceType === DailyStockCheck::class && $sourceId) {
            DailyStockCheckItem::query()
                ->whereHas('dailyStockCheck', fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->where('daily_stock_check_id', $sourceId)
                ->where('product_id', $product->id)
                ->whereNull('adjustment_movement_id')
                ->whereNotNull('difference_quantity')
                ->where('difference_quantity', '!=', 0)
                ->update([
                    'adjustment_movement_id' => $movement->id,
                    'adjusted_at' => now(),
                    'adjusted_by' => $request->user()->id,
                ]);
        }

        if ($sourceType === StockCount::class && $sourceId) {
            \App\Models\StockCountItem::query()
                ->whereHas('stockCount', fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->where('stock_count_id', $sourceId)
                ->where('product_id', $product->id)
                ->whereNull('adjustment_movement_id')
                ->whereNotNull('difference_quantity')
                ->where('difference_quantity', '!=', 0)
                ->update([
                    'adjustment_movement_id' => $movement->id,
                    'adjusted_at' => now(),
                    'adjusted_by' => $request->user()->id,
                ]);
        }

        return redirect()->route('stock.diary')->with('status', 'Movimentacao de estoque registrada.');
    }

    public function counts(Request $request): View
    {
        $counts = StockCount::query()
            ->with('user:id,name')
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('count_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('stock.counts.index', compact('counts'));
    }

    public function createCount(Request $request): View
    {
        $products = $this->products((int) $request->user()->company_id)
            ->where('track_stock', true)
            ->get();

        return view('stock.counts.create', compact('products'));
    }

    public function storeCount(StoreStockCountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $companyId = (int) $request->user()->company_id;
        $productIds = collect($data['items'])->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
        $products = $this->products($companyId)->where('track_stock', true)->whereIn('id', $productIds)->get()->keyBy('id');

        if ($products->count() !== count(array_unique($productIds))) {
            return back()->withErrors(['items' => 'Um ou mais produtos nao pertencem a esta empresa.'])->withInput();
        }

        $stockCount = DB::transaction(function () use ($data, $request, $products): StockCount {
            $count = StockCount::query()->create([
                'company_id' => $request->user()->company_id,
                'user_id' => $request->user()->id,
                'status' => StockCount::STATUS_DRAFT,
                'count_date' => $data['count_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $product = $products->get((int) $row['product_id']);
                $count->items()->create([
                    'product_id' => $product->id,
                    'counted_quantity' => round((float) $row['counted_quantity'], 2),
                    'unit_cost' => $product->cost_price,
                ]);
            }

            return $count;
        });

        return redirect()->route('stock.counts.show', $stockCount);
    }

    public function showCount(Request $request, StockCount $stockCount): View
    {
        abort_unless($stockCount->company_id === $request->user()->company_id, 404);

        $stockCount->load(['items.product:id,name,unit,stock_quantity,cost_price', 'items.adjustmentMovement', 'items.adjustedBy:id,name', 'user:id,name']);

        return view('stock.counts.show', compact('stockCount'));
    }

    public function completeCount(Request $request, StockCount $stockCount): RedirectResponse
    {
        abort_unless($stockCount->company_id === $request->user()->company_id, 404);

        if ($stockCount->isCompleted()) {
            return redirect()->route('stock.counts.show', $stockCount)->with('status', 'Auditoria geral ja finalizada.');
        }

        DB::transaction(function () use ($stockCount): void {
            $stockCount->load('items.product');

            foreach ($stockCount->items as $item) {
                $product = Product::query()
                    ->where('company_id', $stockCount->company_id)
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expected = round((float) $product->stock_quantity, 2);
                $counted = round((float) $item->counted_quantity, 2);
                $difference = round($counted - $expected, 2);
                $unitCost = $product->cost_price !== null ? round((float) $product->cost_price, 2) : 0.0;

                $item->update([
                    'expected_quantity' => $expected,
                    'difference_quantity' => $difference,
                    'unit_cost' => $unitCost,
                    'difference_value' => round(abs($difference) * $unitCost, 2),
                ]);
            }

            $stockCount->update([
                'status' => StockCount::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('stock.counts.show', $stockCount)->with('status', 'Auditoria geral finalizada. Divergencias registradas para ajuste autorizado.');
    }

    public function dailyChecks(Request $request): View
    {
        $checks = DailyStockCheck::query()
            ->with('user:id,name')
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('reference_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('stock.daily-checks.index', compact('checks'));
    }

    public function createDailyCheck(): View
    {
        $defaultReferenceDate = now()->subDay()->toDateString();

        return view('stock.daily-checks.create', compact('defaultReferenceDate'));
    }

    public function generateDailyCheck(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $companyId = (int) $request->user()->company_id;
        $referenceDate = CarbonImmutable::parse($data['reference_date'] ?? now()->subDay()->toDateString())->toDateString();

        $activeExists = DailyStockCheck::query()
            ->where('company_id', $companyId)
            ->whereDate('reference_date', $referenceDate)
            ->whereIn('status', [DailyStockCheck::STATUS_DRAFT, DailyStockCheck::STATUS_COMPLETED])
            ->exists();

        if ($activeExists) {
            return back()->withErrors(['reference_date' => 'Ja existe uma conferencia diaria ativa para esta data.'])->withInput();
        }

        $check = DB::transaction(function () use ($request, $companyId, $referenceDate, $data): DailyStockCheck {
            $check = DailyStockCheck::query()->create([
                'company_id' => $companyId,
                'user_id' => $request->user()->id,
                'reference_date' => $referenceDate,
                'status' => DailyStockCheck::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
            ]);

            $products = $this->products($companyId)
                ->where('active', true)
                ->where('track_stock', true)
                ->get();
            $context = $this->dailyCheckContext($companyId, $referenceDate);

            foreach ($products as $product) {
                $check->items()->create([
                    'product_id' => $product->id,
                    'sold_quantity' => round((float) ($context['sold'][$product->id] ?? 0), 2),
                    'sale_stock_quantity' => round((float) ($context['sale_stock'][$product->id] ?? 0), 2),
                    'other_output_quantity' => round((float) ($context['other_output'][$product->id] ?? 0), 2),
                    'input_quantity' => round((float) ($context['input'][$product->id] ?? 0), 2),
                    'adjustment_quantity' => round((float) ($context['adjustment'][$product->id] ?? 0), 2),
                ]);
            }

            return $check;
        });

        return redirect()->route('stock.daily-checks.show', $check);
    }

    public function showDailyCheck(Request $request, DailyStockCheck $dailyStockCheck): View
    {
        abort_unless($dailyStockCheck->company_id === $request->user()->company_id, 404);

        $dailyStockCheck->load(['items.product:id,name,unit,stock_quantity,cost_price', 'items.adjustmentMovement', 'items.adjustedBy:id,name', 'user:id,name']);

        return view('stock.daily-checks.show', compact('dailyStockCheck'));
    }

    public function completeDailyCheck(Request $request, DailyStockCheck $dailyStockCheck): RedirectResponse
    {
        abort_unless($dailyStockCheck->company_id === $request->user()->company_id, 404);

        if ($dailyStockCheck->isCompleted()) {
            return redirect()->route('stock.daily-checks.show', $dailyStockCheck)->with('status', 'Conferencia diaria ja finalizada.');
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        DB::transaction(function () use ($dailyStockCheck, $data): void {
            $dailyStockCheck->load('items.product');
            $countedByItem = collect($data['items'])->keyBy(fn (array $row): int => (int) $row['id']);

            foreach ($dailyStockCheck->items as $item) {
                $row = $countedByItem->get($item->id);

                if (! $row) {
                    continue;
                }

                $product = Product::query()
                    ->where('company_id', $dailyStockCheck->company_id)
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expected = round((float) $product->stock_quantity, 2);
                $counted = round((float) $row['counted_quantity'], 2);
                $difference = round($counted - $expected, 2);
                $unitCost = $product->cost_price !== null ? round((float) $product->cost_price, 2) : 0.0;

                $item->update([
                    'expected_quantity' => $expected,
                    'counted_quantity' => $counted,
                    'difference_quantity' => $difference,
                    'unit_cost' => $unitCost,
                    'difference_value' => round(abs($difference) * $unitCost, 2),
                    'status' => abs($difference) <= 0.000001 ? DailyStockCheckItem::STATUS_OK : DailyStockCheckItem::STATUS_DIVERGENT,
                ]);
            }

            $dailyStockCheck->update([
                'status' => DailyStockCheck::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('stock.daily-checks.show', $dailyStockCheck)->with('status', 'Conferencia diaria finalizada. Divergencias enviadas para auditoria.');
    }

    public function cancelDailyCheck(Request $request, DailyStockCheck $dailyStockCheck): RedirectResponse
    {
        abort_unless($dailyStockCheck->company_id === $request->user()->company_id, 404);

        if (! $dailyStockCheck->isCompleted()) {
            $dailyStockCheck->update(['status' => DailyStockCheck::STATUS_CANCELLED]);
        }

        return redirect()->route('stock.daily-checks.index')->with('status', 'Conferencia diaria cancelada.');
    }

    public function audit(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $products = $this->products($companyId)->get();
        $dateFrom = $request->filled('date_from') ? CarbonImmutable::parse($request->input('date_from'))->startOfDay() : now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? CarbonImmutable::parse($request->input('date_to'))->endOfDay() : now()->endOfDay();

        $pendingCounts = DB::table('stock_count_items')
            ->join('stock_counts', 'stock_counts.id', '=', 'stock_count_items.stock_count_id')
            ->where('stock_counts.company_id', $companyId)
            ->where('stock_counts.status', StockCount::STATUS_COMPLETED)
            ->whereNull('stock_count_items.adjustment_movement_id')
            ->whereNotNull('stock_count_items.difference_quantity')
            ->where('stock_count_items.difference_quantity', '!=', 0)
            ->select('stock_count_items.product_id', DB::raw('SUM(stock_count_items.difference_quantity) as pending_difference'), DB::raw('SUM(stock_count_items.difference_value) as pending_value'))
            ->groupBy('stock_count_items.product_id')
            ->get()
            ->keyBy('product_id');

        $appliedCounts = DB::table('stock_count_items')
            ->join('stock_counts', 'stock_counts.id', '=', 'stock_count_items.stock_count_id')
            ->where('stock_counts.company_id', $companyId)
            ->whereNotNull('stock_count_items.adjustment_movement_id')
            ->select('stock_count_items.product_id', DB::raw('SUM(stock_count_items.difference_quantity) as applied_difference'), DB::raw('SUM(stock_count_items.difference_value) as applied_value'))
            ->groupBy('stock_count_items.product_id')
            ->get()
            ->keyBy('product_id');

        $pendingDailyChecks = DB::table('daily_stock_check_items')
            ->join('daily_stock_checks', 'daily_stock_checks.id', '=', 'daily_stock_check_items.daily_stock_check_id')
            ->where('daily_stock_checks.company_id', $companyId)
            ->where('daily_stock_checks.status', DailyStockCheck::STATUS_COMPLETED)
            ->whereNull('daily_stock_check_items.adjustment_movement_id')
            ->whereNotNull('daily_stock_check_items.difference_quantity')
            ->where('daily_stock_check_items.difference_quantity', '!=', 0)
            ->select('daily_stock_check_items.product_id', DB::raw('SUM(daily_stock_check_items.difference_quantity) as pending_difference'), DB::raw('SUM(daily_stock_check_items.difference_value) as pending_value'))
            ->groupBy('daily_stock_check_items.product_id')
            ->get()
            ->keyBy('product_id');

        $appliedDailyChecks = DB::table('daily_stock_check_items')
            ->join('daily_stock_checks', 'daily_stock_checks.id', '=', 'daily_stock_check_items.daily_stock_check_id')
            ->where('daily_stock_checks.company_id', $companyId)
            ->whereNotNull('daily_stock_check_items.adjustment_movement_id')
            ->select('daily_stock_check_items.product_id', DB::raw('SUM(daily_stock_check_items.difference_quantity) as applied_difference'), DB::raw('SUM(daily_stock_check_items.difference_value) as applied_value'))
            ->groupBy('daily_stock_check_items.product_id')
            ->get()
            ->keyBy('product_id');

        $rows = $products
            ->when($request->filled('product_id'), fn ($items) => $items->where('id', $request->integer('product_id')))
            ->when($request->filled('category'), fn ($items) => $items->where('category', $request->input('category')))
            ->map(function (Product $product) use ($dateFrom, $dateTo, $pendingCounts, $appliedCounts, $pendingDailyChecks, $appliedDailyChecks) {
                $movements = $product->stockMovements()
                    ->whereBetween('movement_date', [$dateFrom, $dateTo])
                    ->get();
                $openingMovement = $product->stockMovements()
                    ->where('movement_date', '<', $dateFrom)
                    ->latest('movement_date')
                    ->first();
                $opening = $openingMovement ? (float) $openingMovement->balance_after : 0.0;
                $in = (float) $movements->where('direction', StockMovement::DIRECTION_IN)->sum('quantity');
                $saleOut = (float) $movements->where('type', StockMovement::TYPE_SALE)->sum('quantity');
                $otherOut = (float) $movements->where('direction', StockMovement::DIRECTION_OUT)->where('type', '!=', StockMovement::TYPE_SALE)->sum('quantity');
                $adjustments = (float) $movements->whereIn('type', [StockMovement::TYPE_ADJUSTMENT, StockMovement::TYPE_MANUAL_ADJUSTMENT, StockMovement::TYPE_BLIND_COUNT_ADJUSTMENT, StockMovement::TYPE_AUDIT_ADJUSTMENT, StockMovement::TYPE_BLIND_COUNT_ADJUSTMENT_APPLIED])->sum('quantity');
                $expected = round($opening + $in - $saleOut - $otherOut, 2);
                $current = round((float) $product->stock_quantity, 2);
                $difference = round($current - $expected, 2);
                $pending = $pendingCounts->get($product->id);
                $applied = $appliedCounts->get($product->id);
                $pendingDaily = $pendingDailyChecks->get($product->id);
                $appliedDaily = $appliedDailyChecks->get($product->id);

                return [
                    'product' => $product,
                    'opening' => $opening,
                    'in' => $in,
                    'sale_out' => $saleOut,
                    'other_out' => $otherOut,
                    'adjustments' => $adjustments,
                    'expected' => $expected,
                    'current' => $current,
                    'difference' => $difference,
                    'difference_value' => round($difference * (float) ($product->cost_price ?? 0), 2),
                    'pending_count_difference' => round((float) ($pending->pending_difference ?? 0), 2),
                    'pending_count_value' => round((float) ($pending->pending_value ?? 0), 2),
                    'applied_count_difference' => round((float) ($applied->applied_difference ?? 0), 2),
                    'applied_count_value' => round((float) ($applied->applied_value ?? 0), 2),
                    'pending_daily_difference' => round((float) ($pendingDaily->pending_difference ?? 0), 2),
                    'pending_daily_value' => round((float) ($pendingDaily->pending_value ?? 0), 2),
                    'applied_daily_difference' => round((float) ($appliedDaily->applied_difference ?? 0), 2),
                    'applied_daily_value' => round((float) ($appliedDaily->applied_value ?? 0), 2),
                ];
            })
            ->when($request->boolean('only_divergent'), fn ($items) => $items->filter(fn ($row) => abs($row['difference']) > 0.000001))
            ->when($request->boolean('low_stock'), fn ($items) => $items->filter(fn ($row) => $row['product']->isLowStock()))
            ->values();

        $categories = Product::query()->where('company_id', $companyId)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        return view('stock.audit', compact('rows', 'products', 'categories', 'dateFrom', 'dateTo'));
    }

    public function salesAudit(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $date = $request->filled('date') ? CarbonImmutable::parse($request->input('date'))->toDateString() : now()->subDay()->toDateString();

        $sold = ProductSaleItem::query()
            ->select('product_sale_items.product_id', DB::raw('SUM(product_sale_items.quantity) as sold_quantity'))
            ->join('product_sales', 'product_sales.id', '=', 'product_sale_items.product_sale_id')
            ->where('product_sales.company_id', $companyId)
            ->where('product_sales.status', ProductSale::STATUS_COMPLETED)
            ->whereDate('product_sales.sold_at', $date)
            ->groupBy('product_sale_items.product_id')
            ->pluck('sold_quantity', 'product_sale_items.product_id');

        $moved = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('type', StockMovement::TYPE_SALE)
            ->whereDate('movement_date', $date)
            ->select('product_id', DB::raw('SUM(quantity) as moved_quantity'))
            ->groupBy('product_id')
            ->pluck('moved_quantity', 'product_id');

        $productIds = $sold->keys()->merge($moved->keys())->unique()->values();
        $products = Product::query()->where('company_id', $companyId)->whereIn('id', $productIds)->get()->keyBy('id');

        $rows = $productIds->map(function ($productId) use ($sold, $moved, $products) {
            $soldQuantity = round((float) ($sold[$productId] ?? 0), 2);
            $movedQuantity = round((float) ($moved[$productId] ?? 0), 2);

            return [
                'product' => $products->get((int) $productId),
                'sold' => $soldQuantity,
                'moved' => $movedQuantity,
                'difference' => round($soldQuantity - $movedQuantity, 2),
            ];
        });

        return view('stock.sales-audit', compact('rows', 'date'));
    }

    public function lowStock(Request $request): View
    {
        $products = $this->stockService->getLowStockProducts((int) $request->user()->company_id, null);

        return view('stock.low', compact('products'));
    }

    private function products(int $companyId)
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->orderBy('name');
    }

    /**
     * @return array<string, \Illuminate\Support\Collection<int, numeric-string>>
     */
    private function dailyCheckContext(int $companyId, string $referenceDate): array
    {
        $sold = ProductSaleItem::query()
            ->select('product_sale_items.product_id', DB::raw('SUM(product_sale_items.quantity) as quantity'))
            ->join('product_sales', 'product_sales.id', '=', 'product_sale_items.product_sale_id')
            ->where('product_sales.company_id', $companyId)
            ->where('product_sales.status', ProductSale::STATUS_COMPLETED)
            ->whereDate('product_sales.sold_at', $referenceDate)
            ->groupBy('product_sale_items.product_id')
            ->pluck('quantity', 'product_sale_items.product_id');

        $movementBase = StockMovement::query()
            ->where('company_id', $companyId)
            ->whereDate('movement_date', $referenceDate);

        return [
            'sold' => $sold,
            'sale_stock' => (clone $movementBase)->where('type', StockMovement::TYPE_SALE)->select('product_id', DB::raw('SUM(quantity) as quantity'))->groupBy('product_id')->pluck('quantity', 'product_id'),
            'other_output' => (clone $movementBase)->where('direction', StockMovement::DIRECTION_OUT)->where('type', '!=', StockMovement::TYPE_SALE)->select('product_id', DB::raw('SUM(quantity) as quantity'))->groupBy('product_id')->pluck('quantity', 'product_id'),
            'input' => (clone $movementBase)->where('direction', StockMovement::DIRECTION_IN)->select('product_id', DB::raw('SUM(quantity) as quantity'))->groupBy('product_id')->pluck('quantity', 'product_id'),
            'adjustment' => (clone $movementBase)->whereIn('type', [StockMovement::TYPE_ADJUSTMENT, StockMovement::TYPE_MANUAL_ADJUSTMENT, StockMovement::TYPE_AUDIT_ADJUSTMENT, StockMovement::TYPE_BLIND_COUNT_ADJUSTMENT_APPLIED])->select('product_id', DB::raw('SUM(quantity) as quantity'))->groupBy('product_id')->pluck('quantity', 'product_id'),
        ];
    }
}
