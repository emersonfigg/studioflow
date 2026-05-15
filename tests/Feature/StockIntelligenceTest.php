<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceProductConsumption;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdv_product_sale_decrements_stock_and_records_movement(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 10.00,
            'stock_quantity' => 5,
            'track_stock' => true,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertRedirect();

        $this->assertSame('3.00', (string) $product->fresh()->stock_quantity);

        $this->assertTrue(
            StockMovement::query()
                ->where('company_id', $company->id)
                ->where('product_id', $product->id)
                ->where('type', StockMovement::TYPE_SALE)
                ->exists()
        );
    }

    public function test_pdv_rejects_insufficient_stock(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 10.00,
            'stock_quantity' => 1,
            'track_stock' => true,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertSame('1.00', (string) $product->fresh()->stock_quantity);
    }

    public function test_service_consumption_idempotent_per_service_order(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['active' => true, 'price' => 50, 'duration_minutes' => 30]);
        $product = Product::factory()->for($company)->create([
            'stock_quantity' => 10,
            'track_stock' => true,
            'active' => true,
        ]);

        ServiceProductConsumption::create([
            'company_id' => $company->id,
            'service_id' => $service->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'un',
            'active' => true,
        ]);

        $order = ServiceOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $admin->id,
            'status' => ServiceOrder::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        ServiceOrderItem::create([
            'service_order_id' => $order->id,
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $admin->id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => 50,
            'total_price' => 50,
        ]);

        $stockService = app(StockService::class);
        $stockService->applyServiceOrderConsumptions($order->fresh(['items']), $admin);
        $this->assertSame('8.00', (string) $product->fresh()->stock_quantity);

        $stockService->applyServiceOrderConsumptions($order->fresh(['items']), $admin);
        $this->assertSame('8.00', (string) $product->fresh()->stock_quantity);
    }

    public function test_low_stock_query_respects_minimum_and_alert_flags(): void
    {
        $company = Company::factory()->create();
        Product::factory()->for($company)->create([
            'stock_quantity' => 2,
            'minimum_stock' => 5,
            'low_stock_alert' => true,
            'track_stock' => true,
            'active' => true,
        ]);
        Product::factory()->for($company)->create([
            'stock_quantity' => 2,
            'minimum_stock' => 5,
            'low_stock_alert' => false,
            'track_stock' => true,
            'active' => true,
        ]);

        $rows = app(StockService::class)->getLowStockProducts((int) $company->id);
        $this->assertCount(1, $rows);
    }
}
