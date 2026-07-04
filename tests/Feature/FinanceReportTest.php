<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\ProductSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_shows_service_product_and_settlement_values_by_date(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create([
            'commission_type' => 'percent',
            'commission_value' => 40,
        ]);
        $client = Client::factory()->for($company)->create([
            'name' => 'Marcos',
        ]);
        $service = Service::factory()->for($company)->create([
            'name' => 'Corte Executivo',
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $professional->id,
            'status' => 'completed',
        ]);
        Payment::factory()->create([
            'company_id' => $company->id,
            'appointment_id' => $appointment->id,
            'user_id' => $professional->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => '100.00',
            'commission_amount' => '40.00',
            'net_amount' => '60.00',
            'paid_at' => '2026-04-30 09:00:00',
        ]);

        $product = Product::factory()->for($company)->create([
            'name' => 'Pomada Black',
            'price' => 50.00,
        ]);

        app(ProductSaleService::class)->register($admin, [
            'client_id' => $client->id,
            'user_id' => $professional->id,
            'payment_method' => 'pix',
            'sold_at' => '2026-04-30 10:00:00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('finance.commissions.settlements.store', absolute: false), [
                'user_id' => $professional->id,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('finance.report', [
                'from' => '2026-04-30',
                'to' => '2026-04-30',
            ], false))
            ->assertOk()
            ->assertSee('R$ 100,00')
            ->assertSee('R$ 50,00')
            ->assertSee('R$ 150,00')
            ->assertSee('Acertos com profissionais')
            ->assertSee('Pomada Black');
    }

    public function test_report_counts_new_clients_inside_selected_period(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        Client::factory()->for($company)->create([
            'name' => 'Cliente Abril',
            'phone' => '71911110000',
            'cpf' => '111.222.333-44',
            'created_at' => '2026-04-10 09:00:00',
        ]);
        Client::factory()->for($company)->create([
            'name' => 'Cliente Maio',
            'created_at' => '2026-05-01 09:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('finance.report', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
            ], false))
            ->assertOk()
            ->assertSee('Novos clientes')
            ->assertSee('Cliente Abril')
            ->assertSee('71911110000')
            ->assertSee('111.222.333-44')
            ->assertDontSee('Cliente Maio');
    }
}
