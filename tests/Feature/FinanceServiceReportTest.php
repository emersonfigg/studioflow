<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceServiceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_report_loads_without_data(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->get(route('finance.service-report', absolute: false))
            ->assertOk()
            ->assertSee('Nenhum serviço vendido neste período', false);
    }

    public function test_service_report_sums_quantity_and_revenue_correctly(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $service = Service::factory()->for($company)->create(['name' => 'Corte', 'price' => 50]);
        $client = Client::factory()->for($company)->create();

        $order = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $admin->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 100,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 100,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay(),
        ]);

        ServiceOrderItem::query()->create([
            'service_order_id' => $order->id,
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $admin->id,
            'description' => $service->name,
            'quantity' => 2,
            'unit_price' => 50,
            'total_price' => 100,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('finance.service-report', absolute: false));

        $response->assertOk()
            ->assertSee('Corte')
            ->assertSee('100,00')
            ->assertSee('2');
    }

    public function test_service_report_filter_by_professional_works(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $other = User::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create(['name' => 'Barba']);
        $client = Client::factory()->for($company)->create();

        $orderA = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $admin->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 40,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 40,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay(),
        ]);
        $orderB = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $other->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 30,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 30,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay(),
        ]);

        ServiceOrderItem::query()->create([
            'service_order_id' => $orderA->id,
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $admin->id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => 40,
            'total_price' => 40,
        ]);
        ServiceOrderItem::query()->create([
            'service_order_id' => $orderB->id,
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $other->id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => 30,
            'total_price' => 30,
        ]);

        $this->actingAs($admin)
            ->get(route('finance.service-report', ['user_id' => $admin->id], false))
            ->assertOk()
            ->assertSee('40,00')
            ->assertDontSee('70,00');
    }

    public function test_service_report_does_not_leak_other_company_data(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $otherProfessional = User::factory()->for($otherCompany)->create();
        $otherService = Service::factory()->for($otherCompany)->create(['name' => 'Outro']);
        $otherClient = Client::factory()->for($otherCompany)->create();

        $order = ServiceOrder::query()->create([
            'company_id' => $otherCompany->id,
            'client_id' => $otherClient->id,
            'professional_id' => $otherProfessional->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 90,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 90,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay(),
        ]);

        ServiceOrderItem::query()->create([
            'service_order_id' => $order->id,
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $otherService->id,
            'professional_id' => $otherProfessional->id,
            'description' => $otherService->name,
            'quantity' => 1,
            'unit_price' => 90,
            'total_price' => 90,
        ]);

        $this->actingAs($admin)
            ->get(route('finance.service-report', absolute: false))
            ->assertOk()
            ->assertDontSee('Outro');
    }
}
