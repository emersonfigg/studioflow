<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\ProfessionalDayOverride;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_public_booking_page_renders_service_cards_professionals_and_available_slots(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        Storage::fake('public');

        $company = Company::factory()->create([
            'name' => 'Studio Flow',
            'instagram' => '@studioflowoficial',
            'description' => 'Barbearia completa com agendamento online e equipe especializada.',
        ]);
        Storage::disk('public')->put('companies/studio-flow-logo.jpg', 'studio-flow-logo');
        $company->update(['logo' => 'companies/studio-flow-logo.jpg']);
        Storage::disk('public')->put('services/corte-premium.jpg', 'corte-premium');

        $firstService = Service::factory()->for($company)->create([
            'name' => 'Corte Completo',
            'duration_minutes' => 60,
            'active' => true,
            'price' => 120.00,
            'image_path' => 'services/corte-premium.jpg',
        ]);
        $secondService = Service::factory()->for($company)->create([
            'name' => 'Barba Relax',
            'duration_minutes' => 30,
            'active' => true,
            'price' => 55.00,
        ]);

        Storage::disk('public')->put('professionals/ana-photo.jpg', 'ana-photo');
        $user = User::factory()->for($company)->create([
            'name' => 'Ana',
            'active' => true,
            'photo_path' => 'professionals/ana-photo.jpg',
        ]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        Service::factory()->for(Company::factory()->create())->create([
            'name' => 'Servico Externo',
            'active' => true,
        ]);

        $response = $this->get($this->bookingUrl($company, [
            'service_ids' => [$firstService->id, $secondService->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]));

        $response
            ->assertOk()
            ->assertSee('Corte Completo')
            ->assertSee('Barba Relax')
            ->assertSee('120,00')
            ->assertSee('55,00')
            ->assertSee('90 min')
            ->assertSee('/storage/services/corte-premium.jpg')
            ->assertSee('/storage/companies/studio-flow-logo.jpg')
            ->assertSee('@studioflowoficial')
            ->assertSee('Barbearia completa com agendamento online e equipe especializada.')
            ->assertSee('Ana')
            ->assertSee('Profissional')
            ->assertSee('/storage/professionals/ana-photo.jpg')
            ->assertSee('Pr&oacute;ximo hor&aacute;rio dispon&iacute;vel', false)
            ->assertSee('Hor&aacute;rio do atendimento', false)
            ->assertSee('Selecione um hor&aacute;rio', false)
            ->assertSee('09:00')
            ->assertSee('Confirmar agendamento')
            ->assertDontSee('Servico Externo');
    }

    public function test_public_booking_without_selected_service_does_not_show_slots(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        Service::factory()->for($company)->create([
            'name' => 'Corte',
            'duration_minutes' => 45,
            'active' => true,
            'price' => 70,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $override->intervals()->createMany([
            ['start_time' => '11:00', 'end_time' => '13:00'],
            ['start_time' => '14:00', 'end_time' => '20:00'],
        ]);

        $this->get($this->bookingUrl($company, [
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('Para carregar os horários da agenda real, selecione pelo menos um serviço e um profissional.')
            ->assertSee('Escolha os serviços')
            ->assertSee('0 min')
            ->assertSee('A definir')
            ->assertDontSee('11:00')
            ->assertDontSee('11:30')
            ->assertDontSee('20:00')
            ->assertDontSee('Horários estimados com duração padrão de 30 minutos. Escolha o serviço para confirmar.');
    }

    public function test_public_booking_lists_professionals_without_auto_selecting_or_showing_slots(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        Storage::fake('public');

        $company = Company::factory()->create();
        Service::factory()->for($company)->create([
            'duration_minutes' => 45,
            'active' => true,
        ]);

        $admin = User::factory()->for($company)->create([
            'name' => 'Admin',
            'active' => true,
            'photo_path' => null,
        ]);
        $this->createWorkingHour($company, $admin, 1, '08:00', '18:00');

        Storage::disk('public')->put('professionals/bianca.jpg', 'bianca-photo');
        $professional = User::factory()->for($company)->create([
            'name' => 'Bianca',
            'active' => true,
            'photo_path' => 'professionals/bianca.jpg',
        ]);

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $professional->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $override->intervals()->createMany([
            ['start_time' => '11:00', 'end_time' => '13:00'],
            ['start_time' => '14:00', 'end_time' => '20:00'],
        ]);

        $this->get($this->bookingUrl($company, [
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('Bianca')
            ->assertSee('/storage/professionals/bianca.jpg')
            ->assertSee('value="'.$professional->id.'"', false)
            ->assertDontSee('11:00')
            ->assertDontSee('value="'.$admin->id.'" checked', false);
    }

    public function test_public_booking_auto_selects_single_active_professional_but_waits_for_service(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        Service::factory()->for($company)->create([
            'duration_minutes' => 45,
            'active' => true,
        ]);
        $professional = User::factory()->for($company)->create([
            'name' => 'Joao teste',
            'active' => true,
        ]);
        $this->createWorkingHour($company, $professional, 2, '08:00', '18:00');

        $this->get($this->bookingUrl($company, [
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('value="'.$professional->id.'"', false)
            ->assertSee('checked', false)
            ->assertSee('O profissional Joao teste já está selecionado; falta escolher o serviço.')
            ->assertDontSee('08:00');
    }

    public function test_public_booking_shows_service_image_base_when_image_is_missing(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'name' => 'Barba Completa',
            'active' => true,
            'image_path' => null,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('Barba Completa')
            ->assertSee('Selecionado')
            ->assertDontSee('/storage/services/');
    }

    public function test_public_booking_shows_avatar_initial_when_professional_has_no_photo(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create(['active' => true]);
        $user = User::factory()->for($company)->create([
            'name' => 'Bruno',
            'active' => true,
            'photo_path' => null,
        ]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('Bruno')
            ->assertSee('B')
            ->assertSee('rounded-full', false)
            ->assertDontSee('/storage/professionals/');
    }

    public function test_public_booking_ignores_broken_photo_path_and_falls_back_to_initial(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create(['active' => true]);
        $user = User::factory()->for($company)->create([
            'name' => 'Caio',
            'active' => true,
            'photo_path' => 'professionals/arquivo-inexistente.webp',
        ]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('Caio')
            ->assertDontSee('/storage/professionals/arquivo-inexistente.webp')
            ->assertSee('C')
            ->assertSee('rounded-full', false);
    }

    public function test_public_booking_services_have_local_search_and_empty_state(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        Service::factory()->for($company)->create([
            'name' => 'Corte navalhado',
            'active' => true,
        ]);
        User::factory()->for($company)->create(['active' => true]);

        $this->get($this->bookingUrl($company))
            ->assertOk()
            ->assertSee('Buscar por nome do serviço')
            ->assertSee('Corte navalhado')
            ->assertSee('Nenhum serviço encontrado.');
    }

    public function test_public_booking_creates_appointment_and_items_with_selected_services(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $firstService = Service::factory()->for($company)->create([
            'name' => 'Corte Completo',
            'duration_minutes' => 60,
            'active' => true,
            'price' => 120.00,
        ]);
        $secondService = Service::factory()->for($company)->create([
            'name' => 'Barba Relax',
            'duration_minutes' => 30,
            'active' => true,
            'price' => 55.00,
        ]);
        $user = User::factory()->for($company)->create([
            'name' => 'Ana',
            'active' => true,
        ]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        $response = $this->post(route('public-bookings.store', $company, false), [
            'service_ids' => [$firstService->id, $secondService->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'time' => '09:00',
            'client_name' => 'Maria Souza',
            'client_phone' => '71999990000',
            'client_email' => 'maria@example.com',
            'notes' => 'Prefere atendimento pela manha.',
        ]);

        $appointment = Appointment::query()->where('company_id', $company->id)->firstOrFail();

        $response->assertRedirect(route('public-bookings.success', [
            'company' => $company,
            'appointment' => $appointment,
        ], false));

        $client = Client::query()->where('company_id', $company->id)->where('phone', '71999990000')->firstOrFail();

        $this->assertSame('Maria Souza', $client->name);
        $this->assertSame($client->id, $appointment->client_id);
        $this->assertSame($firstService->id, $appointment->service_id);
        $this->assertSame($user->id, $appointment->user_id);
        $this->assertSame('public_booking', $appointment->source);
        $this->assertSame('scheduled', $appointment->status);
        $this->assertSame('2026-04-28 09:00:00', $appointment->start_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-28 10:30:00', $appointment->end_time->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $firstService->id,
            'price_snapshot' => 120.00,
            'duration_snapshot' => 60,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $secondService->id,
            'price_snapshot' => 55.00,
            'duration_snapshot' => 30,
            'order' => 2,
        ]);
    }

    public function test_public_booking_blocks_client_with_overlapping_active_appointment(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 45,
            'active' => true,
        ]);
        $firstProfessional = User::factory()->for($company)->create(['active' => true]);
        $secondProfessional = User::factory()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create([
            'name' => 'Cliente Existente',
            'phone' => '71999990000',
            'email' => 'cliente@example.com',
        ]);
        $this->createWorkingHour($company, $firstProfessional, 2, '08:00', '18:00');
        $this->createWorkingHour($company, $secondProfessional, 2, '08:00', '18:00');

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $firstProfessional->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-28 09:00:00',
            'end_time' => '2026-04-28 09:45:00',
            'status' => 'scheduled',
        ]);

        $this
            ->from(route('public-bookings.create', $company, false))
            ->post(route('public-bookings.store', $company, false), [
                'service_ids' => [$service->id],
                'user_id' => $secondProfessional->id,
                'date' => '2026-04-28',
                'time' => '09:15',
                'client_name' => 'Cliente Existente',
                'client_phone' => '71999990000',
                'client_email' => 'cliente@example.com',
            ])
            ->assertRedirect(route('public-bookings.create', $company, false))
            ->assertSessionHasErrors([
                'time' => 'Você já possui um agendamento neste horário. Cancele ou escolha outro horário.',
            ]);

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_public_booking_hides_slots_when_identified_client_is_busy(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $targetProfessional = User::factory()->for($company)->create(['active' => true]);
        $otherProfessional = User::factory()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();
        $this->createWorkingHour($company, $targetProfessional, 2, '08:00', '18:00');

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $otherProfessional->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-28 09:00:00',
            'end_time' => '2026-04-28 10:00:00',
            'status' => 'scheduled',
        ]);

        $this->withSession(['public_booking_client_'.$company->id => $client->id])
            ->get($this->bookingUrl($company, [
                'service_ids' => [$service->id],
                'user_id' => $targetProfessional->id,
                'date' => '2026-04-28',
                'filters_submitted' => 1,
            ]))
            ->assertOk()
            ->assertSee('08:30')
            ->assertDontSee('09:00')
            ->assertDontSee('09:30')
            ->assertSee('10:00');
    }

    public function test_public_booking_google_creates_new_client_for_company(): void
    {
        config([
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect' => 'http://localhost/agendar/google/callback',
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token'], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-123',
                'name' => 'Cliente Google',
                'email' => 'cliente.google@example.com',
                'picture' => 'https://example.com/avatar.jpg',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $state = $this->googleStateFor($company, ['service_ids' => [1], 'user_id' => 2]);

        $this->get(route('public-bookings.google.callback', [
            'state' => $state,
            'code' => 'valid-code',
        ], false))
            ->assertRedirect(route('public-bookings.create', [
                'company' => $company,
                'service_ids' => [1],
                'user_id' => 2,
            ], false));

        $client = Client::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('Cliente Google', $client->name);
        $this->assertSame('cliente.google@example.com', $client->email);
        $this->assertSame('google-123', $client->google_id);
        $this->assertSame($client->id, session('public_booking_client_'.$company->id));
    }

    public function test_public_booking_google_links_existing_client_by_email(): void
    {
        config([
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect' => 'http://localhost/agendar/google/callback',
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token'], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-linked',
                'name' => 'Novo Nome Google',
                'email' => 'cliente.existente@example.com',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create([
            'name' => 'Cliente Existente',
            'email' => 'cliente.existente@example.com',
            'google_id' => null,
        ]);
        $state = $this->googleStateFor($company);

        $this->get(route('public-bookings.google.callback', [
            'state' => $state,
            'code' => 'valid-code',
        ], false))->assertRedirect();

        $client->refresh();

        $this->assertSame('Cliente Existente', $client->name);
        $this->assertSame('google-linked', $client->google_id);
        $this->assertSame(1, Client::query()->where('company_id', $company->id)->count());
    }

    public function test_public_booking_google_does_not_reuse_client_from_another_company(): void
    {
        config([
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect' => 'http://localhost/agendar/google/callback',
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token'], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-cross-company',
                'name' => 'Cliente Outra Empresa',
                'email' => 'mesmo.email@example.com',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        Client::factory()->for($otherCompany)->create([
            'email' => 'mesmo.email@example.com',
            'google_id' => null,
        ]);
        $state = $this->googleStateFor($company);

        $this->get(route('public-bookings.google.callback', [
            'state' => $state,
            'code' => 'valid-code',
        ], false))->assertRedirect();

        $this->assertSame(1, Client::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, Client::query()->where('company_id', $otherCompany->id)->count());
    }

    public function test_public_booking_revalidates_availability_inside_transaction_before_creating(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create([
            'active' => true,
        ]);

        $this->mock(AvailabilityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('availableSlotsForDuration')
                ->twice()
                ->andReturn(['09:00'], []);
        });

        $this->from(route('public-bookings.create', $company, false))
            ->post(route('public-bookings.store', $company, false), [
                'service_ids' => [$service->id],
                'user_id' => $user->id,
                'date' => '2026-04-28',
                'time' => '09:00',
                'client_name' => 'Paula',
                'client_phone' => '71977776666',
                'client_email' => 'paula@example.com',
            ])
            ->assertRedirect(route('public-bookings.create', $company, false))
            ->assertSessionHasErrors(['time']);

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_public_booking_confirmation_without_service_is_blocked(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['active' => true]);

        $this->from(route('public-bookings.create', $company, false))
            ->post(route('public-bookings.store', $company, false), [
                'user_id' => $user->id,
                'date' => '2026-04-28',
                'time' => '14:00',
                'client_name' => 'Paula',
                'client_phone' => '71999999999',
                'client_email' => 'paula@example.com',
            ])
            ->assertRedirect(route('public-bookings.create', $company, false))
            ->assertSessionHasErrors(['service_ids']);
    }

    public function test_public_booking_success_page_shows_complete_summary_for_multiple_services(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create(['name' => 'Studio Flow']);
        $firstService = Service::factory()->for($company)->create([
            'name' => 'Design de Sobrancelha',
            'duration_minutes' => 45,
            'active' => true,
            'price' => 80.00,
        ]);
        $secondService = Service::factory()->for($company)->create([
            'name' => 'Limpeza Facial',
            'duration_minutes' => 30,
            'active' => true,
            'price' => 60.00,
        ]);
        $user = User::factory()->for($company)->create([
            'name' => 'Bianca',
            'active' => true,
        ]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        $this->post(route('public-bookings.store', $company, false), [
            'service_ids' => [$firstService->id, $secondService->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'time' => '11:00',
            'client_name' => 'Carla',
            'client_phone' => '71991112222',
            'client_email' => 'carla@example.com',
            'notes' => 'Primeira visita.',
        ]);

        $appointment = Appointment::query()->where('company_id', $company->id)->firstOrFail();

        $this->get(route('public-bookings.success', [
            'company' => $company,
            'appointment' => $appointment,
        ], false))
            ->assertOk()
            ->assertSee('Agendamento confirmado')
            ->assertSee('Studio Flow')
            ->assertSee('Carla')
            ->assertSee('71991112222')
            ->assertSee('Design de Sobrancelha')
            ->assertSee('Limpeza Facial')
            ->assertSee('Bianca')
            ->assertSee('28/04/2026')
            ->assertSee('11:00')
            ->assertSee('12:15')
            ->assertSee('140,00')
            ->assertSee('75')
            ->assertSee('Agendado')
            ->assertSee('Agendar outro horário')
            ->assertSee('Chamar no WhatsApp');
    }

    public function test_public_booking_reuses_existing_client_with_same_phone_in_company(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');
        $client = Client::factory()->for($company)->create([
            'name' => 'Cliente Antiga',
            'phone' => '71988887777',
        ]);

        $this->post(route('public-bookings.store', $company, false), [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'time' => '10:30',
            'client_name' => 'Cliente Atualizada',
            'client_phone' => '71988887777',
            'client_email' => 'cliente@example.com',
        ])->assertSessionHasNoErrors();

        $client->refresh();
        $appointment = Appointment::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame(1, Client::query()->where('company_id', $company->id)->where('phone', '71988887777')->count());
        $this->assertSame('Cliente Atualizada', $client->name);
        $this->assertSame($client->id, $appointment->client_id);
    }

    public function test_public_booking_reuses_existing_client_with_same_email_in_company(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');
        $client = Client::factory()->for($company)->create([
            'name' => 'Cliente Antiga',
            'phone' => '71911112222',
            'email' => 'cliente.manual@example.com',
        ]);

        $this->post(route('public-bookings.store', $company, false), [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'time' => '10:30',
            'client_name' => 'Cliente Atualizada',
            'client_phone' => '71933334444',
            'client_email' => 'cliente.manual@example.com',
        ])->assertSessionHasNoErrors();

        $client->refresh();
        $appointment = Appointment::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame(1, Client::query()->where('company_id', $company->id)->where('email', 'cliente.manual@example.com')->count());
        $this->assertSame('Cliente Atualizada', $client->name);
        $this->assertSame('71933334444', $client->phone);
        $this->assertSame($client->id, $appointment->client_id);
    }

    public function test_public_booking_page_shows_only_company_data_and_uses_company_availability(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create(['name' => 'Studio Flow']);
        $otherCompany = Company::factory()->create(['name' => 'Outro Studio']);
        $service = Service::factory()->for($company)->create([
            'name' => 'Corte',
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create([
            'name' => 'Ana',
            'active' => true,
        ]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');
        $otherService = Service::factory()->for($otherCompany)->create([
            'name' => 'Procedimento Externo',
            'active' => true,
        ]);
        $otherUser = User::factory()->for($otherCompany)->create([
            'name' => 'Bruno',
            'active' => true,
        ]);
        $otherClient = Client::factory()->for($otherCompany)->create();

        Appointment::factory()->for($otherCompany)->create([
            'client_id' => $otherClient->id,
            'user_id' => $otherUser->id,
            'service_id' => $otherService->id,
            'start_time' => '2026-04-28 09:00:00',
            'end_time' => '2026-04-28 10:00:00',
            'status' => 'scheduled',
        ]);

        $response = $this->get($this->bookingUrl($company, [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]));

        $response
            ->assertOk()
            ->assertSee('Studio Flow')
            ->assertSee('Corte')
            ->assertSee('Ana')
            ->assertSee('60 min')
            ->assertSee('Confirmar agendamento')
            ->assertDontSee('Procedimento Externo')
            ->assertDontSee('Bruno')
            ->assertSee('09:00');
    }

    public function test_public_booking_recalculates_slots_when_services_are_selected(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $firstService = Service::factory()->for($company)->create([
            'duration_minutes' => 45,
            'active' => true,
        ]);
        $secondService = Service::factory()->for($company)->create([
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $override->intervals()->createMany([
            ['start_time' => '14:00', 'end_time' => '20:00'],
        ]);

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $firstService->id,
            'start_time' => '2026-04-28 14:45:00',
            'end_time' => '2026-04-28 15:15:00',
            'status' => 'scheduled',
        ]);

        $this->get($this->bookingUrl($company, [
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('Para carregar os horários da agenda real, selecione pelo menos um serviço e um profissional.')
            ->assertDontSee('14:00');

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$firstService->id, $secondService->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertDontSee('14:00')
            ->assertDontSee('Reservado')
            ->assertSee('15:30');
    }

    public function test_public_booking_reloads_slots_for_the_selected_professional(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 45,
            'active' => true,
        ]);
        $firstProfessional = User::factory()->for($company)->create([
            'name' => 'Carlos',
            'active' => true,
        ]);
        $secondProfessional = User::factory()->for($company)->create([
            'name' => 'Diego',
            'active' => true,
        ]);

        $firstOverride = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $firstProfessional->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);
        $firstOverride->intervals()->createMany([
            ['start_time' => '09:00', 'end_time' => '11:00'],
        ]);

        $secondOverride = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $secondProfessional->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);
        $secondOverride->intervals()->createMany([
            ['start_time' => '14:00', 'end_time' => '18:00'],
        ]);

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$service->id],
            'user_id' => $secondProfessional->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('14:00')
            ->assertSee('18:00')
            ->assertDontSee('09:00');
    }

    public function test_public_booking_page_shows_valid_slots_from_date_specific_override_for_total_duration(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $firstService = Service::factory()->for($company)->create([
            'duration_minutes' => 45,
            'active' => true,
        ]);
        $secondService = Service::factory()->for($company)->create([
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create([
            'active' => true,
        ]);

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $override->intervals()->createMany([
            ['start_time' => '11:00', 'end_time' => '13:00'],
            ['start_time' => '14:00', 'end_time' => '20:00'],
        ]);

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$firstService->id, $secondService->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('11:00')
            ->assertSee('11:30')
            ->assertSee('12:00')
            ->assertSee('12:30')
            ->assertSee('13:00')
            ->assertSee('14:00')
            ->assertSee('14:30')
            ->assertSee('15:00')
            ->assertSee('15:30')
            ->assertSee('16:00')
            ->assertSee('16:30')
            ->assertSee('17:00')
            ->assertSee('17:30')
            ->assertSee('18:00')
            ->assertSee('18:30')
            ->assertSee('19:00')
            ->assertSee('19:30')
            ->assertSee('20:00')
            ->assertDontSee('13:30')
            ->assertDontSee('20:30');
    }

    public function test_public_booking_page_does_not_show_past_slots_for_today(): void
    {
        CarbonImmutable::setTestNow('2026-04-28 09:10:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create([
            'active' => true,
        ]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertDontSee('08:00')
            ->assertDontSee('08:30')
            ->assertDontSee('09:00')
            ->assertDontSee('Passou')
            ->assertSee('09:20')
            ->assertSee('09:30')
            ->assertSee('10:00')
            ->assertSee('18:00');
    }

    public function test_public_booking_cannot_confirm_slot_inside_public_minimum_lead_time(): void
    {
        CarbonImmutable::setTestNow('2026-04-28 09:10:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        $this->from(route('public-bookings.create', $company, false))
            ->post(route('public-bookings.store', $company, false), [
                'service_ids' => [$service->id],
                'user_id' => $user->id,
                'date' => '2026-04-28',
                'time' => '09:15',
                'client_name' => 'Cliente Teste',
                'client_phone' => '71999990000',
                'client_email' => 'cliente.teste@example.com',
            ])
            ->assertRedirect(route('public-bookings.create', $company, false))
            ->assertSessionHasErrors(['time']);

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_public_booking_today_does_not_discount_thirty_minutes_from_professional_shift(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-29 13:11:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-29',
            'is_day_off' => false,
        ]);

        $override->intervals()->createMany([
            ['start_time' => '13:00', 'end_time' => '20:00'],
        ]);

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-29',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertDontSee('13:00')
            ->assertDontSee('Passou')
            ->assertSee('13:25')
            ->assertSee('13:30')
            ->assertSee('14:00')
            ->assertSee('19:30')
            ->assertSee('20:00');
    }

    public function test_public_booking_hides_reserved_slots_from_compact_selector(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-29 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-29',
            'is_day_off' => false,
        ]);

        $override->intervals()->createMany([
            ['start_time' => '13:00', 'end_time' => '20:00'],
        ]);

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-29 15:00:00',
            'end_time' => '2026-04-29 15:30:00',
            'status' => 'scheduled',
        ]);

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-29',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertDontSee('15:00')
            ->assertDontSee('Reservado')
            ->assertSee('14:30')
            ->assertSee('20:00');
    }

    public function test_public_booking_page_shows_clear_empty_message_when_no_slots_are_available(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');
        $client = Client::factory()->for($company)->create();

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-28 08:00:00',
            'end_time' => '2026-04-28 18:00:00',
            'status' => 'scheduled',
        ]);
        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-28 18:00:00',
            'end_time' => '2026-04-28 19:00:00',
            'status' => 'scheduled',
        ]);

        $this->get($this->bookingUrl($company, [
            'service_ids' => [$service->id],
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('Nenhum horário disponível para esta data.')
            ->assertDontSee('08:00')
            ->assertDontSee('Reservado')
            ->assertDontSee('18:00');
    }

    public function test_public_booking_cannot_use_foreign_company_service_or_unavailable_slot(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $secondService = Service::factory()->for($company)->create([
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $user = User::factory()->for($company)->create(['active' => true]);
        $otherService = Service::factory()->for($otherCompany)->create(['active' => true]);
        $otherUser = User::factory()->for($otherCompany)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-28 09:00:00',
            'end_time' => '2026-04-28 10:30:00',
            'status' => 'scheduled',
        ]);

        $this->from(route('public-bookings.create', $company, false))
            ->post(route('public-bookings.store', $company, false), [
                'service_ids' => [$otherService->id],
                'user_id' => $otherUser->id,
                'date' => '2026-04-28',
                'time' => '09:00',
                'client_name' => 'Paula',
                'client_phone' => '71977776666',
                'client_email' => 'paula@example.com',
            ])
            ->assertRedirect(route('public-bookings.create', $company, false))
            ->assertSessionHasErrors(['service_ids.0', 'user_id']);

        $this->from(route('public-bookings.create', $company, false))
            ->post(route('public-bookings.store', $company, false), [
                'service_ids' => [$service->id, $secondService->id],
                'user_id' => $user->id,
                'date' => '2026-04-28',
                'time' => '09:30',
                'client_name' => 'Paula',
                'client_phone' => '71977776666',
                'client_email' => 'paula@example.com',
            ])
            ->assertRedirect(route('public-bookings.create', $company, false))
            ->assertSessionHasErrors(['time']);

        $this->assertDatabaseCount('appointments', 1);
    }

    private function bookingUrl(Company $company, array $query = []): string
    {
        $baseUrl = route('public-bookings.create', $company, false);

        if ($query === []) {
            return $baseUrl;
        }

        return $baseUrl.'?'.http_build_query($query);
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

    private function googleStateFor(Company $company, array $query = []): string
    {
        $state = 'test-state-'.str()->random(8);
        session()->put('public_google_oauth_'.$state, [
            'company_id' => $company->id,
            'query' => $query,
        ]);

        return $state;
    }
}
