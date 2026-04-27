<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_public_booking_link_for_their_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create([
            'name' => 'Maria',
        ]);
        $service = Service::factory()->for($company)->create([
            'name' => 'Escova',
        ]);
        $otherClient = Client::factory()->for($otherCompany)->create();
        $otherService = Service::factory()->for($otherCompany)->create();
        $otherUser = User::factory()->for($otherCompany)->create();

        Carbon::setTestNow('2026-04-27 10:00:00');

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $user->id,
            'start_time' => '2026-04-27 14:00:00',
            'end_time' => '2026-04-27 15:00:00',
            'status' => 'scheduled',
        ]);

        Appointment::factory()->for($otherCompany)->create([
            'client_id' => $otherClient->id,
            'service_id' => $otherService->id,
            'user_id' => $otherUser->id,
            'start_time' => '2026-04-27 16:00:00',
            'end_time' => '2026-04-27 17:00:00',
            'status' => 'scheduled',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee(route('public-bookings.create', $company, false))
            ->assertDontSee(route('public-bookings.create', $otherCompany, false))
            ->assertSee('Agendamentos Hoje')
            ->assertSee('Próximos Atendimentos')
            ->assertSee('Clientes')
            ->assertSee('Serviços')
            ->assertSee('Copiar link')
            ->assertSee('Maria')
            ->assertSee('Escova')
            ->assertDontSee($otherClient->name);

        Carbon::setTestNow();
    }
}
