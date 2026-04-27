<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_booking_creates_client_and_appointment(): void
    {
        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'name' => 'Corte Premium',
            'duration_minutes' => 60,
            'active' => true,
            'price' => 120.00,
        ]);
        $user = User::factory()->for($company)->create([
            'name' => 'Ana',
        ]);

        $response = $this->post(route('public-bookings.store', $company, false), [
            'service_id' => $service->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'time' => '09:00',
            'client_name' => 'Maria Souza',
            'client_phone' => '71999990000',
            'notes' => 'Prefere atendimento pela manhã.',
        ]);

        $appointment = Appointment::query()->where('company_id', $company->id)->firstOrFail();

        $response->assertRedirect(route('public-bookings.success', [
            'company' => $company,
            'appointment' => $appointment,
        ], false));

        $client = Client::query()->where('company_id', $company->id)->where('phone', '71999990000')->firstOrFail();

        $this->assertSame('Maria Souza', $client->name);
        $this->assertSame($client->id, $appointment->client_id);
        $this->assertSame($service->id, $appointment->service_id);
        $this->assertSame($user->id, $appointment->user_id);
        $this->assertSame('public_booking', $appointment->source);
        $this->assertSame('scheduled', $appointment->status);
        $this->assertSame('2026-04-28 09:00:00', $appointment->start_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-28 10:00:00', $appointment->end_time->format('Y-m-d H:i:s'));
    }

    public function test_public_booking_success_page_shows_complete_summary(): void
    {
        $company = Company::factory()->create(['name' => 'Studio Flow']);
        $service = Service::factory()->for($company)->create([
            'name' => 'Design de Sobrancelha',
            'duration_minutes' => 45,
            'active' => true,
            'price' => 80.00,
        ]);
        $user = User::factory()->for($company)->create(['name' => 'Bianca']);

        $this->post(route('public-bookings.store', $company, false), [
            'service_id' => $service->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'time' => '11:00',
            'client_name' => 'Carla',
            'client_phone' => '71991112222',
            'notes' => 'Primeira visita.',
        ]);

        $appointment = Appointment::query()->where('company_id', $company->id)->firstOrFail();

        $this->get(route('public-bookings.success', [
            'company' => $company,
            'appointment' => $appointment,
        ], false))
            ->assertOk()
            ->assertSee('Studio Flow')
            ->assertSee('Carla')
            ->assertSee('71991112222')
            ->assertSee('Design de Sobrancelha')
            ->assertSee('Bianca')
            ->assertSee('28/04/2026')
            ->assertSee('11:00')
            ->assertSee('11:45')
            ->assertSee('80,00')
            ->assertSee('45')
            ->assertSee('Agendado');
    }

    public function test_public_booking_reuses_existing_client_with_same_phone_in_company(): void
    {
        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create([
            'name' => 'Cliente Antiga',
            'phone' => '71988887777',
        ]);

        $this->post(route('public-bookings.store', $company, false), [
            'service_id' => $service->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'time' => '10:30',
            'client_name' => 'Cliente Atualizada',
            'client_phone' => '71988887777',
        ])->assertSessionHasNoErrors();

        $client->refresh();
        $appointment = Appointment::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame(1, Client::query()->where('company_id', $company->id)->where('phone', '71988887777')->count());
        $this->assertSame('Cliente Atualizada', $client->name);
        $this->assertSame($client->id, $appointment->client_id);
    }

    public function test_public_booking_page_shows_only_company_data_and_uses_company_availability(): void
    {
        $company = Company::factory()->create(['name' => 'Studio Flow']);
        $otherCompany = Company::factory()->create(['name' => 'Outro Studio']);
        $service = Service::factory()->for($company)->create([
            'name' => 'Corte',
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create(['name' => 'Ana']);
        $otherService = Service::factory()->for($otherCompany)->create([
            'name' => 'Procedimento Externo',
            'active' => true,
        ]);
        $otherUser = User::factory()->for($otherCompany)->create(['name' => 'Bruno']);
        $otherClient = Client::factory()->for($otherCompany)->create();

        Appointment::factory()->for($otherCompany)->create([
            'client_id' => $otherClient->id,
            'user_id' => $otherUser->id,
            'service_id' => $otherService->id,
            'start_time' => '2026-04-28 09:00:00',
            'end_time' => '2026-04-28 10:00:00',
            'status' => 'scheduled',
        ]);

        $response = $this->get(route('public-bookings.create', [
            'company' => $company,
            'service_id' => $service->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
        ], false));

        $response
            ->assertOk()
            ->assertSee('Studio Flow')
            ->assertSee('Corte')
            ->assertSee('Ana')
            ->assertSee('60')
            ->assertSee('Confirmar agendamento')
            ->assertDontSee('Procedimento Externo')
            ->assertDontSee('Bruno')
            ->assertSee('09:00');
    }

    public function test_public_booking_page_shows_clear_empty_message_when_no_slots_are_available(): void
    {
        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-28 08:00:00',
            'end_time' => '2026-04-28 18:00:00',
            'status' => 'scheduled',
        ]);

        $this->get(route('public-bookings.create', [
            'company' => $company,
            'service_id' => $service->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
        ], false))
            ->assertOk()
            ->assertSee('Nenhum horário disponível para essa data.');
    }

    public function test_public_booking_cannot_use_foreign_company_service_or_unavailable_slot(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create();
        $otherService = Service::factory()->for($otherCompany)->create([
            'active' => true,
        ]);
        $otherUser = User::factory()->for($otherCompany)->create();
        $client = Client::factory()->for($company)->create();

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-28 09:00:00',
            'end_time' => '2026-04-28 10:00:00',
            'status' => 'scheduled',
        ]);

        $this->from(route('public-bookings.create', $company, false))
            ->post(route('public-bookings.store', $company, false), [
                'service_id' => $otherService->id,
                'user_id' => $otherUser->id,
                'date' => '2026-04-28',
                'time' => '09:00',
                'client_name' => 'Paula',
                'client_phone' => '71977776666',
            ])
            ->assertRedirect(route('public-bookings.create', $company, false))
            ->assertSessionHasErrors(['service_id', 'user_id']);

        $this->from(route('public-bookings.create', $company, false))
            ->post(route('public-bookings.store', $company, false), [
                'service_id' => $service->id,
                'user_id' => $user->id,
                'date' => '2026-04-28',
                'time' => '09:30',
                'client_name' => 'Paula',
                'client_phone' => '71977776666',
            ])
            ->assertRedirect(route('public-bookings.create', $company, false))
            ->assertSessionHasErrors([
                'time' => 'Este horário não está mais disponível.',
            ]);

        $this->assertDatabaseCount('appointments', 1);
    }
}
