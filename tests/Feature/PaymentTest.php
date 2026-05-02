<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_payment_for_an_appointment(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'price' => 95.00,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $admin->id,
            'status' => 'in_progress',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('appointments.payments.store', $appointment, false), [
                'gross_amount' => '95.00',
                'payment_method' => 'pix',
                'notes' => 'Recebido no caixa.',
            ])
            ->assertRedirect(route('appointments.show', $appointment, false));

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'company_id' => $company->id,
            'payment_method' => 'pix',
            'gross_amount' => '95.00',
            'commission_amount' => '0.00',
            'net_amount' => '95.00',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('cash_movements', [
            'company_id' => $company->id,
            'type' => 'inflow',
            'source_type' => 'App\\Models\\Payment',
            'amount' => '95.00',
        ]);
    }

    public function test_percent_commission_is_calculated_correctly(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create([
            'commission_type' => 'percent',
            'commission_value' => 40,
        ]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create();
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $professional->id,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('appointments.payments.store', $appointment, false), [
                'gross_amount' => '120.00',
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('appointments.show', $appointment, false));

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'commission_type' => 'percent',
            'commission_rate' => '40.00',
            'commission_amount' => '48.00',
            'net_amount' => '72.00',
        ]);
    }

    public function test_admin_can_register_payment_with_products_in_same_closing_flow(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'price' => 40.00,
        ]);
        $product = Product::factory()->for($company)->create([
            'name' => 'Pomada Modeladora',
            'price' => 25.00,
            'active' => true,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $admin->id,
            'status' => 'in_progress',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('appointments.payments.store', $appointment, false), [
                'gross_amount' => '40.00',
                'payment_method' => 'card',
                'notes' => 'Servico com produto.',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect(route('appointments.show', $appointment, false));

        $sale = ProductSale::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->assertDatabaseHas('product_sales', [
            'id' => $sale->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'gross_amount' => '50.00',
            'payment_method' => 'card',
        ]);

        $this->assertDatabaseHas('product_sale_items', [
            'product_sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'total_price' => '50.00',
        ]);

        $this->assertDatabaseHas('cash_movements', [
            'company_id' => $company->id,
            'type' => 'inflow',
            'source_type' => 'App\\Models\\Payment',
            'amount' => '40.00',
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'company_id' => $company->id,
            'type' => 'inflow',
            'source_type' => 'App\\Models\\ProductSale',
            'source_id' => $sale->id,
            'amount' => '50.00',
        ]);
    }

    public function test_fixed_commission_is_calculated_correctly(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create([
            'commission_type' => 'fixed',
            'commission_value' => 35,
        ]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create();
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $professional->id,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('appointments.payments.store', $appointment, false), [
                'gross_amount' => '120.00',
                'payment_method' => 'card',
            ])
            ->assertRedirect(route('appointments.show', $appointment, false));

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'commission_type' => 'fixed',
            'commission_rate' => null,
            'commission_amount' => '35.00',
            'net_amount' => '85.00',
        ]);
    }

    public function test_duplicate_payment_is_not_allowed_for_the_same_appointment(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create();
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $admin->id,
        ]);

        Payment::factory()->create([
            'company_id' => $company->id,
            'appointment_id' => $appointment->id,
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
        ]);

        $this
            ->actingAs($admin)
            ->from(route('appointments.payments.create', $appointment, false))
            ->post(route('appointments.payments.store', $appointment, false), [
                'gross_amount' => '80.00',
                'payment_method' => 'pix',
            ])
            ->assertSessionHasErrors([
                'payment_method' => 'Este atendimento ja possui pagamento registrado.',
            ]);

        $this->assertSame(1, Payment::count());
    }

    public function test_staff_sees_only_their_own_production(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create([
            'name' => 'Carlos',
        ]);
        $otherStaff = User::factory()->for($company)->create([
            'name' => 'Joao',
        ]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create();

        $staffAppointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $staff->id,
            'status' => 'completed',
        ]);
        $otherAppointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $otherStaff->id,
            'status' => 'completed',
        ]);

        Payment::factory()->create([
            'company_id' => $company->id,
            'appointment_id' => $staffAppointment->id,
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => '100.00',
            'net_amount' => '70.00',
        ]);
        Payment::factory()->create([
            'company_id' => $company->id,
            'appointment_id' => $otherAppointment->id,
            'user_id' => $otherStaff->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => '150.00',
            'net_amount' => '110.00',
        ]);

        $response = $this
            ->actingAs($staff)
            ->get(route('production.index', ['user_id' => $otherStaff->id], false));

        $response
            ->assertOk()
            ->assertSee('Carlos')
            ->assertDontSee('Joao')
            ->assertSee('R$ 100,00')
            ->assertDontSee('R$ 150,00');
    }

    public function test_company_cannot_access_payment_flow_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $otherProfessional = User::factory()->for($otherCompany)->create();
        $otherClient = Client::factory()->for($otherCompany)->create();
        $otherService = Service::factory()->for($otherCompany)->create();
        $otherAppointment = Appointment::factory()->for($otherCompany)->create([
            'client_id' => $otherClient->id,
            'service_id' => $otherService->id,
            'user_id' => $otherProfessional->id,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('appointments.payments.create', $otherAppointment, false))
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->post(route('appointments.payments.store', $otherAppointment, false), [
                'gross_amount' => '90.00',
                'payment_method' => 'pix',
            ])
            ->assertNotFound();
    }

    public function test_admin_can_register_commission_settlement(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create();

        $appointmentA = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $professional->id,
            'status' => 'completed',
        ]);
        $appointmentB = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $professional->id,
            'status' => 'completed',
        ]);

        $paymentA = Payment::factory()->create([
            'company_id' => $company->id,
            'appointment_id' => $appointmentA->id,
            'user_id' => $professional->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => '100.00',
            'commission_amount' => '40.00',
            'paid_at' => '2026-04-10 10:00:00',
        ]);
        $paymentB = Payment::factory()->create([
            'company_id' => $company->id,
            'appointment_id' => $appointmentB->id,
            'user_id' => $professional->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => '80.00',
            'commission_amount' => '32.00',
            'paid_at' => '2026-04-12 11:00:00',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('finance.commissions.settlements.store', absolute: false), [
                'user_id' => $professional->id,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'payment_method' => 'pix',
                'notes' => 'Repasse da primeira quinzena.',
            ])
            ->assertRedirect(route('finance.commissions', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
                'user_id' => $professional->id,
            ], false));

        $settlement = CommissionSettlement::firstOrFail();

        $this->assertDatabaseHas('commission_settlements', [
            'id' => $settlement->id,
            'company_id' => $company->id,
            'user_id' => $professional->id,
            'gross_amount' => '180.00',
            'commission_amount' => '72.00',
            'payment_method' => 'pix',
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('commission_settlement_payment', [
            'commission_settlement_id' => $settlement->id,
            'payment_id' => $paymentA->id,
        ]);
        $this->assertDatabaseHas('commission_settlement_payment', [
            'commission_settlement_id' => $settlement->id,
            'payment_id' => $paymentB->id,
        ]);
    }

    public function test_settled_payments_do_not_appear_again_as_pending_and_recent_settlement_is_visible(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create([
            'name' => 'Carlos',
        ]);
        $client = Client::factory()->for($company)->create([
            'name' => 'Marcos',
        ]);
        $service = Service::factory()->for($company)->create([
            'name' => 'Corte Completo',
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $professional->id,
            'status' => 'completed',
        ]);
        $payment = Payment::factory()->create([
            'company_id' => $company->id,
            'appointment_id' => $appointment->id,
            'user_id' => $professional->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => '120.00',
            'commission_amount' => '48.00',
            'paid_at' => '2026-04-18 10:00:00',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('finance.commissions.settlements.store', absolute: false), [
                'user_id' => $professional->id,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $response = $this
            ->actingAs($admin)
            ->get(route('finance.commissions', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
                'user_id' => $professional->id,
            ], false));

        $response
            ->assertOk()
            ->assertSee('R$ 0,00')
            ->assertSee('Últimos repasses')
            ->assertSee('Carlos')
            ->assertSee('Dinheiro')
            ->assertDontSee('Fazer acerto');

        $this->assertDatabaseHas('commission_settlement_payment', [
            'payment_id' => $payment->id,
        ]);
    }

    public function test_same_payment_cannot_be_settled_twice(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create();
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
            'gross_amount' => '90.00',
            'commission_amount' => '30.00',
            'paid_at' => '2026-04-16 10:00:00',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('finance.commissions.settlements.store', absolute: false), [
                'user_id' => $professional->id,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'payment_method' => 'pix',
            ])
            ->assertRedirect();

        $this
            ->actingAs($admin)
            ->post(route('finance.commissions.settlements.store', absolute: false), [
                'user_id' => $professional->id,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'payment_method' => 'pix',
            ])
            ->assertStatus(422);

        $this->assertSame(1, CommissionSettlement::count());
    }

    public function test_staff_cannot_register_commission_settlement(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        $professional = User::factory()->for($company)->create();

        $this
            ->actingAs($staff)
            ->get(route('finance.commissions.settlements.create', [
                'user_id' => $professional->id,
                'from' => '2026-04-01',
                'to' => '2026-04-30',
            ], false))
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->post(route('finance.commissions.settlements.store', absolute: false), [
                'user_id' => $professional->id,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'payment_method' => 'pix',
            ])
            ->assertForbidden();
    }

    public function test_company_cannot_access_commission_settlement_for_other_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $otherProfessional = User::factory()->for($otherCompany)->create();

        $this
            ->actingAs($admin)
            ->get(route('finance.commissions.settlements.create', [
                'user_id' => $otherProfessional->id,
                'from' => '2026-04-01',
                'to' => '2026-04-30',
            ], false))
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->post(route('finance.commissions.settlements.store', absolute: false), [
                'user_id' => $otherProfessional->id,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'payment_method' => 'pix',
            ])
            ->assertNotFound();
    }
}
