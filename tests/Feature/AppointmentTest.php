<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\ProfessionalDayOverride;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-04-15 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_appointment_end_time_is_calculated_from_service_duration_when_created(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 75,
        ]);
        $this->createWorkingHour($company, $admin, 4, '08:00', '18:00');

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
        $this->createWorkingHour($company, $admin, 4, '08:00', '18:00');
        $this->createWorkingHour($company, $admin, 4, '08:00', '18:00');
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
        $this->createWorkingHour($company, $admin, 4, '08:00', '18:00');

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
                'start_time' => 'Este horário não está disponível para a agenda real deste profissional.',
            ]);
    }

    public function test_internal_appointment_must_respect_professional_fixed_schedule(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
        ]);
        $this->createWorkingHour($company, $admin, 4, '08:00', '11:00');

        $this
            ->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 09:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($admin)
            ->from('/appointments/create')
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 14:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertRedirect('/appointments/create')
            ->assertSessionHasErrors(['start_time']);
    }

    public function test_internal_appointment_respects_dynamic_schedule_day_overrides(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create([
            'schedule_type' => 'dynamic',
        ]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 45,
        ]);
        $this->createWorkingHour($company, $admin, 4, '08:00', '18:00');

        $this
            ->actingAs($admin)
            ->from('/appointments/create')
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 09:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertRedirect('/appointments/create')
            ->assertSessionHasErrors(['start_time']);

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'date' => '2026-04-16',
            'is_day_off' => false,
        ]);
        $override->intervals()->create([
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this
            ->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 09:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertSessionHasNoErrors();
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
        $this->createWorkingHour($company, $admin, 4, '08:00', '18:00');

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
        $this->createWorkingHour($company, $admin, 4, '08:00', '18:00');
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
                'start_time' => 'Este horário não está disponível para a agenda real deste profissional.',
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

    public function test_new_appointment_page_shows_inline_client_creation_action(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        Client::factory()->for($company)->create();
        Service::factory()->for($company)->create();

        $this
            ->actingAs($staff)
            ->get('/appointments/create')
            ->assertOk()
            ->assertSee('Novo cliente')
            ->assertSee('Cadastre o cliente sem sair do novo agendamento.');
    }

    public function test_completed_appointment_show_page_displays_payment_actions_correctly(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create();
        $completedWithoutPayment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('appointments.show', $completedWithoutPayment, absolute: false))
            ->assertOk()
            ->assertSee('Voltar para agenda')
            ->assertSee('Novo agendamento')
            ->assertSee('Registrar pagamento')
            ->assertSee('Atendimento concluido')
            ->assertDontSee('Concluir atendimento');

        $completedWithPayment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        Payment::factory()->create([
            'company_id' => $company->id,
            'appointment_id' => $completedWithPayment->id,
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('appointments.show', $completedWithPayment, absolute: false))
            ->assertOk()
            ->assertSee('Pagamento registrado')
            ->assertDontSee('Registrar pagamento')
            ->assertDontSee('Concluir atendimento');
    }

    private function createWorkingHour(Company $company, User $user, int $weekday, string $startTime, string $endTime): void
    {
        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'weekday' => $weekday,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'active' => true,
        ]);
    }
}
