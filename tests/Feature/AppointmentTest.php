<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_end_time_is_calculated_from_service_duration_when_created(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 75,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 10:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
                'notes' => 'Created from test.',
            ]);

        $appointment = Appointment::where('client_id', $client->id)->firstOrFail();

        $response->assertRedirect(route('appointments.show', $appointment, absolute: false));
        $this->assertSame('2026-04-16 10:00:00', $appointment->start_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-16 11:15:00', $appointment->end_time->format('Y-m-d H:i:s'));
    }

    public function test_appointment_end_time_is_recalculated_when_updated(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 45,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 09:00:00',
            'end_time' => '2026-04-16 09:45:00',
        ]);

        $this
            ->actingAs($admin)
            ->patch("/appointments/{$appointment->id}", [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 14:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertRedirect(route('appointments.show', $appointment, absolute: false));

        $appointment->refresh();

        $this->assertSame('2026-04-16 14:00:00', $appointment->start_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-16 14:45:00', $appointment->end_time->format('Y-m-d H:i:s'));
    }

    public function test_appointment_cannot_overlap_same_user_in_same_company(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
        ]);

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 10:00:00',
            'end_time' => '2026-04-16 11:00:00',
            'status' => 'scheduled',
        ]);

        $this
            ->actingAs($admin)
            ->from('/appointments/create')
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 10:30:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertRedirect('/appointments/create')
            ->assertSessionHasErrors([
                'start_time' => 'Este profissional já possui um agendamento nesse horário.',
            ]);
    }

    public function test_adjacent_cancelled_other_user_and_other_company_appointments_do_not_conflict(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $otherUser = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $otherCompanyClient = Client::factory()->for($otherCompany)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
        ]);
        $otherCompanyService = Service::factory()->for($otherCompany)->create([
            'duration_minutes' => 60,
        ]);
        $otherCompanyUser = User::factory()->for($otherCompany)->create();

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 10:00:00',
            'end_time' => '2026-04-16 11:00:00',
            'status' => 'scheduled',
        ]);
        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 12:00:00',
            'end_time' => '2026-04-16 13:00:00',
            'status' => 'cancelled',
        ]);
        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $otherUser->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 14:00:00',
            'end_time' => '2026-04-16 15:00:00',
            'status' => 'scheduled',
        ]);
        Appointment::factory()->for($otherCompany)->create([
            'client_id' => $otherCompanyClient->id,
            'user_id' => $otherCompanyUser->id,
            'service_id' => $otherCompanyService->id,
            'start_time' => '2026-04-16 16:00:00',
            'end_time' => '2026-04-16 17:00:00',
            'status' => 'scheduled',
        ]);

        foreach ([
            '2026-04-16 11:00:00',
            '2026-04-16 12:30:00',
            '2026-04-16 14:30:00',
            '2026-04-16 16:30:00',
        ] as $startTime) {
            $this
                ->actingAs($admin)
                ->post('/appointments', [
                    'client_id' => $client->id,
                    'user_id' => $admin->id,
                    'service_id' => $service->id,
                    'start_time' => $startTime,
                    'status' => 'scheduled',
                    'source' => 'internal',
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('appointments', 8);
    }

    public function test_update_ignores_own_appointment_but_blocks_other_conflicting_appointment(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 10:00:00',
            'end_time' => '2026-04-16 11:00:00',
            'status' => 'scheduled',
        ]);
        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 12:00:00',
            'end_time' => '2026-04-16 13:00:00',
            'status' => 'scheduled',
        ]);

        $this
            ->actingAs($admin)
            ->patch("/appointments/{$appointment->id}", [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 10:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($admin)
            ->from("/appointments/{$appointment->id}/edit")
            ->patch("/appointments/{$appointment->id}", [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 12:30:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertRedirect("/appointments/{$appointment->id}/edit")
            ->assertSessionHasErrors([
                'start_time' => 'Este profissional já possui um agendamento nesse horário.',
            ]);
    }

    public function test_company_user_can_update_appointment_status_quickly(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create();
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $staff->id,
            'service_id' => $service->id,
            'status' => 'scheduled',
        ]);

        $this
            ->actingAs($staff)
            ->patch("/appointments/{$appointment->id}/status", [
                'status' => 'confirmed',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);
    }
}
