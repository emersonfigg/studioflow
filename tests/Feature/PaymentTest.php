<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
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
}
