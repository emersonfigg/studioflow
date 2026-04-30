<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_own_company_profile(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create([
            'name' => 'Empresa Antiga',
        ]);
        $admin = User::factory()->admin()->for($company)->create();
        $logo = UploadedFile::fake()->image('logo.webp');

        $this
            ->actingAs($admin)
            ->get(route('company.edit', absolute: false))
            ->assertOk()
            ->assertSee('Dados da empresa')
            ->assertSee('Empresa Antiga');

        $this
            ->actingAs($admin)
            ->patch(route('company.update', absolute: false), [
                'name' => 'Barbearia do Joao',
                'phone' => '(71) 99999-0000',
                'address' => 'Rua Central, 123',
                'cnpj' => '12.345.678/0001-90',
                'instagram' => '@barbeariadojoao',
                'description' => 'Cortes premium e atendimento de bairro com acabamento fino.',
                'logo' => $logo,
            ])
            ->assertRedirect(route('company.edit', absolute: false));

        $company->refresh();

        $this->assertSame('Barbearia do Joao', $company->name);
        $this->assertSame('(71) 99999-0000', $company->phone);
        $this->assertSame('Rua Central, 123', $company->address);
        $this->assertSame('12.345.678/0001-90', $company->cnpj);
        $this->assertSame('@barbeariadojoao', $company->instagram);
        $this->assertSame('Cortes premium e atendimento de bairro com acabamento fino.', $company->description);
        $this->assertNotNull($company->logo);
        Storage::disk('public')->assertExists($company->logo);
    }

    public function test_admin_is_redirected_to_onboarding_when_company_setup_is_incomplete(): void
    {
        $company = Company::factory()->create([
            'onboarding_completed_at' => null,
        ]);
        $admin = User::factory()->admin()->for($company)->create();

        $this
            ->actingAs($admin)
            ->get(route('dashboard', absolute: false))
            ->assertRedirect(route('company.onboarding', absolute: false));
    }

    public function test_staff_cannot_access_company_settings(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();

        $this
            ->actingAs($staff)
            ->get(route('company.edit', absolute: false))
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->patch(route('company.update', absolute: false), [
                'name' => 'Nao Pode',
            ])
            ->assertForbidden();
    }
}
