<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductSaleRequest;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\ProductSaleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductSaleController extends Controller
{
    /**
     * Display recent product sales.
     */
    public function index(Request $request): View
    {
        $sales = ServiceOrder::query()
            ->with(['client', 'professional', 'items.service', 'items.product'])
            ->where('company_id', $request->user()->company_id)
            ->whereNull('appointment_id')
            ->where('status', ServiceOrder::STATUS_PAID)
            ->latest('closed_at')
            ->paginate(12);

        return view('products.sales.index', [
            'sales' => $sales,
            'legacySales' => ProductSale::query()
                ->with(['client', 'user', 'items.product'])
                ->where('company_id', $request->user()->company_id)
                ->whereNull('service_order_id')
                ->latest('sold_at')
                ->limit(12)
                ->get(),
        ]);
    }

    /**
     * Show the form to record a product sale.
     */
    public function create(Request $request): View
    {
        $companyId = $request->user()->company_id;

        return view('products.sales.create', [
            'clients' => Client::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'products' => Product::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'services' => Service::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'professionals' => User::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'prefilledClientId' => $request->integer('client_id') ?: null,
            'paymentMethods' => [
                'cash' => 'Dinheiro',
                'pix' => 'Pix',
                'card' => 'Cartao',
            ],
        ]);
    }

    /**
     * Store a product sale.
     */
    public function store(StoreProductSaleRequest $request, ProductSaleService $productSaleService): RedirectResponse
    {
        $order = $productSaleService->registerStandaloneOrder($request->user(), $request->validated());

        return redirect()
            ->route('clients.show', $order->client)
            ->with('status', 'product-sale-created');
    }
}
