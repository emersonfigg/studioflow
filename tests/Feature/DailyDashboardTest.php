<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_daily_dashboard(): void
    {
        CarbonImmutable::setTestNow('2026-06-05 10:00:00');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $this->actingAs($admin)
            ->get(route('daily-dashboard.index', absolute: false))
            ->assertOk()
            ->assertSee('Central do Dia')
            ->assertSee('Receita bruta');

        CarbonImmutable::setTestNow();
    }

    public function test_daily_dashboard_respects_company_scope(): void
    {
        CarbonImmutable::setTestNow('2026-06-05 10:00:00');

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $professional = User::factory()->for($company)->create(['name' => 'Ana Studio', 'active' => true]);
        $client = Client::factory()->for($company)->create(['name' => 'Cliente Studio']);
        $service = Service::factory()->for($company)->create(['name' => 'Corte Premium', 'price' => 100]);
        $product = Product::factory()->for($company)->create([
            'name' => 'Pomada Studio',
            'price' => 40,
            'cost_price' => 12,
            'stock_quantity' => 2,
            'minimum_stock' => 3,
        ]);

        $order = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 100,
            'subtotal_products' => 40,
            'discount' => 10,
            'total' => 130,
            'opened_at' => now()->subHour(),
            'closed_at' => now(),
        ]);

        ServiceOrderItem::query()->create([
            'service_order_id' => $order->id,
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        ServiceOrderItem::query()->create([
            'service_order_id' => $order->id,
            'type' => ServiceOrderItem::TYPE_PRODUCT,
            'product_id' => $product->id,
            'seller_id' => $professional->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 40,
            'total_price' => 40,
        ]);

        Payment::query()->create([
            'company_id' => $company->id,
            'service_order_id' => $order->id,
            'user_id' => $professional->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => 90,
            'payment_method' => 'pix',
            'commission_type' => 'percent',
            'commission_rate' => 10,
            'commission_amount' => 9,
            'net_amount' => 81,
            'paid_at' => now(),
        ]);

        $sale = ProductSale::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'service_order_id' => $order->id,
            'user_id' => $professional->id,
            'gross_amount' => 40,
            'payment_method' => 'pix',
            'sold_at' => now(),
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'seller_id' => $professional->id,
            'quantity' => 1,
            'unit_price' => 40,
            'total_price' => 40,
            'commission_amount' => 4,
        ]);

        $foreignClient = Client::factory()->for($otherCompany)->create(['name' => 'Cliente Outra Empresa']);
        $foreignProfessional = User::factory()->for($otherCompany)->create(['name' => 'Profissional Fora']);
        ServiceOrder::query()->create([
            'company_id' => $otherCompany->id,
            'client_id' => $foreignClient->id,
            'professional_id' => $foreignProfessional->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 999,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 999,
            'opened_at' => now()->subHour(),
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('daily-dashboard.index', ['date' => '2026-06-05'], false))
            ->assertOk()
            ->assertSee('Cliente Studio')
            ->assertSee('Corte Premium')
            ->assertSee('Pomada Studio')
            ->assertSee('R$ 130,00')
            ->assertDontSee('Cliente Outra Empresa')
            ->assertDontSee('R$ 999,00');

        CarbonImmutable::setTestNow();
    }
}
