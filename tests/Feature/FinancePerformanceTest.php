<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_page_loads_with_empty_state(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->get(route('finance.performance', absolute: false))
            ->assertOk()
            ->assertSeeText('Sem dados no período selecionado');
    }

    public function test_revenue_ignores_cancelled_orders(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create(['price' => 100]);

        $this->createOrderWithServiceItem($company, $client, $professional, $service, ServiceOrder::STATUS_PAID, '2026-05-01 10:00:00', 100);
        $this->createOrderWithServiceItem($company, $client, $professional, $service, ServiceOrder::STATUS_CANCELLED, '2026-05-01 11:00:00', 250);

        $this->actingAs($admin)
            ->get(route('finance.performance', [
                'period' => 'custom',
                'from' => '2026-05-01',
                'to' => '2026-05-01',
            ], false))
            ->assertOk()
            ->assertSeeText('R$ 100,00')
            ->assertDontSeeText('R$ 350,00');
    }

    public function test_upsell_rate_calculates_orders_with_two_or_more_services(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create(['price' => 80]);

        $orderOne = $this->createOrderWithServiceItem($company, $client, $professional, $service, ServiceOrder::STATUS_PAID, '2026-05-01 09:00:00', 160);
        $orderOne->items()->create([
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => 80,
            'total_price' => 80,
        ]);

        $this->createOrderWithServiceItem($company, $client, $professional, $service, ServiceOrder::STATUS_PAID, '2026-05-01 10:00:00', 80);

        $this->actingAs($admin)
            ->get(route('finance.performance', [
                'period' => 'custom',
                'from' => '2026-05-01',
                'to' => '2026-05-01',
            ], false))
            ->assertOk()
            ->assertSeeText('Taxa de upsell: 50,00%');
    }

    public function test_performance_data_respects_company_id(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $professionalA = User::factory()->for($company)->create();
        $clientA = Client::factory()->for($company)->create();
        $serviceA = Service::factory()->for($company)->create(['price' => 90]);
        $this->createOrderWithServiceItem($company, $clientA, $professionalA, $serviceA, ServiceOrder::STATUS_PAID, '2026-05-02 10:00:00', 90);

        $professionalB = User::factory()->for($otherCompany)->create();
        $clientB = Client::factory()->for($otherCompany)->create();
        $serviceB = Service::factory()->for($otherCompany)->create(['price' => 500]);
        $this->createOrderWithServiceItem($otherCompany, $clientB, $professionalB, $serviceB, ServiceOrder::STATUS_PAID, '2026-05-02 10:00:00', 500);

        $this->actingAs($admin)
            ->get(route('finance.performance', [
                'period' => 'custom',
                'from' => '2026-05-02',
                'to' => '2026-05-02',
            ], false))
            ->assertOk()
            ->assertSeeText('R$ 90,00')
            ->assertDontSeeText('R$ 590,00');
    }

    public function test_custom_period_filters_results_correctly(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create(['price' => 120]);

        $this->createOrderWithServiceItem($company, $client, $professional, $service, ServiceOrder::STATUS_PAID, '2026-05-01 10:00:00', 120);
        $this->createOrderWithServiceItem($company, $client, $professional, $service, ServiceOrder::STATUS_PAID, '2026-05-05 10:00:00', 220);

        $this->actingAs($admin)
            ->get(route('finance.performance', [
                'period' => 'custom',
                'from' => '2026-05-05',
                'to' => '2026-05-05',
            ], false))
            ->assertOk()
            ->assertSeeText('R$ 220,00')
            ->assertDontSeeText('R$ 340,00');
    }

    private function createOrderWithServiceItem(
        Company $company,
        Client $client,
        User $professional,
        Service $service,
        string $status,
        string $closedAt,
        float $total
    ): ServiceOrder {
        $order = ServiceOrder::create([
            'company_id' => $company->id,
            'appointment_id' => null,
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'status' => $status,
            'subtotal_services' => $total,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => $total,
            'opened_at' => $closedAt,
            'closed_at' => $closedAt,
        ]);

        $order->items()->create([
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => $total,
            'total_price' => $total,
        ]);

        Payment::create([
            'company_id' => $company->id,
            'appointment_id' => null,
            'service_order_id' => $order->id,
            'user_id' => $professional->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => $total,
            'payment_method' => 'pix',
            'commission_type' => null,
            'commission_rate' => null,
            'commission_amount' => 0,
            'net_amount' => $total,
            'paid_at' => $closedAt,
            'notes' => null,
        ]);

        return $order;
    }
}
