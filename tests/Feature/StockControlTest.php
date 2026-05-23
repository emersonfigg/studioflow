<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\DailyStockCheck;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductSaleItem;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdv_product_sale_does_not_decrement_untracked_product(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 10,
            'stock_quantity' => 0,
            'track_stock' => false,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])->assertRedirect();

        $this->assertSame('0.00', (string) $product->fresh()->stock_quantity);
        $this->assertDatabaseMissing('stock_movements', [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_SALE,
        ]);
    }

    public function test_stock_sale_decrement_is_idempotent_per_sale_item(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create(['stock_quantity' => 5, 'track_stock' => true, 'active' => true]);
        $sale = ProductSale::factory()->for($company)->for($client)->for($admin, 'user')->create(['sold_at' => now()]);
        ProductSaleItem::create([
            'product_sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
            'total_price' => 20,
        ]);

        $service = app(StockService::class);
        $service->applyProductSaleMovements($sale->fresh(['items.product']), $admin);
        $service->applyProductSaleMovements($sale->fresh(['items.product']), $admin);

        $this->assertSame('3.00', (string) $product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_SALE)->where('product_id', $product->id)->count());
    }

    public function test_manual_adjustment_increases_and_decreases_stock_with_movement(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create(['stock_quantity' => 5, 'track_stock' => true, 'active' => true]);

        $this->actingAs($admin)->post(route('stock.adjustments.store', absolute: false), [
            'product_id' => $product->id,
            'direction' => 'in',
            'quantity' => 4,
            'reason' => 'purchase',
            'unit_cost' => 3,
            'notes' => 'Compra semanal',
        ])->assertRedirect(route('stock.diary', absolute: false));

        $this->assertSame('9.00', (string) $product->fresh()->stock_quantity);

        $this->actingAs($admin)->post(route('stock.adjustments.store', absolute: false), [
            'product_id' => $product->id,
            'direction' => 'out',
            'quantity' => 2,
            'reason' => 'internal_use',
            'notes' => 'Uso na bancada',
        ])->assertRedirect(route('stock.diary', absolute: false));

        $this->assertSame('7.00', (string) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'direction' => 'out',
            'balance_before' => 9,
            'balance_after' => 7,
        ]);
    }

    public function test_manual_adjustment_does_not_allow_output_above_stock(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create(['stock_quantity' => 1, 'track_stock' => true, 'active' => true]);

        $this->actingAs($admin)->post(route('stock.adjustments.store', absolute: false), [
            'product_id' => $product->id,
            'direction' => 'out',
            'quantity' => 3,
            'reason' => 'loss',
            'notes' => 'Quebra registrada',
        ])->assertSessionHasErrors('stock');

        $this->assertSame('1.00', (string) $product->fresh()->stock_quantity);
    }

    public function test_blind_count_hides_expected_stock_then_finalizes_without_adjusting_stock(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'name' => 'Pomada Controle',
            'stock_quantity' => 10,
            'cost_price' => 5,
            'track_stock' => true,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('stock.counts.create', absolute: false))
            ->assertOk()
            ->assertSee('Pomada Controle')
            ->assertDontSee('10.00');

        $this->actingAs($admin)->post(route('stock.counts.store', absolute: false), [
            'count_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'counted_quantity' => 7],
            ],
        ])->assertRedirect();

        $count = StockCount::query()->firstOrFail();

        $this->actingAs($admin)->post(route('stock.counts.complete', $count, false))->assertRedirect();

        $this->assertSame('10.00', (string) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_count_items', [
            'stock_count_id' => $count->id,
            'expected_quantity' => 10,
            'counted_quantity' => 7,
            'difference_quantity' => -3,
            'difference_value' => 15,
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'type' => StockMovement::TYPE_BLIND_COUNT_ADJUSTMENT_APPLIED,
            'product_id' => $product->id,
        ]);
    }

    public function test_apply_audit_adjustment_changes_stock_and_creates_movement_once(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create(['stock_quantity' => 5, 'track_stock' => true, 'active' => true]);
        $count = StockCount::create(['company_id' => $company->id, 'user_id' => $admin->id, 'status' => StockCount::STATUS_DRAFT, 'count_date' => now()]);
        $count->items()->create(['product_id' => $product->id, 'counted_quantity' => 3]);

        $this->actingAs($admin)->post(route('stock.counts.complete', $count, false))->assertRedirect();
        $this->actingAs($admin)->post(route('stock.counts.complete', $count, false))->assertRedirect();

        $this->assertSame('5.00', (string) $product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::TYPE_BLIND_COUNT_ADJUSTMENT_APPLIED)->count());

        $this->actingAs($admin)->post(route('stock.adjustments.store', absolute: false), [
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'direction' => 'out',
            'quantity' => 2,
            'reason' => 'correction',
            'notes' => 'Ajuste autorizado apos auditoria geral.',
        ])->assertRedirect(route('stock.diary', absolute: false));

        $this->assertSame('3.00', (string) $product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_MANUAL_ADJUSTMENT)->count());
        $this->assertDatabaseHas('stock_count_items', [
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'adjusted_by' => $admin->id,
        ]);
    }

    public function test_diary_ignores_unapplied_blind_count_and_lists_applied_adjustment(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create(['name' => 'Cera Diario', 'stock_quantity' => 8, 'track_stock' => true, 'active' => true]);
        $count = StockCount::create(['company_id' => $company->id, 'user_id' => $admin->id, 'status' => StockCount::STATUS_DRAFT, 'count_date' => now()]);
        $count->items()->create(['product_id' => $product->id, 'counted_quantity' => 6]);

        $this->actingAs($admin)->post(route('stock.counts.complete', $count, false))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('stock.diary', absolute: false))
            ->assertOk()
            ->assertSee('Nenhuma movimentacao real encontrada.');

        $this->actingAs($admin)->post(route('stock.adjustments.store', absolute: false), [
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'direction' => 'out',
            'quantity' => 2,
            'reason' => 'correction',
            'notes' => 'Ajuste autorizado apos auditoria geral.',
        ])->assertRedirect(route('stock.diary', absolute: false));

        $this->actingAs($admin)
            ->get(route('stock.diary', absolute: false))
            ->assertOk()
            ->assertSee('Cera Diario')
            ->assertSee('Ajuste autorizado');
    }

    public function test_audit_shows_pending_count_divergence_then_reconciles_after_adjustment(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create(['name' => 'Auditoria Produto', 'stock_quantity' => 12, 'cost_price' => 4, 'track_stock' => true, 'active' => true]);
        $count = StockCount::create(['company_id' => $company->id, 'user_id' => $admin->id, 'status' => StockCount::STATUS_DRAFT, 'count_date' => now()]);
        $count->items()->create(['product_id' => $product->id, 'counted_quantity' => 10]);

        $this->actingAs($admin)->post(route('stock.counts.complete', $count, false))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('stock.audit', absolute: false))
            ->assertOk()
            ->assertSee('Auditoria Produto')
            ->assertSee('2,00')
            ->assertSee('8,00');

        $this->actingAs($admin)->post(route('stock.adjustments.store', absolute: false), [
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'direction' => 'out',
            'quantity' => 2,
            'reason' => 'correction',
            'notes' => 'Ajuste autorizado apos auditoria geral.',
        ])->assertRedirect(route('stock.diary', absolute: false));

        $this->actingAs($admin)
            ->get(route('stock.audit', absolute: false))
            ->assertOk()
            ->assertSee('Ajustes')
            ->assertSee('10,00');
    }

    public function test_daily_stock_check_generation_uses_yesterday_and_controlled_products(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        Product::factory()->for($company)->create(['name' => 'Controlado Diario', 'stock_quantity' => 4, 'track_stock' => true, 'active' => true]);
        Product::factory()->for($company)->create(['name' => 'Sem Controle Diario', 'stock_quantity' => 0, 'track_stock' => false, 'active' => true]);

        $this->actingAs($admin)
            ->post(route('stock.daily-checks.generate', absolute: false), [])
            ->assertRedirect();

        $check = DailyStockCheck::query()->with('items.product')->firstOrFail();

        $this->assertSame(now()->subDay()->toDateString(), $check->reference_date->toDateString());
        $this->assertTrue($check->items->pluck('product.name')->contains('Controlado Diario'));
        $this->assertFalse($check->items->pluck('product.name')->contains('Sem Controle Diario'));
    }

    public function test_daily_stock_check_draft_does_not_show_expected_and_completion_only_records_divergence(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'name' => 'Shampoo Diario',
            'stock_quantity' => 12,
            'cost_price' => 6,
            'track_stock' => true,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('stock.daily-checks.generate', absolute: false), [])->assertRedirect();
        $check = DailyStockCheck::query()->with('items')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('stock.daily-checks.show', $check, false))
            ->assertOk()
            ->assertSee('Shampoo Diario')
            ->assertDontSee('Esperado');

        $this->actingAs($admin)->post(route('stock.daily-checks.complete', $check, false), [
            'items' => [
                ['id' => $check->items->first()->id, 'counted_quantity' => 10],
            ],
        ])->assertRedirect();

        $this->assertSame('12.00', (string) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('daily_stock_check_items', [
            'daily_stock_check_id' => $check->id,
            'product_id' => $product->id,
            'expected_quantity' => 12,
            'counted_quantity' => 10,
            'difference_quantity' => -2,
            'difference_value' => 12,
            'status' => 'divergent',
        ]);
        $this->assertSame(0, StockMovement::query()->where('product_id', $product->id)->count());
    }

    public function test_daily_stock_check_does_not_allow_two_active_checks_for_same_reference_date(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        Product::factory()->for($company)->create(['track_stock' => true, 'active' => true]);
        $date = now()->subDay()->toDateString();

        $this->actingAs($admin)->post(route('stock.daily-checks.generate', absolute: false), [
            'reference_date' => $date,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('stock.daily-checks.generate', absolute: false), [
            'reference_date' => $date,
        ])->assertSessionHasErrors('reference_date');
    }

    public function test_daily_stock_check_never_applies_directly_and_adjustment_module_updates_stock(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create(['name' => 'Mascara Diario', 'stock_quantity' => 9, 'cost_price' => 4, 'track_stock' => true, 'active' => true]);

        $this->actingAs($admin)->post(route('stock.daily-checks.generate', absolute: false), [])->assertRedirect();
        $check = DailyStockCheck::query()->with('items')->firstOrFail();

        $this->actingAs($admin)->post(route('stock.daily-checks.complete', $check, false), [
            'items' => [
                ['id' => $check->items->first()->id, 'counted_quantity' => 7],
            ],
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('stock.diary', absolute: false))
            ->assertOk()
            ->assertSee('Nenhuma movimentacao real encontrada.');

        $this->actingAs($admin)
            ->get(route('stock.daily-checks.show', $check, false))
            ->assertOk()
            ->assertSee('Divergencia registrada')
            ->assertSee('Ir para Ajustes')
            ->assertDontSee('Aplicar ajuste de auditoria');

        $this->actingAs($admin)
            ->post('/stock/daily-checks/'.$check->id.'/apply-adjustments')
            ->assertNotFound();

        $this->actingAs($admin)->post(route('stock.adjustments.store', absolute: false), [
            'daily_stock_check_id' => $check->id,
            'product_id' => $product->id,
            'direction' => 'out',
            'quantity' => 2,
            'reason' => 'correction',
            'notes' => 'Ajuste autorizado apos conferencia diaria.',
        ])->assertRedirect(route('stock.diary', absolute: false));

        $this->assertSame('7.00', (string) $product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::query()->where('product_id', $product->id)->where('type', StockMovement::TYPE_MANUAL_ADJUSTMENT)->count());
        $this->assertDatabaseHas('daily_stock_check_items', [
            'daily_stock_check_id' => $check->id,
            'product_id' => $product->id,
            'adjusted_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('stock.diary', absolute: false))
            ->assertOk()
            ->assertSee('Mascara Diario')
            ->assertSee('Ajuste autorizado');
    }

    public function test_daily_stock_check_audit_shows_pending_then_applied_divergence(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create(['name' => 'Auditoria Diaria', 'stock_quantity' => 6, 'cost_price' => 5, 'track_stock' => true, 'active' => true]);

        $this->actingAs($admin)->post(route('stock.daily-checks.generate', absolute: false), [])->assertRedirect();
        $check = DailyStockCheck::query()->with('items')->firstOrFail();
        $this->actingAs($admin)->post(route('stock.daily-checks.complete', $check, false), [
            'items' => [
                ['id' => $check->items->first()->id, 'counted_quantity' => 8],
            ],
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('stock.audit', absolute: false))
            ->assertOk()
            ->assertSee('Auditoria Diaria')
            ->assertSee('2,00')
            ->assertSee('10,00');

        $this->actingAs($admin)->post(route('stock.adjustments.store', absolute: false), [
            'daily_stock_check_id' => $check->id,
            'product_id' => $product->id,
            'direction' => 'in',
            'quantity' => 2,
            'reason' => 'correction',
            'notes' => 'Ajuste autorizado apos conferencia diaria.',
        ])->assertRedirect(route('stock.diary', absolute: false));

        $this->actingAs($admin)
            ->get(route('stock.audit', absolute: false))
            ->assertOk()
            ->assertSee('Diaria aplicada')
            ->assertSee('8,00');
    }

    public function test_sales_audit_shows_ok_and_divergence(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $okProduct = Product::factory()->for($company)->create(['name' => 'Gel OK', 'stock_quantity' => 10, 'track_stock' => true]);
        $badProduct = Product::factory()->for($company)->create(['name' => 'Gel Divergente', 'stock_quantity' => 10, 'track_stock' => true]);
        $sale = ProductSale::factory()->for($company)->for($client)->for($admin, 'user')->create(['sold_at' => now()->subDay()]);
        $okItem = ProductSaleItem::create([
            'product_sale_id' => $sale->id,
            'product_id' => $okProduct->id,
            'quantity' => 2,
            'unit_price' => 10,
            'total_price' => 20,
        ]);
        ProductSaleItem::create([
            'product_sale_id' => $sale->id,
            'product_id' => $badProduct->id,
            'quantity' => 1,
            'unit_price' => 10,
            'total_price' => 10,
        ]);

        app(StockService::class)->decrease($okProduct, 2, 'Venda de produto', StockMovement::TYPE_SALE, ProductSaleItem::class, $okItem->id, $admin, now()->subDay());

        $this->actingAs($admin)
            ->get(route('stock.sales-audit', ['date' => now()->subDay()->toDateString()], false))
            ->assertOk()
            ->assertSee('Gel OK')
            ->assertSee('Divergente');
    }

    public function test_low_stock_report_lists_products_below_minimum(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        Product::factory()->for($company)->create(['name' => 'Baixo', 'stock_quantity' => 1, 'minimum_stock' => 3, 'track_stock' => true, 'low_stock_alert' => true]);
        Product::factory()->for($company)->create(['name' => 'Alto', 'stock_quantity' => 10, 'minimum_stock' => 3, 'track_stock' => true, 'low_stock_alert' => true]);

        $this->actingAs($admin)
            ->get(route('stock.low', absolute: false))
            ->assertOk()
            ->assertSee('Baixo')
            ->assertDontSee('Alto');
    }
}
