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
            ->assertSee('Existing Service')
            ->assertSee('/storage/services/existing-service.jpg')
            ->assertDontSee('Other Company Service');

        $createResponse = $this
            ->actingAs($admin)
            ->post('/services', [
                'name' => 'New Service',
                'duration_minutes' => 60,
                'price' => '125.50',
                'active' => '1',
                'image' => UploadedFile::fake()->image('new-service.webp'),
            ]);

        $createdService = Service::where('name', 'New Service')->firstOrFail();

        $createResponse->assertRedirect(route('services.show', $createdService, absolute: false));
        $this->assertSame($company->id, $createdService->company_id);
        $this->assertTrue($createdService->active);
        $this->assertNotNull($createdService->image_path);
        Storage::disk('public')->assertExists($createdService->image_path);

        $this
            ->actingAs($admin)
            ->post("/services/{$service->id}", [
                '_method' => 'PATCH',
                'name' => 'Updated Service',
                'duration_minutes' => 90,
                'price' => '250.00',
                'image' => UploadedFile::fake()->image('updated-service.png'),
            ])
            ->assertRedirect(route('services.show', $service, absolute: false));

        $service->refresh();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'company_id' => $company->id,
            'name' => 'Updated Service',
            'duration_minutes' => 90,
            'price' => '250.00',
            'active' => false,
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
            ->assertSee('Detalhes e performance do servico')
            ->assertSee('/storage/services/visible-service.jpg')
            ->assertSee('Receita gerada no mes');

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
}
