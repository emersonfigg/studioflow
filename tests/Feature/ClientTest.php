<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_list_create_update_and_delete_clients(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create([
            'name' => 'Existing Client',
        ]);
        Client::factory()->create([
            'name' => 'Other Company Client',
        ]);

        $this
            ->actingAs($admin)
            ->get('/clients')
            ->assertOk()
            ->assertSee('Existing Client')
            ->assertDontSee('Other Company Client');

        $createResponse = $this
            ->actingAs($admin)
            ->post('/clients', [
                'name' => 'New Client',
                'phone' => '555-1000',
                'birthday' => '1990-01-15',
                'notes' => 'Prefers morning appointments.',
                'last_visit_at' => '2026-04-16 10:00:00',
            ]);

        $createdClient = Client::where('name', 'New Client')->firstOrFail();

        $createResponse->assertRedirect(route('clients.show', $createdClient, absolute: false));
        $this->assertSame($company->id, $createdClient->company_id);

        $this
            ->actingAs($admin)
            ->patch("/clients/{$client->id}", [
                'name' => 'Updated Client',
                'phone' => '555-2000',
                'birthday' => '1991-02-20',
                'notes' => 'Updated notes.',
                'last_visit_at' => '2026-04-16 11:00:00',
            ])
            ->assertRedirect(route('clients.show', $client, absolute: false));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'company_id' => $company->id,
            'name' => 'Updated Client',
            'phone' => '555-2000',
        ]);

        $this
            ->actingAs($admin)
            ->delete("/clients/{$client->id}")
            ->assertRedirect(route('clients.index', absolute: false));

        $this->assertDatabaseMissing('clients', [
            'id' => $client->id,
        ]);
    }

    public function test_company_staff_can_list_and_view_but_cannot_create_update_or_delete_clients(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create([
            'name' => 'Visible Client',
        ]);

        $this
            ->actingAs($staff)
            ->get('/clients')
            ->assertOk()
            ->assertSee('Visible Client');

        $this
            ->actingAs($staff)
            ->get("/clients/{$client->id}")
            ->assertOk()
            ->assertSee('Visible Client');

        $this
            ->actingAs($staff)
            ->get('/clients/create')
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->post('/clients', [
                'name' => 'Blocked Client',
                'phone' => '555-3000',
            ])
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->get("/clients/{$client->id}/edit")
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->patch("/clients/{$client->id}", [
                'name' => 'Blocked Update',
                'phone' => '555-4000',
            ])
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->delete("/clients/{$client->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Visible Client',
        ]);
    }

    public function test_user_cannot_access_clients_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $otherClient = Client::factory()->for($otherCompany)->create([
            'name' => 'Private Client',
        ]);

        $this
            ->actingAs($admin)
            ->get('/clients')
            ->assertOk()
            ->assertDontSee('Private Client');

        $this
            ->actingAs($admin)
            ->get("/clients/{$otherClient->id}")
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->get("/clients/{$otherClient->id}/edit")
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->patch("/clients/{$otherClient->id}", [
                'name' => 'Leaked Client',
                'phone' => '555-5000',
            ])
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->delete("/clients/{$otherClient->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('clients', [
            'id' => $otherClient->id,
            'company_id' => $otherCompany->id,
            'name' => 'Private Client',
        ]);
    }

    public function test_company_user_can_create_or_reuse_client_inline_for_appointments(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $staff = User::factory()->for($company)->create();

        Client::factory()->for($otherCompany)->create([
            'name' => 'Other Company Match',
            'phone' => '555-9191',
        ]);

        $createResponse = $this
            ->actingAs($staff)
            ->postJson('/clients/inline', [
                'name' => 'Inline Client',
                'phone' => '555-9191',
                'birthday' => '1992-06-10',
                'notes' => 'Created from appointment modal.',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJson([
                'reused' => false,
                'client' => [
                    'name' => 'Inline Client',
                    'phone' => '555-9191',
                ],
            ]);

        $client = Client::query()
            ->where('company_id', $company->id)
            ->where('phone', '555-9191')
            ->firstOrFail();

        $reuseResponse = $this
            ->actingAs($staff)
            ->postJson('/clients/inline', [
                'name' => 'Different Name',
                'phone' => '555-9191',
            ]);

        $reuseResponse
            ->assertOk()
            ->assertJson([
                'reused' => true,
                'client' => [
                    'id' => $client->id,
                    'name' => 'Inline Client',
                    'phone' => '555-9191',
                ],
            ]);

        $this->assertDatabaseCount('clients', 2);
    }

    public function test_admin_can_deactivate_and_reactivate_client_and_delete_is_blocked_with_history(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create(['active' => true]);
        ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $admin->id,
            'status' => ServiceOrder::STATUS_OPEN,
            'subtotal_services' => 0,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 0,
            'opened_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('clients.deactivate', $client, false))
            ->assertRedirect(route('clients.show', $client, false));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'active' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('clients.destroy', $client, false))
            ->assertRedirect(route('clients.show', $client, false));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'active' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('clients.reactivate', $client, false))
            ->assertRedirect(route('clients.show', $client, false));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'active' => true,
        ]);
    }

    public function test_client_code_is_generated_per_company_and_cpf_is_unique_per_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $adminA = User::factory()->admin()->for($companyA)->create();
        $adminB = User::factory()->admin()->for($companyB)->create();

        $this->actingAs($adminA)->post('/clients', [
            'name' => 'Cliente A1',
            'phone' => '11999990001',
            'cpf' => '111.222.333-44',
        ])->assertRedirect();

        $this->actingAs($adminA)->post('/clients', [
            'name' => 'Cliente A2',
            'phone' => '11999990002',
            'cpf' => '11122233344',
        ])->assertSessionHasErrors('cpf');

        $this->actingAs($adminB)->post('/clients', [
            'name' => 'Cliente B1',
            'phone' => '11999990003',
            'cpf' => '11122233344',
        ])->assertRedirect();

        $firstClientA = Client::query()->where('company_id', $companyA->id)->firstOrFail();
        $firstClientB = Client::query()->where('company_id', $companyB->id)->firstOrFail();

        $this->assertSame('C0001', $firstClientA->client_code);
        $this->assertSame('C0001', $firstClientB->client_code);
    }

    public function test_client_search_supports_code_and_cpf(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create([
            'name' => 'Cliente Busca',
            'phone' => '11988887777',
            'cpf' => '12345678901',
            'cpf_normalized' => '12345678901',
            'client_code' => 'C0042',
        ]);

        $this->actingAs($admin)
            ->get('/clients?search=C0042')
            ->assertOk()
            ->assertSee('Cliente Busca');

        $this->actingAs($admin)
            ->get('/clients?search=123.456.789-01')
            ->assertOk()
            ->assertSee('Cliente Busca');

        $this->assertNotNull($client);
    }

    public function test_inactive_client_is_hidden_from_operational_flows(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $inactiveClient = Client::factory()->for($company)->create([
            'name' => 'Inativo Operacional',
            'active' => false,
        ]);

        $this->actingAs($admin)->get('/pdv')->assertOk()->assertDontSee('Inativo Operacional');
        $this->actingAs($admin)->get('/appointments/create')->assertOk()->assertDontSee('Inativo Operacional');
        $this->actingAs($admin)->get(route('product-sales.create', absolute: false))->assertOk()->assertDontSee('Inativo Operacional');

        $this->assertNotNull($inactiveClient);
    }
}
