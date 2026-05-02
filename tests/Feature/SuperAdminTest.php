<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_global_dashboard(): void
    {
        $superAdmin = User::factory()->superAdmin()->create([
            'email' => 'superadmin@studioflow.local',
        ]);

        $this
            ->actingAs($superAdmin)
            ->get(route('super-admin.dashboard', absolute: false))
            ->assertOk()
            ->assertSee('Painel Global');
    }

    public function test_company_admin_cannot_access_super_admin_area(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this
            ->actingAs($admin)
            ->get(route('super-admin.dashboard', absolute: false))
            ->assertForbidden();
    }

    public function test_staff_cannot_access_super_admin_area(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();

        $this
            ->actingAs($staff)
            ->get(route('super-admin.companies.index', absolute: false))
            ->assertForbidden();
    }

    public function test_inactive_company_blocks_common_user_login_and_usage(): void
    {
        $company = Company::factory()->create([
            'active' => false,
        ]);
        $admin = User::factory()->admin()->for($company)->create([
            'email' => 'admin@inactive.local',
        ]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'Sua empresa esta inativa. Entre em contato com o suporte do StudioFlow.',
        ]);

        $this->assertGuest();

        $response = $this
            ->actingAs($admin)
            ->get(route('profile.edit', absolute: false));

        $response->assertRedirect(route('login', absolute: false));
        $this->assertGuest();
    }

    public function test_super_admin_can_view_inactive_companies(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $inactiveCompany = Company::factory()->create([
            'name' => 'Empresa Inativa',
            'active' => false,
        ]);

        $this
            ->actingAs($superAdmin)
            ->get(route('super-admin.companies.index', absolute: false))
            ->assertOk()
            ->assertSee('Empresa Inativa')
            ->assertSee('Inativa');

        $this
            ->actingAs($superAdmin)
            ->get(route('super-admin.companies.show', $inactiveCompany, false))
            ->assertOk()
            ->assertSee('Empresa Inativa');
    }

    public function test_super_admin_can_enter_support_mode_for_company(): void
    {
        $superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Suporte Global',
        ]);
        $company = Company::factory()->create([
            'name' => 'Barbearia do Joao',
            'onboarding_completed_at' => now(),
        ]);
        $companyAdmin = User::factory()->admin()->for($company)->create([
            'name' => 'Joao Admin',
        ]);

        $this
            ->actingAs($superAdmin)
            ->post(route('super-admin.companies.support.start', $company, false))
            ->assertRedirect(route('dashboard', absolute: false));

        $this
            ->actingAs($superAdmin)
            ->withSession([
                'support_mode' => [
                    'original_user_id' => $superAdmin->id,
                    'company_id' => $company->id,
                    'user_id' => $companyAdmin->id,
                    'entered_at' => now()->toIso8601String(),
                ],
            ])
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertSee('Modo suporte ativo')
            ->assertSee('Barbearia do Joao')
            ->assertSee('Joao Admin');
    }

    public function test_super_admin_can_use_support_mode_with_inactive_company(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $company = Company::factory()->create([
            'active' => false,
            'name' => 'Empresa Inativa',
            'onboarding_completed_at' => now(),
        ]);
        $companyAdmin = User::factory()->admin()->for($company)->create();

        $this
            ->actingAs($superAdmin)
            ->withSession([
                'support_mode' => [
                    'original_user_id' => $superAdmin->id,
                    'company_id' => $company->id,
                    'user_id' => $companyAdmin->id,
                    'entered_at' => now()->toIso8601String(),
                ],
            ])
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertSee('Modo suporte ativo');
    }

    public function test_super_admin_can_stop_support_mode(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $company = Company::factory()->create();
        $companyAdmin = User::factory()->admin()->for($company)->create();

        $this
            ->actingAs($superAdmin)
            ->withSession([
                'support_mode' => [
                    'original_user_id' => $superAdmin->id,
                    'company_id' => $company->id,
                    'user_id' => $companyAdmin->id,
                    'entered_at' => now()->toIso8601String(),
                ],
            ])
            ->post(route('super-admin.support.stop', absolute: false))
            ->assertRedirect(route('super-admin.companies.show', $company, false));

        $this->assertFalse(session()->has('support_mode'));
    }

    public function test_company_admin_cannot_start_support_mode(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this
            ->actingAs($admin)
            ->post(route('super-admin.companies.support.start', $company, false))
            ->assertForbidden();
    }
}
