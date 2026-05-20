<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPublicMedia;
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
                'description' => 'Cortes completos e atendimento de bairro com acabamento fino.',
                'receipt_message' => 'Mais que um corte, e sobre autoestima.',
                'logo' => $logo,
            ])
            ->assertRedirect(route('company.edit', absolute: false));

        $company->refresh();

        $this->assertSame('Barbearia do Joao', $company->name);
        $this->assertSame('(71) 99999-0000', $company->phone);
        $this->assertSame('Rua Central, 123', $company->address);
        $this->assertSame('12.345.678/0001-90', $company->cnpj);
        $this->assertSame('@barbeariadojoao', $company->instagram);
        $this->assertSame('Cortes completos e atendimento de bairro com acabamento fino.', $company->description);
        $this->assertSame('Mais que um corte, e sobre autoestima.', $company->receipt_message);
        $this->assertNotNull($company->logo);
        Storage::disk('public')->assertExists($company->logo);
    }

    public function test_admin_can_manage_public_booking_cover_banners(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $existing = CompanyPublicMedia::create([
            'company_id' => $company->id,
            'type' => 'booking_cover',
            'path' => 'companies/booking-covers/old.jpg',
            'position' => 0,
            'is_active' => true,
        ]);
        Storage::disk('public')->put($existing->path, 'old-cover');

        $this
            ->actingAs($admin)
            ->get(route('company.edit', absolute: false))
            ->assertOk()
            ->assertSee('Banners do agendamento')
            ->assertSee('/storage/companies/booking-covers/old.jpg');

        $this
            ->actingAs($admin)
            ->patch(route('company.update', absolute: false), [
                'name' => $company->name,
                'remove_booking_cover_media' => [$existing->id],
                'booking_cover_images' => [
                    UploadedFile::fake()->image('cover-1.webp', 1200, 600),
                    UploadedFile::fake()->image('cover-2.jpg', 1200, 600),
                ],
            ])
            ->assertRedirect(route('company.edit', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('company_public_media', [
            'id' => $existing->id,
        ]);
        Storage::disk('public')->assertMissing($existing->path);

        $covers = $company->fresh()->bookingCoverImages()->get();

        $this->assertCount(2, $covers);
        $this->assertSame([1, 2], $covers->pluck('position')->all());
        $covers->each(fn (CompanyPublicMedia $cover) => Storage::disk('public')->assertExists($cover->path));
    }

    public function test_admin_can_customize_public_booking_hero_texts(): void
    {
        $company = Company::factory()->create([
            'name' => 'Barbearia Raiz',
            'description' => 'Descricao antiga',
        ]);
        $admin = User::factory()->admin()->for($company)->create();

        $this
            ->actingAs($admin)
            ->patch(route('company.update', absolute: false), [
                'name' => 'Barbearia Raiz',
                'public_headline' => 'Seu visual novo começa aqui',
                'public_subheadline' => 'Corte, barba e cuidado com hora marcada.',
            ])
            ->assertRedirect(route('company.edit', absolute: false))
            ->assertSessionHasNoErrors();

        $company->refresh();

        $this->assertSame('Seu visual novo começa aqui', $company->public_headline);
        $this->assertSame('Corte, barba e cuidado com hora marcada.', $company->public_subheadline);
    }

    public function test_admin_cannot_keep_more_than_five_public_booking_cover_banners(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        foreach (range(1, 4) as $index) {
            CompanyPublicMedia::create([
                'company_id' => $company->id,
                'type' => 'booking_cover',
                'path' => "companies/booking-covers/existing-{$index}.jpg",
                'position' => $index,
                'is_active' => true,
            ]);
        }

        $this
            ->actingAs($admin)
            ->from(route('company.edit', absolute: false))
            ->patch(route('company.update', absolute: false), [
                'name' => 'Nao deve salvar parcialmente',
                'booking_cover_images' => [
                    UploadedFile::fake()->image('cover-1.webp', 1200, 600),
                    UploadedFile::fake()->image('cover-2.jpg', 1200, 600),
                ],
            ])
            ->assertRedirect(route('company.edit', absolute: false))
            ->assertSessionHasErrors('booking_cover_images');

        $this->assertSame($company->name, $company->fresh()->name);
        $this->assertCount(4, $company->fresh()->bookingCoverImages()->get());
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

    public function test_company_text_fields_never_persist_dom_object_strings(): void
    {
        $company = Company::factory()->create([
            'description' => 'Descricao inicial',
        ]);
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->patch(route('company.update', absolute: false), [
                'name' => 'Empresa segura',
                'address' => '[object HTMLTextAreaElement]',
                'description' => '[object HTMLTextAreaElement]',
                'phone' => '[object HTMLInputElement]',
            ])
            ->assertRedirect(route('company.edit', absolute: false));

        $company->refresh();

        $this->assertSame('Empresa segura', $company->name);
        $this->assertNull($company->address);
        $this->assertNull($company->description);
        $this->assertNull($company->phone);

        $this->actingAs($admin)
            ->get(route('company.edit', absolute: false))
            ->assertOk()
            ->assertDontSee('[object HTMLTextAreaElement]')
            ->assertDontSee('[object HTMLInputElement]');
    }

    public function test_restoring_theme_clears_colors_without_removing_texts_or_logo(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('companies/test-logo.png', 'logo');

        $company = Company::factory()->create([
            'name' => 'Empresa tema',
            'description' => 'Descricao firme',
            'logo' => 'companies/test-logo.png',
            'primary_color' => '#750006',
            'secondary_color' => '#FAFAFA',
            'accent_color' => '#FFFFFF',
            'brand_enabled' => true,
        ]);
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->patch(route('company.update', absolute: false), [
                'name' => 'Empresa tema',
                'description' => 'Descricao firme',
                'primary_color' => '',
                'secondary_color' => '',
                'accent_color' => '',
                'brand_enabled' => '0',
            ])
            ->assertRedirect(route('company.edit', absolute: false));

        $company->refresh();

        $this->assertNull($company->primary_color);
        $this->assertNull($company->secondary_color);
        $this->assertNull($company->accent_color);
        $this->assertFalse($company->brand_enabled);
        $this->assertSame('Descricao firme', $company->description);
        $this->assertSame('companies/test-logo.png', $company->logo);
    }

    public function test_percentage_booking_deposit_cannot_be_greater_than_one_hundred_percent(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->from(route('company.edit', absolute: false))
            ->patch(route('company.update', absolute: false), [
                'name' => 'Empresa Percentual',
                'online_booking_payment_enabled' => '1',
                'booking_payment_requirement' => 'required',
                'booking_payment_mode' => 'deposit',
                'booking_deposit_type' => 'percentage',
                'booking_deposit_value' => '120',
                'booking_payment_expiration_minutes' => 15,
                'booking_auto_cancel_unpaid' => '1',
            ])
            ->assertRedirect(route('company.edit', absolute: false))
            ->assertSessionHasErrors([
                'booking_deposit_value' => 'O percentual do sinal não pode ser maior que 100%.',
            ]);
    }
}
