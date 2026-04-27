<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_slots_respect_service_duration_and_existing_appointments(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
        ]);

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 09:00:00',
            'end_time' => '2026-04-16 10:00:00',
            'status' => 'scheduled',
        ]);

        $slots = app(AvailabilityService::class)->availableSlots($company, $user, $service, '2026-04-16');

        $this->assertContains('08:00', $slots);
        $this->assertNotContains('08:30', $slots);
        $this->assertNotContains('09:00', $slots);
        $this->assertNotContains('09:30', $slots);
        $this->assertContains('10:00', $slots);
        $this->assertContains('17:00', $slots);
        $this->assertNotContains('17:30', $slots);
    }

    public function test_cancelled_other_user_and_other_company_appointments_do_not_block_slots(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $otherUser = User::factory()->for($company)->create();
        $otherCompanyUser = User::factory()->for($otherCompany)->create();
        $client = Client::factory()->for($company)->create();
        $otherCompanyClient = Client::factory()->for($otherCompany)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
        ]);
        $otherCompanyService = Service::factory()->for($otherCompany)->create([
            'duration_minutes' => 60,
        ]);

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 11:00:00',
            'end_time' => '2026-04-16 12:00:00',
            'status' => 'cancelled',
        ]);
        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $otherUser->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-16 13:00:00',
            'end_time' => '2026-04-16 14:00:00',
            'status' => 'scheduled',
        ]);
        Appointment::factory()->for($otherCompany)->create([
            'client_id' => $otherCompanyClient->id,
            'user_id' => $otherCompanyUser->id,
            'service_id' => $otherCompanyService->id,
            'start_time' => '2026-04-16 15:00:00',
            'end_time' => '2026-04-16 16:00:00',
            'status' => 'scheduled',
        ]);

        $slots = app(AvailabilityService::class)->availableSlots($company, $user, $service, '2026-04-16');

        $this->assertContains('11:00', $slots);
        $this->assertContains('13:00', $slots);
        $this->assertContains('15:00', $slots);
    }

    public function test_mismatched_company_user_or_service_returns_no_slots(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($otherCompany)->create();
        $service = Service::factory()->for($company)->create();

        $slots = app(AvailabilityService::class)->availableSlots($company, $user, $service, '2026-04-16');

        $this->assertSame([], $slots);
    }
}
