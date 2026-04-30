<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\ProfessionalDayOverride;
use App\Models\ProfessionalDayOverrideInterval;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_available_slots_respect_service_duration_and_existing_appointments(): void
    {
        CarbonImmutable::setTestNow('2026-04-15 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 4, '08:00', '18:00');
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
        $this->assertContains('17:30', $slots);
        $this->assertContains('17:00', $slots);
        $this->assertContains('18:00', $slots);
    }

    public function test_available_slots_for_total_duration_only_return_fully_free_blocks(): void
    {
        CarbonImmutable::setTestNow('2026-04-15 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 4, '08:00', '18:00');
        $client = Client::factory()->for($company)->create();

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => Service::factory()->for($company),
            'start_time' => '2026-04-16 10:00:00',
            'end_time' => '2026-04-16 11:00:00',
            'status' => 'scheduled',
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 90, '2026-04-16');

        $this->assertContains('08:00', $slots);
        $this->assertContains('08:30', $slots);
        $this->assertNotContains('09:00', $slots);
        $this->assertNotContains('09:30', $slots);
        $this->assertNotContains('10:00', $slots);
        $this->assertContains('11:00', $slots);
        $this->assertContains('17:00', $slots);
        $this->assertContains('16:30', $slots);
        $this->assertContains('17:30', $slots);
        $this->assertContains('18:00', $slots);
    }

    public function test_past_date_returns_no_slots(): void
    {
        CarbonImmutable::setTestNow('2026-04-16 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 3, '08:00', '18:00');

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-15');

        $this->assertSame([], $slots);
    }

    public function test_today_does_not_show_already_passed_slots(): void
    {
        CarbonImmutable::setTestNow('2026-04-16 09:05:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 4, '08:00', '18:00');

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-16');

        $this->assertNotContains('08:00', $slots);
        $this->assertNotContains('08:30', $slots);
        $this->assertNotContains('09:00', $slots);
        $this->assertNotContains('09:30', $slots);
        $this->assertContains('10:00', $slots);
    }

    public function test_today_respects_minimum_lead_time_before_first_safe_slot(): void
    {
        CarbonImmutable::setTestNow('2026-04-16 15:10:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 4, '08:00', '18:00');

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-16');

        $this->assertNotContains('15:00', $slots);
        $this->assertNotContains('15:30', $slots);
        $this->assertContains('16:00', $slots);
    }

    public function test_today_with_public_margin_returns_only_safe_future_slots_inside_the_shift(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-28 13:58:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $this->createOverrideIntervals($override, [
            ['start_time' => '14:00', 'end_time' => '20:00'],
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 75, '2026-04-28');

        $this->assertSame([
            '14:30',
            '15:00',
            '15:30',
            '16:00',
            '16:30',
            '17:00',
            '17:30',
            '18:00',
            '18:30',
            '19:00',
            '19:30',
            '20:00',
        ], $slots);
    }

    public function test_today_without_public_margin_can_return_the_first_slot_for_internal_use(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-28 13:58:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $this->createOverrideIntervals($override, [
            ['start_time' => '14:00', 'end_time' => '20:00'],
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 75, '2026-04-28', false);

        $this->assertContains('14:00', $slots);
        $this->assertContains('14:30', $slots);
        $this->assertContains('19:00', $slots);
        $this->assertContains('20:00', $slots);
    }

    public function test_future_date_keeps_normal_opening_window(): void
    {
        CarbonImmutable::setTestNow('2026-04-16 15:10:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 5, '08:00', '18:00');

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-17');

        $this->assertContains('08:00', $slots);
        $this->assertContains('09:00', $slots);
        $this->assertContains('17:00', $slots);
        $this->assertContains('17:30', $slots);
        $this->assertContains('18:00', $slots);
    }

    public function test_cancelled_other_user_and_other_company_appointments_do_not_block_slots(): void
    {
        CarbonImmutable::setTestNow('2026-04-15 10:00:00');

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 4, '08:00', '18:00');
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

    public function test_professional_with_two_intervals_in_same_day_returns_slots_in_both_blocks(): void
    {
        CarbonImmutable::setTestNow('2026-04-15 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 4, '08:00', '11:00');
        $this->createWorkingHour($company, $user, 4, '13:00', '22:00');

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-16');

        $this->assertContains('08:00', $slots);
        $this->assertContains('10:00', $slots);
        $this->assertContains('11:00', $slots);
        $this->assertNotContains('12:00', $slots);
        $this->assertContains('13:00', $slots);
        $this->assertContains('21:00', $slots);
        $this->assertContains('21:30', $slots);
        $this->assertContains('22:00', $slots);
    }

    public function test_tuesday_split_blocks_return_slots_only_inside_each_block(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 2, '09:30', '13:00');
        $this->createWorkingHour($company, $user, 2, '14:00', '20:00');

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-28');

        $this->assertContains('09:30', $slots);
        $this->assertContains('12:00', $slots);
        $this->assertContains('12:30', $slots);
        $this->assertContains('13:00', $slots);
        $this->assertNotContains('13:30', $slots);
        $this->assertContains('14:00', $slots);
        $this->assertContains('19:00', $slots);
        $this->assertContains('19:30', $slots);
        $this->assertContains('20:00', $slots);
    }

    public function test_day_off_override_removes_all_slots_for_the_day(): void
    {
        CarbonImmutable::setTestNow('2026-04-15 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 4, '08:00', '18:00');

        ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-16',
            'is_day_off' => true,
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-16');

        $this->assertSame([], $slots);
    }

    public function test_override_with_special_hours_replaces_weekly_schedule(): void
    {
        CarbonImmutable::setTestNow('2026-04-15 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 4, '08:00', '18:00');

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-16',
            'is_day_off' => false,
        ]);

        $this->createOverrideIntervals($override, [
            ['start_time' => '14:00', 'end_time' => '18:00'],
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-16');

        $this->assertNotContains('08:00', $slots);
        $this->assertNotContains('13:00', $slots);
        $this->assertContains('14:00', $slots);
        $this->assertContains('17:00', $slots);
        $this->assertContains('17:30', $slots);
        $this->assertContains('18:00', $slots);
    }

    public function test_date_specific_intervals_take_priority_over_weekly_schedule(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $this->createOverrideIntervals($override, [
            ['start_time' => '08:00', 'end_time' => '11:00'],
            ['start_time' => '13:00', 'end_time' => '19:00'],
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-28');

        $this->assertContains('08:00', $slots);
        $this->assertContains('10:00', $slots);
        $this->assertContains('13:00', $slots);
        $this->assertContains('18:00', $slots);
        $this->assertContains('11:00', $slots);
        $this->assertNotContains('12:00', $slots);
        $this->assertContains('19:00', $slots);
    }

    public function test_date_specific_split_intervals_return_all_valid_slots_for_total_duration(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $this->createOverrideIntervals($override, [
            ['start_time' => '11:00', 'end_time' => '13:00'],
            ['start_time' => '14:00', 'end_time' => '20:00'],
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 75, '2026-04-28');

        $this->assertSame([
            '11:00',
            '11:30',
            '12:00',
            '12:30',
            '13:00',
            '14:00',
            '14:30',
            '15:00',
            '15:30',
            '16:00',
            '16:30',
            '17:00',
            '17:30',
            '18:00',
            '18:30',
            '19:00',
            '19:30',
            '20:00',
        ], $slots);

        $this->assertNotContains('13:30', $slots);
    }

    public function test_tuesday_working_hour_returns_slots_for_tuesday_date(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->createWorkingHour($company, $user, 2, '08:00', '18:00');

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 60, '2026-04-28');

        $this->assertContains('08:00', $slots);
        $this->assertContains('10:00', $slots);
        $this->assertContains('17:00', $slots);
        $this->assertContains('17:30', $slots);
        $this->assertContains('18:00', $slots);
    }

    public function test_turn_end_time_is_also_an_accepted_arrival_slot_for_total_duration(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $this->createOverrideIntervals($override, [
            ['start_time' => '14:00', 'end_time' => '20:00'],
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 75, '2026-04-28');

        $this->assertContains('20:00', $slots);
    }

    public function test_short_turn_end_time_is_also_an_accepted_arrival_slot(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $this->createOverrideIntervals($override, [
            ['start_time' => '11:00', 'end_time' => '13:00'],
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 75, '2026-04-28');

        $this->assertContains('13:00', $slots);
    }

    public function test_conflicting_late_appointment_blocks_same_start_but_keeps_end_slot_when_free(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:00:00', 'America/Bahia'));

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
        ]);
        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $this->createOverrideIntervals($override, [
            ['start_time' => '14:00', 'end_time' => '20:00'],
        ]);

        Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-28 19:30:00',
            'end_time' => '2026-04-28 20:30:00',
            'status' => 'scheduled',
        ]);

        $slots = app(AvailabilityService::class)->availableSlotsForDuration($company, $user, 75, '2026-04-28');

        $this->assertNotContains('19:30', $slots);
        $this->assertNotContains('20:00', $slots);
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

    /**
     * @param  list<array{start_time: string, end_time: string}>  $intervals
     */
    private function createOverrideIntervals(ProfessionalDayOverride $override, array $intervals): void
    {
        foreach ($intervals as $interval) {
            ProfessionalDayOverrideInterval::create([
                'professional_day_override_id' => $override->id,
                'start_time' => $interval['start_time'],
                'end_time' => $interval['end_time'],
            ]);
        }
    }
}
