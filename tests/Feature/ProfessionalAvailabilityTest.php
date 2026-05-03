<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProfessionalDayOverride;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_save_date_configuration_with_two_blocks_for_the_same_day(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();

        $this
            ->actingAs($staff)
            ->put(route('schedule.update', ['month' => '2026-04', 'date' => '2026-04-28'], false), [
                'date' => '2026-04-28',
                'works_this_day' => '1',
                'intervals' => [
                    ['start_time' => '09:30', 'end_time' => '13:00'],
                    ['start_time' => '14:00', 'end_time' => '20:00'],
                ],
                'notes' => 'Agenda cheia após o almoço.',
            ])
            ->assertRedirect(route('schedule.edit', ['month' => '2026-04', 'date' => '2026-04-28'], false));

        $override = ProfessionalDayOverride::query()
            ->where('company_id', $company->id)
            ->where('user_id', $staff->id)
            ->whereDate('date', '2026-04-28')
            ->firstOrFail();

        $this->assertFalse($override->is_day_off);
        $this->assertSame('Agenda cheia após o almoço.', $override->notes);
        $this->assertDatabaseHas('professional_day_override_intervals', [
            'professional_day_override_id' => $override->id,
            'start_time' => '09:30',
            'end_time' => '13:00',
        ]);
        $this->assertDatabaseHas('professional_day_override_intervals', [
            'professional_day_override_id' => $override->id,
            'start_time' => '14:00',
            'end_time' => '20:00',
        ]);
    }

    public function test_selected_date_reopens_with_saved_configuration_visible(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
            'notes' => 'Horário especial.',
        ]);

        $override->intervals()->createMany([
            ['start_time' => '08:00', 'end_time' => '11:00'],
            ['start_time' => '13:00', 'end_time' => '19:00'],
        ]);

        $this
            ->actingAs($staff)
            ->get(route('schedule.edit', ['month' => '2026-04', 'date' => '2026-04-28'], false))
            ->assertOk()
            ->assertSee('28/04/2026')
            ->assertSee('Configurado')
            ->assertSee('value="08:00"', false)
            ->assertSee('value="11:00"', false)
            ->assertSee('value="13:00"', false)
            ->assertSee('value="19:00"', false)
            ->assertSee('Horário especial.');
    }

    public function test_marking_a_day_off_returns_no_slots_for_that_date(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);

        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'weekday' => 2,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'active' => true,
        ]);

        $this
            ->actingAs($staff)
            ->put(route('schedule.update', ['month' => '2026-04', 'date' => '2026-04-28'], false), [
                'date' => '2026-04-28',
                'works_this_day' => '0',
                'intervals' => [
                    ['start_time' => '', 'end_time' => ''],
                    ['start_time' => '', 'end_time' => ''],
                ],
            ])
            ->assertRedirect();

        $this->get(route('public-bookings.create', [
            'company' => $company,
            'service_ids' => [$service->id],
            'user_id' => $staff->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ], false))
            ->assertOk()
            ->assertSee('Nenhum horário disponível para esta data.');
    }

    public function test_clearing_a_day_configuration_falls_back_to_weekly_schedule(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);

        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'weekday' => 2,
            'start_time' => '09:30',
            'end_time' => '13:00',
            'active' => true,
        ]);
        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'weekday' => 2,
            'start_time' => '14:00',
            'end_time' => '20:00',
            'active' => true,
        ]);

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $override->intervals()->createMany([
            ['start_time' => '08:00', 'end_time' => '11:00'],
            ['start_time' => '13:00', 'end_time' => '19:00'],
        ]);

        $this
            ->actingAs($staff)
            ->delete(route('schedule.clear', ['month' => '2026-04', 'date' => '2026-04-28'], false), [
                'date' => '2026-04-28',
            ])
            ->assertRedirect(route('schedule.edit', ['month' => '2026-04', 'date' => '2026-04-28'], false));

        $this->assertDatabaseMissing('professional_day_overrides', [
            'id' => $override->id,
        ]);

        $this->get(route('public-bookings.create', [
            'company' => $company,
            'service_ids' => [$service->id],
            'user_id' => $staff->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ], false))
            ->assertOk()
            ->assertSee('09:30')
            ->assertSee('14:00')
            ->assertSee('13:00')
            ->assertSee('20:00')
            ->assertDontSee('08:00')
            ->assertDontSee('13:30');
    }

    public function test_staff_cannot_edit_schedule_of_another_professional(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        $otherProfessional = User::factory()->for($company)->create();

        $this
            ->actingAs($staff)
            ->get(route('team.availability.edit', ['team' => $otherProfessional, 'month' => '2026-04', 'date' => '2026-04-28'], false))
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->put(route('team.availability.update', ['team' => $otherProfessional, 'month' => '2026-04', 'date' => '2026-04-28'], false), [
                'date' => '2026-04-28',
                'works_this_day' => '1',
                'intervals' => [
                    ['start_time' => '08:00', 'end_time' => '11:00'],
                    ['start_time' => '13:00', 'end_time' => '19:00'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_edit_schedule_of_professional_in_same_company(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create();

        $this
            ->actingAs($admin)
            ->put(route('team.availability.update', ['team' => $professional, 'month' => '2026-04', 'date' => '2026-04-28'], false), [
                'date' => '2026-04-28',
                'works_this_day' => '1',
                'intervals' => [
                    ['start_time' => '08:00', 'end_time' => '11:00'],
                    ['start_time' => '13:00', 'end_time' => '19:00'],
                ],
                'notes' => 'Turno especial do dia.',
            ])
            ->assertRedirect(route('team.availability.edit', [
                'team' => $professional,
                'month' => '2026-04',
                'date' => '2026-04-28',
            ], false));

        $override = ProfessionalDayOverride::query()
            ->where('company_id', $company->id)
            ->where('user_id', $professional->id)
            ->whereDate('date', '2026-04-28')
            ->firstOrFail();

        $this->assertSame('Turno especial do dia.', $override->notes);
        $this->assertDatabaseHas('professional_day_override_intervals', [
            'professional_day_override_id' => $override->id,
            'start_time' => '08:00',
            'end_time' => '11:00',
        ]);
    }

    public function test_public_booking_respects_day_specific_configuration(): void
    {
        CarbonImmutable::setTestNow('2026-04-27 10:00:00');

        $company = Company::factory()->create();
        $professional = User::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'active' => true,
        ]);

        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $professional->id,
            'weekday' => 2,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'active' => true,
        ]);

        $override = ProfessionalDayOverride::create([
            'company_id' => $company->id,
            'user_id' => $professional->id,
            'date' => '2026-04-28',
            'is_day_off' => false,
        ]);

        $override->intervals()->createMany([
            ['start_time' => '08:00', 'end_time' => '11:00'],
            ['start_time' => '13:00', 'end_time' => '19:00'],
        ]);

        $this->get(route('public-bookings.create', [
            'company' => $company,
            'service_ids' => [$service->id],
            'user_id' => $professional->id,
            'date' => '2026-04-28',
            'filters_submitted' => 1,
        ], false))
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('10:00')
            ->assertSee('13:00')
            ->assertSee('18:00')
            ->assertSee('11:00')
            ->assertDontSee('12:00')
            ->assertSee('19:00');
    }
}
