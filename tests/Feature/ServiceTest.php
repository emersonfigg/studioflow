<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_list_create_update_and_delete_services(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        Storage::disk('public')->put('services/existing-service.jpg', 'existing-service');
        $service = Service::factory()->for($company)->create([
            'name' => 'Existing Service',
            'image_path' => 'services/existing-service.jpg',
        ]);
        Service::factory()->create([
            'name' => 'Other Company Service',
        ]);

        $this
            ->actingAs($admin)
            ->get('/services')
            ->assertOk()
            ->assertSee('Serviços')
            ->assertSee('Gerencie serviços vendidos no PDV e exibidos no link de agendamento.')
            ->assertSee('Existing Service')
            ->assertSee('Lista')
            ->assertSee('Cards')
            ->assertSee('Ticket médio')
            ->assertSee('Tempo médio')
            ->assertSee('Buscar serviço...')
            ->assertSee('PDV')
            ->assertSee('Visualizar')
            ->assertSee('Desativar')
            ->assertSee('/storage/services/existing-service.jpg')
            ->assertDontSee('Other Company Service');

        $createResponse = $this
            ->actingAs($admin)
            ->post('/services', [
                'name' => 'New Service',
                'description' => 'Descricao de teste do servico.',
                'duration_minutes' => 60,
                'price' => '125.50',
                'active' => '1',
                'is_publicly_available' => '1',
                'available_for_pos' => '0',
                'image' => UploadedFile::fake()->image('new-service.webp'),
            ]);

        $createdService = Service::where('name', 'New Service')->firstOrFail();

        $createResponse->assertRedirect(route('services.show', $createdService, absolute: false));
        $this->assertSame($company->id, $createdService->company_id);
        $this->assertTrue($createdService->active);
        $this->assertTrue($createdService->is_publicly_available);
        $this->assertFalse($createdService->available_for_pos);
        $this->assertSame('Descricao de teste do servico.', $createdService->description);
        $this->assertNotNull($createdService->image_path);
        Storage::disk('public')->assertExists($createdService->image_path);

        $this
            ->actingAs($admin)
            ->post("/services/{$service->id}", [
                '_method' => 'PATCH',
                'name' => 'Updated Service',
                'description' => 'Descricao atualizada',
                'duration_minutes' => 90,
                'price' => '250.00',
                'is_publicly_available' => '0',
                'available_for_pos' => '1',
                'image' => UploadedFile::fake()->image('updated-service.png'),
            ])
            ->assertRedirect(route('services.show', $service, absolute: false));

        $service->refresh();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'company_id' => $company->id,
            'name' => 'Updated Service',
            'description' => 'Descricao atualizada',
            'duration_minutes' => 90,
            'price' => '250.00',
            'active' => false,
            'is_publicly_available' => false,
            'available_for_pos' => true,
        ]);
        $this->assertNotSame('services/existing-service.jpg', $service->image_path);
        Storage::disk('public')->assertExists($service->image_path);

        $this
            ->actingAs($admin)
            ->delete("/services/{$service->id}")
            ->assertRedirect(route('services.index', absolute: false));

        $this->assertDatabaseMissing('services', [
            'id' => $service->id,
        ]);
    }

    public function test_company_staff_can_list_and_view_but_cannot_create_update_or_delete_services(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        Storage::disk('public')->put('services/visible-service.jpg', 'visible-service');
        $service = Service::factory()->for($company)->create([
            'name' => 'Visible Service',
            'image_path' => 'services/visible-service.jpg',
        ]);

        $this
            ->actingAs($staff)
            ->get('/services')
            ->assertOk()
            ->assertSee('Visible Service')
            ->assertSee('/storage/services/visible-service.jpg');

        $this
            ->actingAs($staff)
            ->get("/services/{$service->id}")
            ->assertOk()
            ->assertSee('Visible Service')
            ->assertSee('Detalhes e performance do serviço')
            ->assertSee('/storage/services/visible-service.jpg')
            ->assertSee('absolute inset-0 h-full w-full object-cover', false)
            ->assertDontSee('h-56 w-full object-cover', false)
            ->assertSee('Receita gerada no mês');

        $this
            ->actingAs($staff)
            ->get('/services/create')
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->post('/services', [
                'name' => 'Blocked Service',
                'duration_minutes' => 45,
                'price' => '99.00',
                'active' => '1',
            ])
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->get("/services/{$service->id}/edit")
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->patch("/services/{$service->id}", [
                'name' => 'Blocked Update',
                'duration_minutes' => 30,
                'price' => '80.00',
            ])
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->delete("/services/{$service->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Visible Service',
        ]);
    }

    public function test_company_admin_can_create_and_update_service_using_library_images(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        Storage::disk('public')->put(
            'service-library/services/corte-premium.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#d4af37"/></svg>'
        );
        Storage::disk('public')->put(
            'service-library/services/barba-luxo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#1b335b"/></svg>'
        );

        $this
            ->actingAs($admin)
            ->get('/services/create')
            ->assertOk()
            ->assertSee('/storage/service-library/services/corte-premium.svg')
            ->assertSee('/storage/service-library/services/barba-luxo.svg');

        $this
            ->actingAs($admin)
            ->post('/services', [
                'name' => 'Servico com biblioteca',
                'duration_minutes' => 45,
                'price' => '89.90',
                'active' => '1',
                'library_image' => 'service-library/services/corte-premium.svg',
            ])
            ->assertRedirect();

        $service = Service::where('name', 'Servico com biblioteca')->firstOrFail();

        $this->assertNotNull($service->image_path);
        $this->assertStringStartsWith('services/corte-premium-', $service->image_path);
        Storage::disk('public')->assertExists($service->image_path);
        $this->assertSame(
            Storage::disk('public')->get('service-library/services/corte-premium.svg'),
            Storage::disk('public')->get($service->image_path)
        );

        $oldPath = $service->image_path;

        $this
            ->actingAs($admin)
            ->patch("/services/{$service->id}", [
                'name' => 'Servico com biblioteca',
                'duration_minutes' => 45,
                'price' => '89.90',
                'active' => '1',
                'library_image' => 'service-library/services/barba-luxo.svg',
            ])
            ->assertRedirect(route('services.show', $service, absolute: false));

        $service->refresh();

        $this->assertNotSame($oldPath, $service->image_path);
        Storage::disk('public')->assertExists($service->image_path);
        $this->assertSame(
            Storage::disk('public')->get('service-library/services/barba-luxo.svg'),
            Storage::disk('public')->get($service->image_path)
        );
    }

    public function test_service_description_is_optional_on_create_and_update(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this
            ->actingAs($admin)
            ->post('/services', [
                'name' => 'Servico sem descricao',
                'duration_minutes' => 30,
                'price' => '40.00',
                'active' => '1',
            ])
            ->assertSessionDoesntHaveErrors('description')
            ->assertRedirect();

        $service = Service::where('name', 'Servico sem descricao')->firstOrFail();

        $this->assertNull($service->description);

        $this
            ->actingAs($admin)
            ->patch("/services/{$service->id}", [
                'name' => 'Servico sem descricao',
                'duration_minutes' => 30,
                'price' => '40.00',
                'active' => '1',
            ])
            ->assertSessionDoesntHaveErrors('description')
            ->assertRedirect();

        $service->refresh();
        $this->assertNull($service->description);
    }

    public function test_service_description_rejects_payloads_longer_than_500_characters(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $service = Service::factory()->for($company)->create();

        $oversized = str_repeat('a', 501);

        $this
            ->actingAs($admin)
            ->from('/services/create')
            ->post('/services', [
                'name' => 'Servico longo',
                'description' => $oversized,
                'duration_minutes' => 30,
                'price' => '50.00',
                'active' => '1',
            ])
            ->assertRedirect('/services/create')
            ->assertSessionHasErrors('description');

        $this->assertDatabaseMissing('services', [
            'company_id' => $company->id,
            'name' => 'Servico longo',
        ]);

        $this
            ->actingAs($admin)
            ->from("/services/{$service->id}/edit")
            ->patch("/services/{$service->id}", [
                'name' => $service->name,
                'description' => $oversized,
                'duration_minutes' => $service->duration_minutes,
                'price' => number_format((float) $service->price, 2, '.', ''),
                'active' => '1',
            ])
            ->assertRedirect("/services/{$service->id}/edit")
            ->assertSessionHasErrors('description');

        $service->refresh();
        $this->assertNotSame($oversized, $service->description);
    }

    public function test_service_description_accepts_exactly_500_characters(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $description = str_repeat('a', 500);

        $this
            ->actingAs($admin)
            ->post('/services', [
                'name' => 'Servico no limite',
                'description' => $description,
                'duration_minutes' => 30,
                'price' => '50.00',
                'active' => '1',
            ])
            ->assertSessionDoesntHaveErrors('description')
            ->assertRedirect();

        $this->assertDatabaseHas('services', [
            'company_id' => $company->id,
            'name' => 'Servico no limite',
            'description' => $description,
        ]);
    }

    public function test_user_cannot_access_services_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $otherService = Service::factory()->for($otherCompany)->create([
            'name' => 'Private Service',
        ]);

        $this
            ->actingAs($admin)
            ->get('/services')
            ->assertOk()
            ->assertDontSee('Private Service');

        $this
            ->actingAs($admin)
            ->get("/services/{$otherService->id}")
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->get("/services/{$otherService->id}/edit")
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->patch("/services/{$otherService->id}", [
                'name' => 'Leaked Service',
                'duration_minutes' => 20,
                'price' => '50.00',
            ])
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->delete("/services/{$otherService->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('services', [
            'id' => $otherService->id,
            'company_id' => $otherCompany->id,
            'name' => 'Private Service',
        ]);
    }

    public function test_services_index_filters_by_search_public_pos_and_active_status(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        Service::factory()->for($company)->create([
            'name' => 'Corte Interno',
            'active' => true,
            'is_publicly_available' => false,
            'available_for_pos' => true,
        ]);
        Service::factory()->for($company)->create([
            'name' => 'Barba Publica',
            'active' => true,
            'is_publicly_available' => true,
            'available_for_pos' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('services.index', [
                'q' => 'Corte',
                'public_status' => '0',
                'pos_status' => '1',
                'active_status' => '1',
                'view' => 'list',
            ], false))
            ->assertOk()
            ->assertSee('Corte Interno')
            ->assertDontSee('Barba Publica');
    }

    public function test_services_index_cards_mode_and_toggle_active_preserves_availability(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'name' => 'Servico Alternavel',
            'active' => true,
            'is_publicly_available' => false,
            'available_for_pos' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('services.index', ['view' => 'cards'], false))
            ->assertOk()
            ->assertSee('Público: Não')
            ->assertSee('PDV: Sim')
            ->assertSee('Desativar');

        $this->actingAs($admin)
            ->patch(route('services.update', $service, false), [
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'price' => number_format((float) $service->price, 2, '.', ''),
                'active' => '0',
                'is_publicly_available' => '0',
                'available_for_pos' => '1',
            ])
            ->assertRedirect(route('services.show', $service, false));

        $service->refresh();

        $this->assertFalse($service->active);
        $this->assertFalse($service->is_publicly_available);
        $this->assertTrue($service->available_for_pos);
    }
}
