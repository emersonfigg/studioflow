<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePdvSaleRequest;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\ProductSaleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PdvController extends Controller
{
    /**
     * Display the POS quick sale screen.
     */
    public function index(Request $request, CashRegisterService $cashRegisterService): View
    {
        $companyId = $request->user()->company_id;
        $cashRegister = $cashRegisterService->registerForDate($companyId, now());
        $clients = Client::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'phone']);
        $products = Product::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get([
            'id', 'name', 'sku', 'price', 'stock_quantity', 'image_path',
        ]);
        $services = Service::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get([
            'id', 'name', 'price', 'duration_minutes', 'image_path',
        ]);
        $professionals = User::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name']);
        $catalog = [
            'products' => $products->map(function ($product) {
                $code = $product->sku ? (string) $product->sku : 'P'.$product->id;

                return [
                    'id' => $product->id,
                    'code' => $code,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'stock' => $product->stock_quantity,
                    'type' => 'product',
                    'image_url' => $product->image_url,
                ];
            })->values(),
            'services' => $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'code' => 'S'.$service->id,
                    'sku' => null,
                    'name' => $service->name,
                    'price' => (float) $service->price,
                    'duration' => $service->duration_minutes,
                    'type' => 'service',
                    'image_url' => $service->image_url,
                ];
            })->values(),
        ];

        return view('pdv.index', [
            'catalog' => $catalog,
            'clients' => $clients,
            'products' => $products,
            'services' => $services,
            'professionals' => $professionals,
            'paymentMethods' => collect(Payment::PAYMENT_METHODS)->mapWithKeys(fn (string $method): array => [$method => match ($method) {
                'cash' => 'Dinheiro',
                'pix' => 'Pix',
                'card' => 'Cartao',
                default => ucfirst($method),
            }])->all(),
            'cashRegister' => $cashRegister,
        ]);
    }

    /**
     * Store a POS standalone sale using existing service order logic.
     */
    public function store(StorePdvSaleRequest $request, ProductSaleService $productSaleService): RedirectResponse
    {
        $order = $productSaleService->registerStandaloneOrder($request->user(), $request->payload());

        return redirect()
            ->route('pdv.index')
            ->with('status', "pdv-sale-created-{$order->id}");
    }
}
