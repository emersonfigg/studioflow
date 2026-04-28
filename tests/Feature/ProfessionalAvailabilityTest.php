<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProfessionalDayOverride;
use App\Models\ProfessionalWorkingHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_update_their_own_schedule(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();

        $this
            ->actingAs($staff)
            ->put(route('schedule.update', absolute: false), [
                'working_hours' => [
                    ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '11:00'],
                    ['weekday' => 1, 'start_time' => '13:00', 'end_time' => '22:00'],
                ],
                'overrides' => [
                    ['date' => '2026-05-01', 'is_day_off' => '1', 'start_time' => '', 'end_time' => '', 'notes' => 'Feriado'],
                ],
            ])
            ->assertRedirect(route('schedule.edit', absolute: false));

        $this->assertDatabaseHas('professional_working_hours', [
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '11:00',
            'active' => true,
        ]);
        $this->assertDatabaseHas('professional_working_hours', [
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'weekday' => 1,
            'start_time' => '13:00',
            'end_time' => '22:00',
            'active' => true,
        ]);
        $this->assertDatabaseHas('professional_day_overrides', [
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'date' => '2026-05-01',
            'is_day_off' => true,
        ]);
    }

    public function test_staff_cannot_edit_schedule_of_another_professional(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        $otherProfessional = User::factory()->for($company)->create();

        $this
            ->actingAs($staff)
            ->get(route('team.availability.edit', $otherProfessional, false))
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->put(route('team.availability.update', $otherProfessional, false), [
                'working_hours' => [
                    ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '18:00'],
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
            ->put(route('team.availability.update', $professional, false), [
                'working_hours' => [
                    ['weekday' => 2, 'start_time' => '09:00', 'end_time' => '17:00'],
                ],
                'overrides' => [
                    ['date' => '2026-05-02', 'is_day_off' => '0', 'start_time' => '12:00', 'end_time' => '18:00', 'notes' => 'Evento'],
                ],
            ])
            ->assertRedirect(route('team.availability.edit', $professional, false));

        $this->assertDatabaseHas('professional_working_hours', [
            'company_id' => $company->id,
            'user_id' => $professional->id,
            'weekday' => 2,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $this->assertDatabaseHas('professional_day_overrides', [
            'company_id' => $company->id,
            'user_id' => $professional->id,
            'date' => '2026-05-02',
            'is_day_off' => false,
            'start_time' => '12:00',
            'end_time' => '18:00',
        ]);
    }

    public function test_other_company_cannot_access_or_edit_professional_schedule(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $otherProfessional = User::factory()->for($otherCompany)->create();

        $this
            ->actingAs($admin)
            ->get(route('team.availability.edit', $otherProfessional, false))
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->put(route('team.availability.update', $otherProfessional, false), [
                'working_hours' => [
                    ['weekday' => 2, 'start_time' => '09:00', 'end_time' => '17:00'],
                ],
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('professional_working_hours', 0);
        $this->assertDatabaseCount('professional_day_overrides', 0);
    }
}
