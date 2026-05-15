<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\User;
use App\Services\BrandingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_valid_hex_colors(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->patch(route('company.update', absolute: false), [
                'name' => $company->name,
                'primary_color' => '#aabbcc',
                'secondary_color' => '#112233',
                'accent_color' => '#445566',
                'brand_enabled' => '1',
            ])
            ->assertRedirect(route('company.edit', absolute: false));

        $company->refresh();
        $this->assertSame('#AABBCC', $company->primary_color);
        $this->assertSame('#112233', $company->secondary_color);
        $this->assertSame('#445566', $company->accent_color);
    }

    public function test_invalid_hex_color_is_rejected(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->patch(route('company.update', absolute: false), [
                'name' => $company->name,
                'primary_color' => '#gggggg',
                'brand_enabled' => '1',
            ])
            ->assertSessionHasErrors('primary_color');
    }

    public function test_logo_upload_stores_relative_path(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create(['logo' => null]);
        $admin = User::factory()->admin()->for($company)->create();
        $file = UploadedFile::fake()->image('logo.png', 120, 120);

        $this->actingAs($admin)
            ->patch(route('company.update', absolute: false), [
                'name' => $company->name,
                'logo' => $file,
                'brand_enabled' => '1',
            ])
            ->assertRedirect(route('company.edit', absolute: false));

        $company->refresh();
        $this->assertNotNull($company->logo);
        $this->assertStringNotContainsString('http', $company->logo);
        Storage::disk('public')->assertExists($company->logo);
    }

    public function test_company_without_branding_uses_fallback_css_variables(): void
    {
        $company = Company::factory()->create([
            'primary_color' => null,
            'secondary_color' => null,
            'accent_color' => null,
            'logo' => null,
        ]);

        $branding = app(BrandingService::class)->getCurrentCompanyBranding($company);

        $this->assertSame(BrandingService::DEFAULT_PRIMARY, $branding['primary']);
        $this->assertNull($branding['logo_url']);
        $this->assertStringContainsString('--brand-primary', $branding['root_style']);
    }

    public function test_red_primary_hex_appears_in_company_edit_and_public_booking_html(): void
    {
        $company = Company::factory()->create([
            'onboarding_completed_at' => now(),
            'primary_color' => '#ff0000',
            'secondary_color' => '#1a1a1a',
            'accent_color' => '#0d0d0d',
            'brand_enabled' => true,
        ]);
        $admin = User::factory()->admin()->for($company)->create();

        $companyPage = $this->actingAs($admin)
            ->get(route('company.edit', absolute: false))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/#ff0000/i', $companyPage);
        $this->assertStringContainsString('--brand-primary:', $companyPage);
        $this->assertStringContainsString('--brand-secondary: #1A1A1A', $companyPage);
        $this->assertStringContainsString('--brand-accent: #0D0D0D', $companyPage);
        $this->assertStringContainsString('brand-cta', $companyPage);

        $professional = User::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create([
            'active' => true,
            'duration_minutes' => 30,
            'price' => 40,
        ]);
        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $professional->id,
            'weekday' => CarbonImmutable::parse('2026-06-10')->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'active' => true,
        ]);

        $bookingPage = $this->get(route('public-bookings.create', [
            'company' => $company,
            'service_ids' => [$service->id],
            'user_id' => $professional->id,
            'date' => '2026-06-10',
            'filters_submitted' => 1,
        ], false))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/#ff0000/i', $bookingPage);
        $this->assertStringContainsString('brand-cta', $bookingPage);
    }

    public function test_public_booking_page_contains_branding_for_company(): void
    {
        $company = Company::factory()->create([
            'name' => 'Barbearia Alfa',
            'public_headline' => 'Corte com estilo',
            'primary_color' => '#ff0000',
            'brand_enabled' => true,
        ]);
        $professional = User::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create([
            'active' => true,
            'duration_minutes' => 30,
            'price' => 40,
        ]);
        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $professional->id,
            'weekday' => CarbonImmutable::parse('2026-06-10')->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'active' => true,
        ]);

        $this->get(route('public-bookings.create', [
            'company' => $company,
            'service_ids' => [$service->id],
            'user_id' => $professional->id,
            'date' => '2026-06-10',
            'filters_submitted' => 1,
        ], false))
            ->assertOk()
            ->assertSee('Corte com estilo', false)
            ->assertSee('--brand-primary: #FF0000', false);
    }

    public function test_dashboard_renders_saved_branding_tokens(): void
    {
        $company = Company::factory()->create([
            'onboarding_completed_at' => now(),
            'brand_enabled' => true,
            'primary_color' => '#ff0000',
            'secondary_color' => '#223d69',
            'accent_color' => '#132746',
        ]);
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertSee('--brand-primary: #FF0000', false);
    }

    public function test_after_saving_primary_color_dashboard_html_updates(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create([
            'name' => 'Empresa Teste',
            'onboarding_completed_at' => now(),
            'primary_color' => '#111111',
            'brand_enabled' => true,
        ]);
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->patch(route('company.update', absolute: false), [
                'name' => $company->name,
                'primary_color' => '#ee00ee',
                'brand_enabled' => '1',
            ])
            ->assertRedirect(route('company.edit', absolute: false));

        $this->actingAs($admin)
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertSee('--brand-primary: #EE00EE', false);
    }

    public function test_branding_preview_endpoint_returns_theme_variables(): void
    {
        $company = Company::factory()->create([
            'onboarding_completed_at' => now(),
            'brand_enabled' => true,
        ]);
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->postJson(route('company.branding-preview', absolute: false), [
                'primary_color' => '#750006',
                'secondary_color' => '#FAFAFA',
                'accent_color' => '#FFFFFF',
                'brand_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonStructure(['vars' => ['--brand-primary', '--app-bg', '--text-main']])
            ->assertJsonPath('vars.--brand-primary', '#750006')
            ->assertJsonPath('vars.--text-main', '#0F172A');
    }

    public function test_light_theme_generates_dark_text_tokens(): void
    {
        $company = Company::factory()->create([
            'brand_enabled' => true,
            'primary_color' => '#2563EB',
            'secondary_color' => '#F8FAFC',
            'accent_color' => '#FFFFFF',
        ]);

        $branding = app(BrandingService::class)->getCurrentCompanyBranding($company);

        $this->assertTrue($branding['theme_light']);
        $this->assertStringContainsString('--text-main: #0F172A', $branding['root_style']);
    }

    public function test_dark_theme_generates_light_text_tokens(): void
    {
        $company = Company::factory()->create([
            'brand_enabled' => true,
            'primary_color' => '#D4AF37',
            'secondary_color' => '#050505',
            'accent_color' => '#141414',
        ]);

        $branding = app(BrandingService::class)->getCurrentCompanyBranding($company);

        $this->assertFalse($branding['theme_light']);
        $this->assertStringContainsString('--text-main: #F8FAFC', $branding['root_style']);
    }

    public function test_preview_endpoint_rejects_css_injection_attempt(): void
    {
        $company = Company::factory()->create([
            'onboarding_completed_at' => now(),
        ]);
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->postJson(route('company.branding-preview', absolute: false), [
                'primary_color' => '#123456;background:red',
                'secondary_color' => '#223D69',
                'accent_color' => '#132746',
                'brand_enabled' => true,
            ])
            ->assertStatus(422);
    }

    public function test_company_update_only_affects_authenticated_users_company(): void
    {
        $a = Company::factory()->create(['name' => 'A']);
        $b = Company::factory()->create(['name' => 'B']);
        $adminA = User::factory()->admin()->for($a)->create();

        $this->actingAs($adminA)
            ->patch(route('company.update', absolute: false), [
                'name' => 'Novo nome A',
                'primary_color' => '#123456',
                'brand_enabled' => '1',
            ])
            ->assertRedirect(route('company.edit', absolute: false));

        $a->refresh();
        $b->refresh();
        $this->assertSame('Novo nome A', $a->name);
        $this->assertSame('#123456', $a->primary_color);
        $this->assertSame('B', $b->name);
        $this->assertNull($b->primary_color);
    }
}
