<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductSaleItem;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\ProductCommissionCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-05-13 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_product_form_saves_percentage_commission(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $this->actingAs($admin)->post(route('products.store', absolute: false), [
            'name' => 'Pomada Premium',
            'price' => '70,00',
            'stock_quantity' => 5,
            'track_stock' => 1,
            'low_stock_alert' => 1,
            'active' => 1,
            'commission_type' => Product::COMMISSION_TYPE_PERCENTAGE,
            'commission_value' => '10',
        ])->assertRedirect(route('products.index', absolute: false));

        $product = Product::query()->where('name', 'Pomada Premium')->firstOrFail();

        $this->assertSame(Product::COMMISSION_TYPE_PERCENTAGE, $product->commission_type);
        $this->assertSame('10.00', (string) $product->commission_value);
    }

    public function test_product_edit_keeps_percentage_commission_selected_and_filled(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 70,
            'commission_type' => null,
            'commission_value' => null,
        ]);

        $this->actingAs($admin)->patch(route('products.update', $product, absolute: false), [
            'name' => $product->name,
            'price' => '70,00',
            'stock_quantity' => 5,
            'track_stock' => 1,
            'low_stock_alert' => 1,
            'active' => 1,
            'commission_type' => Product::COMMISSION_TYPE_PERCENTAGE,
            'commission_value' => '10,00',
        ])->assertRedirect(route('products.index', absolute: false));

        $product->refresh();
        $this->assertSame(Product::COMMISSION_TYPE_PERCENTAGE, $product->commission_type);
        $this->assertSame('10.00', (string) $product->commission_value);

        $response = $this->actingAs($admin)->get(route('products.edit', $product, absolute: false));

        $response->assertOk();
        $response->assertSee('value="percentage" selected', false);
        $response->assertSee('value="10,00"', false);
    }

    public function test_product_form_saves_fixed_commission(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $this->actingAs($admin)->post(route('products.store', absolute: false), [
            'name' => 'Finalizador',
            'price' => '50,00',
            'stock_quantity' => 3,
            'track_stock' => 1,
            'low_stock_alert' => 1,
            'active' => 1,
            'commission_type' => Product::COMMISSION_TYPE_FIXED,
            'commission_value' => '7,50',
        ])->assertRedirect(route('products.index', absolute: false));

        $product = Product::query()->where('name', 'Finalizador')->firstOrFail();

        $this->assertSame(Product::COMMISSION_TYPE_FIXED, $product->commission_type);
        $this->assertSame('7.50', (string) $product->commission_value);
    }

    public function test_product_form_saves_without_commission_and_clears_value(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $this->actingAs($admin)->post(route('products.store', absolute: false), [
            'name' => 'Shampoo Neutro',
            'price' => '35,00',
            'stock_quantity' => 4,
            'track_stock' => 1,
            'low_stock_alert' => 1,
            'active' => 1,
            'commission_type' => 'none',
            'commission_value' => '10',
        ])->assertRedirect(route('products.index', absolute: false));

        $product = Product::query()->where('name', 'Shampoo Neutro')->firstOrFail();

        $this->assertNull($product->commission_type);
        $this->assertNull($product->commission_value);
    }

    public function test_product_without_commission_generates_zero_commission_on_sale_items(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 20.00,
            'stock_quantity' => 10,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])->assertRedirect();

        $item = ProductSaleItem::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertNull($item->commission_type_snapshot);
        $this->assertNull($item->commission_value_snapshot);
        $this->assertSame('0.00', (string) $item->commission_amount);
    }

    public function test_fixed_commission_is_calculated_per_unit(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $seller = User::factory()->for($company)->create(['active' => true]);
        $product = Product::factory()
            ->for($company)
            ->withFixedCommission(5.00)
            ->create(['price' => 30.00, 'stock_quantity' => 10, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'cash',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4, 'seller_id' => $seller->id],
            ],
        ])->assertRedirect();

        $item = ProductSaleItem::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame($seller->id, $item->seller_id);
        $this->assertSame(Product::COMMISSION_TYPE_FIXED, $item->commission_type_snapshot);
        $this->assertSame('5.00', (string) $item->commission_value_snapshot);
        $this->assertSame('20.00', (string) $item->commission_amount);
    }

    public function test_percentage_commission_is_calculated_over_item_subtotal(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $seller = User::factory()->for($company)->create(['active' => true]);
        $product = Product::factory()
            ->for($company)
            ->withPercentageCommission(10)
            ->create(['price' => 50.00, 'stock_quantity' => 10, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'seller_id' => $seller->id],
            ],
        ])->assertRedirect();

        $item = ProductSaleItem::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(Product::COMMISSION_TYPE_PERCENTAGE, $item->commission_type_snapshot);
        $this->assertSame('10.00', (string) $item->commission_value_snapshot);
        $this->assertSame('10.00', (string) $item->commission_amount);
    }

    public function test_percentage_commission_applies_over_post_discount_subtotal(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $seller = User::factory()->for($company)->create(['active' => true]);
        $product = Product::factory()
            ->for($company)
            ->withPercentageCommission(10)
            ->create(['price' => 100.00, 'stock_quantity' => 10, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'discount_type' => 'fixed',
            'discount_value' => '20,00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'seller_id' => $seller->id],
            ],
        ])->assertRedirect();

        $item = ProductSaleItem::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('8.00', (string) $item->commission_amount);
    }

    public function test_pdv_requires_seller_for_commissioned_products(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()
            ->for($company)
            ->withFixedCommission(2.00)
            ->create(['price' => 10.00, 'stock_quantity' => 10, 'active' => true]);

        $response = $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, ProductSaleItem::query()->count());
    }

    public function test_pdv_rejects_seller_from_another_company(): void
    {
        $company = Company::factory()->create();
        $foreignCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $foreignSeller = User::factory()->for($foreignCompany)->create(['active' => true]);
        $product = Product::factory()
            ->for($company)
            ->withFixedCommission(2.00)
            ->create(['price' => 10.00, 'stock_quantity' => 10, 'active' => true]);

        $response = $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'seller_id' => $foreignSeller->id],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, ProductSaleItem::query()->count());
    }

    public function test_later_product_commission_change_does_not_affect_past_sales(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $seller = User::factory()->for($company)->create(['active' => true]);
        $product = Product::factory()
            ->for($company)
            ->withFixedCommission(5.00)
            ->create(['price' => 30.00, 'stock_quantity' => 10, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'seller_id' => $seller->id],
            ],
        ])->assertRedirect();

        $product->update([
            'commission_type' => Product::COMMISSION_TYPE_PERCENTAGE,
            'commission_value' => 25,
        ]);

        $item = ProductSaleItem::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(Product::COMMISSION_TYPE_FIXED, $item->commission_type_snapshot);
        $this->assertSame('5.00', (string) $item->commission_value_snapshot);
        $this->assertSame('5.00', (string) $item->commission_amount);
    }

    public function test_product_commission_report_summarizes_per_period(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $sellerA = User::factory()->for($company)->create(['active' => true, 'name' => 'Ana']);
        $sellerB = User::factory()->for($company)->create(['active' => true, 'name' => 'Bruno']);
        $product = Product::factory()
            ->for($company)
            ->withPercentageCommission(10)
            ->create(['price' => 100.00, 'stock_quantity' => 20, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'seller_id' => $sellerA->id]],
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'seller_id' => $sellerB->id]],
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get(route('finance.product-commissions', [
            'from' => CarbonImmutable::today()->subDays(7)->format('Y-m-d'),
            'to' => CarbonImmutable::today()->addDay()->format('Y-m-d'),
        ], absolute: false));

        $response->assertOk();
        $response->assertSee('Bruno');
        $response->assertSee('Comissões de produtos');

        $totalCommission = (float) ProductSaleItem::query()->sum('commission_amount');
        $this->assertEqualsWithDelta(30.0, $totalCommission, 0.01);
    }

    public function test_staff_user_cannot_change_product_commission_rule(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create(['active' => true, 'role' => 'staff']);
        $product = Product::factory()->for($company)->create([
            'price' => 30.00,
            'stock_quantity' => 5,
            'active' => true,
        ]);

        $response = $this->actingAs($staff)->patch(route('products.update', $product, absolute: false), [
            'name' => $product->name,
            'price' => '30,00',
            'stock_quantity' => 5,
            'active' => 1,
            'commission_type' => 'fixed',
            'commission_value' => '5,00',
        ]);

        $response->assertForbidden();
        $product->refresh();
        $this->assertNull($product->commission_type);
        $this->assertNull($product->commission_value);
    }

    public function test_staff_can_only_see_own_commissions_on_report(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $staff = User::factory()->for($company)->create(['active' => true, 'role' => 'staff', 'name' => 'Carlos']);
        $other = User::factory()->for($company)->create(['active' => true, 'role' => 'staff', 'name' => 'Daniela']);
        $product = Product::factory()
            ->for($company)
            ->withFixedCommission(3.00)
            ->create(['price' => 20.00, 'stock_quantity' => 10, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'seller_id' => $staff->id]],
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'seller_id' => $other->id]],
        ])->assertRedirect();

        $response = $this->actingAs($staff)->get(route('finance.product-commissions', [
            'from' => CarbonImmutable::today()->subDays(7)->format('Y-m-d'),
            'to' => CarbonImmutable::today()->addDay()->format('Y-m-d'),
        ], absolute: false));

        $response->assertOk();
        $response->assertSee('Carlos');
        $response->assertDontSee('Daniela');
    }

    public function test_calculator_returns_zero_when_value_is_zero(): void
    {
        $product = Product::factory()->make([
            'commission_type' => Product::COMMISSION_TYPE_FIXED,
            'commission_value' => 0,
        ]);

        $calculator = new ProductCommissionCalculator();

        $result = $calculator->calculate($product, 5, 100.0);

        $this->assertSame(0.0, $result['amount']);
        $this->assertNull($result['type']);
    }

    public function test_validation_blocks_percentage_above_100(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $response = $this->actingAs($admin)->post(route('products.store', absolute: false), [
            'name' => 'Pomada Premium',
            'price' => '40,00',
            'stock_quantity' => 5,
            'active' => 1,
            'commission_type' => 'percentage',
            'commission_value' => '120',
        ]);

        $response->assertSessionHasErrors('commission_value');
    }

    public function test_validation_blocks_negative_commission_value(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $response = $this->actingAs($admin)->post(route('products.store', absolute: false), [
            'name' => 'Pomada',
            'price' => '40,00',
            'stock_quantity' => 5,
            'active' => 1,
            'commission_type' => 'fixed',
            'commission_value' => '-5',
        ]);

        $response->assertSessionHasErrors('commission_value');
    }

    public function test_dashboard_ranking_shows_top_sellers(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $star = User::factory()->for($company)->create(['active' => true, 'name' => 'Estrela do Mes']);
        $product = Product::factory()
            ->for($company)
            ->withFixedCommission(10.00)
            ->create(['price' => 50.00, 'stock_quantity' => 5, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'seller_id' => $star->id]],
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get(route('dashboard', absolute: false));

        $response->assertOk();
        $response->assertSee('Estrela do Mes');
        $response->assertSee('Ranking de vendas de produtos');
    }
}
