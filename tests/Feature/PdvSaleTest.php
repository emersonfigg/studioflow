<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdvSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-04-15 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_standalone_product_sale_succeeds_and_exposes_receipt(): void
    {
        $company = Company::factory()->create(['auto_print_receipt' => false]);
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 25.50,
            'stock_quantity' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('pdv.index', absolute: false));
        $response->assertSessionHas('pdv_sale_result');

        $order = ServiceOrder::query()->firstOrFail();
        $this->assertSame(ServiceOrder::STATUS_PAID, $order->status);

        $payload = session('pdv_sale_result');
        $this->assertIsArray($payload);
        $this->assertSame($order->id, $payload['service_order_id']);
        $this->assertSame('51,00', $payload['total']);
        $this->assertSame('pix', $payload['payment_method']);
        $this->assertFalse($payload['appointment_completed']);
        $this->assertNotEmpty($payload['receipt_url']);
        $this->assertFalse($payload['auto_print_receipt']);

        $this->actingAs($admin)->get(route('pdv.receipt', $order, absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('sales.receipt', $order, absolute: false))->assertOk();
    }

    public function test_pdv_with_appointment_completes_sale_and_appointment(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create(['price' => 60.00, 'active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 10.00,
            'stock_quantity' => 5,
            'active' => true,
        ]);

        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'appointment_id' => $appointment->id,
            'payment_method' => 'card',
            'user_id' => $admin->id,
            'service_items' => [
                ['service_id' => $service->id],
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect(route('pdv.index', absolute: false));
        $response->assertSessionHas('pdv_sale_result');

        $appointment->refresh();
        $this->assertSame('completed', $appointment->status);

        $payload = session('pdv_sale_result');
        $this->assertTrue($payload['appointment_completed']);
        $this->assertSame($appointment->id, $payload['appointment_id']);
    }

    public function test_second_sale_for_same_completed_appointment_is_blocked(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create(['price' => 40.00, 'active' => true]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'appointment_id' => $appointment->id,
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'service_items' => [['service_id' => $service->id]],
            'items' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame('completed', $appointment->fresh()->status);

        $product = Product::factory()->for($company)->create(['price' => 5.00, 'stock_quantity' => 3, 'active' => true]);

        $response = $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'appointment_id' => $appointment->id,
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'service_items' => [['service_id' => $service->id]],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('appointment_id');
        $this->assertSame(1, ServiceOrder::query()->where('appointment_id', $appointment->id)->where('status', ServiceOrder::STATUS_PAID)->count());
    }

    public function test_pdv_index_hydrates_cart_from_appointment_services(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'name' => 'Corte PDV Hydrate',
            'price' => 55.50,
            'active' => true,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($admin)->get(route('pdv.index', ['appointment_id' => $appointment->id], absolute: false));

        $response->assertOk();
        $response->assertSee('id="pdv-initial-cart-data"', false);
        $html = $response->getContent();
        $this->assertStringContainsString('"type":"service"', $html);
        $this->assertStringContainsString('"source":"appointment"', $html);
        $this->assertStringContainsString('Corte PDV Hydrate', $html);
        $this->assertStringContainsString('55.5', $html);
        $response->assertSee('Serviços carregados do agendamento', false);
        $response->assertSee('Corte PDV Hydrate', false);
        $response->assertSee('55,50', false);
    }

    public function test_pdv_index_hydrates_services_when_open_order_has_no_service_lines(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'name' => 'Servico Comanda Vazia',
            'price' => 40.00,
            'active' => true,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        ServiceOrder::create([
            'company_id' => $company->id,
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'professional_id' => $admin->id,
            'status' => ServiceOrder::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('pdv.index', ['appointment_id' => $appointment->id], absolute: false));

        $response->assertOk();
        $this->assertStringContainsString('id="pdv-initial-cart-data"', $response->getContent());
        $this->assertStringContainsString('"type":"service"', $response->getContent());
        $response->assertSee('Servico Comanda Vazia', false);
        $response->assertSee('40,00', false);
    }

    public function test_pdv_appointment_sale_requires_at_least_one_service_line(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create(['price' => 30.00, 'active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 12.00,
            'stock_quantity' => 4,
            'active' => true,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $service->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'appointment_id' => $appointment->id,
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'service_items' => [],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('service_items');
        $this->assertNotSame('completed', $appointment->fresh()->status);
    }

    public function test_validation_error_keeps_cart_safe_and_does_not_complete_appointment(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => Client::factory()->for($company)->create()->id,
            'user_id' => $admin->id,
            'service_id' => Service::factory()->for($company)->create()->id,
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'appointment_id' => $appointment->id,
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'service_items' => [],
            'items' => [],
        ]);

        $response->assertSessionHasErrors();
        $this->assertFalse(session()->has('pdv_sale_result'));
        $this->assertNotSame('completed', $appointment->fresh()->status);
        $this->assertSame(0, ServiceOrder::query()->count());
    }

    public function test_flash_includes_auto_print_flag_when_company_enabled(): void
    {
        $company = Company::factory()->create(['auto_print_receipt' => true]);
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 10.00,
            'stock_quantity' => 2,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'payment_method' => 'cash',
            'user_id' => $admin->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHas('pdv_sale_result');

        $this->assertTrue(session('pdv_sale_result')['auto_print_receipt']);
    }
}
