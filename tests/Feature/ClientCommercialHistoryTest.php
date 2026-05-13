<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientCommercialHistory;
use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\ClientCommercialHistoryService;
use App\Services\ClientRecommendationService;
use App\Services\ServiceOrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCommercialHistoryTest extends TestCase
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

    public function test_product_with_repurchase_days_records_history_with_next_recommendation_date(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $seller = User::factory()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'name' => 'Óleo de barba',
            'price' => 50.00,
            'stock_quantity' => 10,
            'recommended_repurchase_days' => 120,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'seller_id' => $seller->id],
            ],
        ])->assertRedirect();

        $history = ClientCommercialHistory::query()->where('item_id', $product->id)->firstOrFail();

        $this->assertSame($company->id, $history->company_id);
        $this->assertSame($client->id, $history->client_id);
        $this->assertSame(ClientCommercialHistory::ITEM_TYPE_PRODUCT, $history->item_type);
        $this->assertSame('Óleo de barba', $history->item_name_snapshot);
        $this->assertSame('2.00', (string) $history->quantity);
        $this->assertSame('50.00', (string) $history->unit_price_snapshot);
        $this->assertSame('100.00', (string) $history->total_amount_snapshot);
        $this->assertSame($seller->id, $history->professional_id);
        $this->assertSame(120, $history->recommendation_days);
        $this->assertSame('2026-09-10', $history->next_recommendation_date->format('Y-m-d'));
    }

    public function test_product_without_repurchase_days_does_not_generate_future_recommendation(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 40.00,
            'stock_quantity' => 10,
            'recommended_repurchase_days' => null,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $history = ClientCommercialHistory::query()->where('item_id', $product->id)->firstOrFail();

        $this->assertNull($history->recommendation_days);
        $this->assertNull($history->next_recommendation_date);
        $this->assertTrue(app(ClientRecommendationService::class)
            ->getRecommendationsForClient($company->id, $client->id)
            ->isEmpty());
    }

    public function test_service_with_return_days_records_history_when_appointment_is_closed(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $professional = User::factory()->for($company)->create(['active' => true]);
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create([
            'name' => 'Hidratação',
            'price' => 90.00,
            'recommended_return_days' => 30,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $professional->id,
            'service_id' => $service->id,
            'start_time' => CarbonImmutable::parse('2026-05-01 09:00:00'),
            'end_time' => CarbonImmutable::parse('2026-05-01 10:00:00'),
        ]);

        $orders = app(ServiceOrderService::class);
        $order = $orders->ensureForAppointment($appointment);
        $orders->close($order, $admin, 'pix', null, CarbonImmutable::parse('2026-05-01 10:00:00'));

        $history = ClientCommercialHistory::query()
            ->where('item_type', ClientCommercialHistory::ITEM_TYPE_SERVICE)
            ->where('item_id', $service->id)
            ->firstOrFail();

        $this->assertSame($client->id, $history->client_id);
        $this->assertSame('Hidratação', $history->item_name_snapshot);
        $this->assertSame($professional->id, $history->professional_id);
        $this->assertSame($appointment->id, $history->appointment_id);
        $this->assertSame(30, $history->recommendation_days);
        $this->assertSame('2026-05-31', $history->next_recommendation_date->format('Y-m-d'));
    }

    public function test_recommendation_is_visible_when_due_and_upcoming_only_inside_last_seven_days(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $service = app(ClientRecommendationService::class);

        ClientCommercialHistory::factory()->for($company)->for($client)->product(10, 30)->create([
            'item_name_snapshot' => 'Pomada',
            'occurred_at' => CarbonImmutable::parse('2026-04-10 10:00:00'),
            'next_recommendation_date' => '2026-05-10',
        ]);
        ClientCommercialHistory::factory()->for($company)->for($client)->product(11, 30)->create([
            'item_name_snapshot' => 'Shampoo',
            'occurred_at' => CarbonImmutable::parse('2026-04-24 10:00:00'),
            'next_recommendation_date' => '2026-05-24',
        ]);
        ClientCommercialHistory::factory()->for($company)->for($client)->product(12, 30)->create([
            'item_name_snapshot' => 'Condicionador',
            'occurred_at' => CarbonImmutable::parse('2026-04-16 10:00:00'),
            'next_recommendation_date' => '2026-05-16',
        ]);

        $recommendations = $service->getRecommendationsForClient(
            $company->id,
            $client->id,
            CarbonImmutable::parse('2026-05-13')
        );

        $this->assertTrue($recommendations->pluck('item_name')->contains('Pomada'));
        $this->assertTrue($recommendations->pluck('item_name')->contains('Condicionador'));
        $this->assertFalse($recommendations->pluck('item_name')->contains('Shampoo'));
        $this->assertSame(ClientRecommendationService::STATUS_DUE, $recommendations->firstWhere('item_name', 'Pomada')['status']);
        $this->assertSame(ClientRecommendationService::STATUS_UPCOMING, $recommendations->firstWhere('item_name', 'Condicionador')['status']);
    }

    public function test_later_product_changes_do_not_corrupt_old_history_snapshots(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'name' => 'Óleo original',
            'price' => 60.00,
            'stock_quantity' => 10,
            'recommended_repurchase_days' => 90,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $product->update([
            'name' => 'Óleo novo',
            'price' => 120.00,
            'recommended_repurchase_days' => 10,
        ]);

        $history = ClientCommercialHistory::query()->where('item_id', $product->id)->firstOrFail();

        $this->assertSame('Óleo original', $history->item_name_snapshot);
        $this->assertSame('60.00', (string) $history->unit_price_snapshot);
        $this->assertSame(90, $history->recommendation_days);
    }

    public function test_service_history_is_not_duplicated_for_same_appointment_and_service(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $professional = User::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['recommended_return_days' => 21]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $professional->id,
            'service_id' => $service->id,
        ]);
        $order = app(ServiceOrderService::class)->ensureForAppointment($appointment);
        $history = app(ClientCommercialHistoryService::class);

        $history->recordServiceOrderServices($order);
        $history->recordServiceOrderServices($order);

        $this->assertSame(1, ClientCommercialHistory::query()
            ->where('appointment_id', $appointment->id)
            ->where('item_id', $service->id)
            ->count());
    }

    public function test_recommendations_respect_company_id(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $otherClient = Client::factory()->for($otherCompany)->create();

        ClientCommercialHistory::factory()->for($company)->for($client)->product(10, 30)->create([
            'item_name_snapshot' => 'Pomada certa',
            'occurred_at' => CarbonImmutable::parse('2026-04-01 10:00:00'),
            'next_recommendation_date' => '2026-05-01',
        ]);
        ClientCommercialHistory::factory()->for($otherCompany)->for($otherClient)->product(10, 30)->create([
            'item_name_snapshot' => 'Pomada externa',
            'occurred_at' => CarbonImmutable::parse('2026-04-01 10:00:00'),
            'next_recommendation_date' => '2026-05-01',
        ]);

        $recommendations = app(ClientRecommendationService::class)
            ->getRecommendationsForClient($company->id, $client->id, CarbonImmutable::parse('2026-05-13'));

        $this->assertTrue($recommendations->pluck('item_name')->contains('Pomada certa'));
        $this->assertFalse($recommendations->pluck('item_name')->contains('Pomada externa'));
    }

    public function test_canceled_sale_history_does_not_enter_active_opportunities(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();

        ClientCommercialHistory::factory()->for($company)->for($client)->product(10, 30)->create([
            'item_name_snapshot' => 'Produto cancelado',
            'occurred_at' => CarbonImmutable::parse('2026-04-01 10:00:00'),
            'next_recommendation_date' => '2026-05-01',
            'metadata' => ['status' => 'canceled'],
        ]);

        $this->assertTrue(app(ClientRecommendationService::class)
            ->getRecommendationsForClient($company->id, $client->id, CarbonImmutable::parse('2026-05-13'))
            ->isEmpty());
    }
}
