<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductSaleItem;
use App\Support\MediaStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display company products and recent sales highlights.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        $products = Product::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->paginate(12);

        $recentSaleItems = ProductSaleItem::query()
            ->with(['sale.client', 'product'])
            ->whereHas('sale', fn ($query) => $query->where('company_id', $companyId))
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('products.index', [
            'products' => $products,
            'activeProductsCount' => Product::query()->where('company_id', $companyId)->where('active', true)->count(),
            'averagePrice' => (float) (Product::query()->where('company_id', $companyId)->avg('price') ?? 0),
            'soldItemsCount' => (int) ProductSaleItem::query()
                ->whereHas('sale', fn ($query) => $query->where('company_id', $companyId))
                ->sum('quantity'),
            'stockTotal' => (int) Product::query()->where('company_id', $companyId)->sum('stock_quantity'),
            'inventoryRevenue' => (float) (ProductSaleItem::query()
                ->whereHas('sale', fn ($query) => $query->where('company_id', $companyId))
                ->sum('total_price') ?? 0),
            'recentSaleItems' => $recentSaleItems,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('products.create');
    }

    /**
     * Store a product.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $image = $request->file('image') ?? ($data['image'] ?? null);
        unset($data['image']);

        if ($image) {
            $data['image_path'] = MediaStorage::putFile('products', $image);
        }

        Product::create([
            ...$data,
            'company_id' => $request->user()->company_id,
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('products.index')->with('status', 'product-created');
    }

    /**
     * Show the form for editing a product.
     */
    public function edit(Request $request, Product $product): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureProductBelongsToCompany($request, $product);

        return view('products.edit', [
            'product' => $product,
        ]);
    }

    /**
     * Update the product.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->ensureProductBelongsToCompany($request, $product);

        $data = $request->validated();
        $image = $request->file('image') ?? ($data['image'] ?? null);
        unset($data['image']);
        $removeImage = $request->boolean('remove_image');
        unset($data['remove_image']);

        if ($removeImage && $product->image_path) {
            $this->deleteProductImage($product);
            $data['image_path'] = null;
        }

        if ($image) {
            $newPath = MediaStorage::putFile('products', $image);

            if ($product->image_path) {
                $this->deleteProductImage($product);
            }

            $data['image_path'] = $newPath;
        }

        $product->update([
            ...$data,
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('products.index')->with('status', 'product-updated');
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureProductBelongsToCompany($request, $product);

        if ($product->image_path) {
            $this->deleteProductImage($product);
        }

        $product->delete();

        return redirect()->route('products.index')->with('status', 'product-deleted');
    }

    private function ensureProductBelongsToCompany(Request $request, Product $product): void
    {
        abort_unless($product->company_id === $request->user()->company_id, 404);
    }

    private function deleteProductImage(Product $product): void
    {
        $paths = array_values(array_unique(array_filter([
            $product->normalizedImagePath(),
            $product->image_path,
        ])));

        if ($paths === []) {
            return;
        }

        MediaStorage::delete($paths);
    }
}
