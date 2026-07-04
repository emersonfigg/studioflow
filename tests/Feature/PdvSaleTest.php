<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Company;
use App\Models\CustomerMembership;
use App\Models\MembershipPlan;
use App\Models\MembershipUsage;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use App\Services\DailyDashboardService;
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

    public function test_pdv_membership_sale_records_receipt_item_and_payment_method(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano Barba',
            'price' => 69.90,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'cash',
            'membership_items' => [
                ['membership_plan_id' => $plan->id],
            ],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $order = ServiceOrder::query()->with('items')->firstOrFail();
        $this->assertSame(ServiceOrder::STATUS_PAID, $order->status);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('69.90', (string) $order->total);
        $this->assertSame(1, $order->items()->where('type', ServiceOrderItem::TYPE_MEMBERSHIP)->count());

        $membershipItem = $order->items->firstWhere('type', ServiceOrderItem::TYPE_MEMBERSHIP);
        $this->assertNotNull($membershipItem);
        $this->assertStringContainsString('Assinatura - Plano Barba (Mensal)', $membershipItem->description);
        $this->assertStringContainsString('Vigencia: 15/04/2026 a 14/05/2026', $membershipItem->description);

        $membership = CustomerMembership::query()->firstOrFail();
        $this->assertSame(CustomerMembership::STATUS_ACTIVE, $membership->status);

        $this->actingAs($admin)
            ->get(route('pdv.receipt', $order, absolute: false))
            ->assertOk()
            ->assertSee('Plano Barba')
            ->assertSee('Subtotal assinaturas')
            ->assertSee('Dinheiro');

        $this->actingAs($admin)
            ->get(route('pdv.sales.show', $order, absolute: false))
            ->assertOk()
            ->assertSee('Plano Barba')
            ->assertSee('Assinatura')
            ->assertSee('Dinheiro');
    }

    public function test_pdv_applies_fixed_discount_in_total(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['price' => 60.00, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'card_credit',
            'discount' => '10,00',
            'service_items' => [
                ['service_id' => $service->id],
            ],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $order = ServiceOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('10.00', (string) $order->discount);
        $this->assertSame('50.00', (string) $order->total);
    }

    public function test_pdv_applies_percent_discount_in_total(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['price' => 100.00, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'card_credit',
            'discount_type' => 'percent',
            'discount_value' => '10',
            'service_items' => [
                ['service_id' => $service->id],
            ],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $order = ServiceOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('10.00', (string) $order->discount);
        $this->assertSame('90.00', (string) $order->total);
        $this->assertDatabaseHas('payments', [
            'service_order_id' => $order->id,
            'gross_amount' => 90.00,
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'source_type' => Payment::class,
            'amount' => 90.00,
        ]);
    }

    public function test_pdv_accepts_percent_discount_zero_and_hundred(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['price' => 50.00, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'discount_type' => 'percent',
            'discount_value' => '0',
            'service_items' => [['service_id' => $service->id]],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $this->assertSame('50.00', (string) ServiceOrder::query()->latest('id')->firstOrFail()->total);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'discount_type' => 'percent',
            'discount_value' => '100',
            'service_items' => [['service_id' => $service->id]],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $this->assertSame('0.00', (string) ServiceOrder::query()->latest('id')->firstOrFail()->total);
    }

    public function test_pdv_blocks_percent_discount_above_hundred(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['price' => 80.00, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'discount_type' => 'percent',
            'discount_value' => '110',
            'service_items' => [['service_id' => $service->id]],
        ])->assertSessionHasErrors('discount_value');
    }

    public function test_pdv_blocks_fixed_discount_above_subtotal(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['price' => 40.00, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '60',
            'service_items' => [['service_id' => $service->id]],
        ])->assertSessionHasErrors('discount_value');
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
        $response->assertSee('id="pdv-search-input"', false);
        $response->assertSee('id="pdv-initial-cart-data"', false);
        $html = $response->getContent();
        $this->assertStringContainsString('"type":"service"', $html);
        $this->assertStringContainsString('"source":"appointment"', $html);
        $this->assertStringContainsString('Corte PDV Hydrate', $html);
        $this->assertStringContainsString('55.5', $html);
        $this->assertStringNotContainsString(':disabled="item.source === \'appointment\'"', $html);
        $this->assertStringNotContainsString('Serviço do agendamento (fixo)', $html);
        $response->assertSee('Serviços carregados do agendamento', false);
        $response->assertSee('Corte PDV Hydrate', false);
        $response->assertSee('55,50', false);
    }

    public function test_pdv_client_search_finds_client_by_name_without_loading_all_clients(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $target = Client::factory()->for($company)->create([
            'name' => 'Paulo Cliente',
            'phone' => '71999990000',
            'cpf' => '123.456.789-01',
            'active' => true,
        ]);
        Client::factory()->for($company)->create(['name' => 'Outro Cliente', 'active' => true]);

        $this->actingAs($admin)
            ->getJson(route('pdv.clients.search', ['q' => 'paul'], false))
            ->assertOk()
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('data.0.name', 'Paulo Cliente');

        $this->actingAs($admin)
            ->get(route('pdv.index', [], false))
            ->assertOk()
            ->assertSee('x-ref="clientSearchInput"', false)
            ->assertDontSee('Outro Cliente');

        $html = $this->actingAs($admin)
            ->get(route('pdv.index', [], false))
            ->getContent();
        $this->assertStringContainsString('placeholder="Balcão ou buscar cliente"', $html);
        $this->assertStringNotContainsString('value="Balcão ou buscar cliente"', $html);
        $this->assertStringContainsString("clientSearch: '',", $html);
    }

    public function test_pdv_client_search_finds_client_by_partial_phone_ignoring_mask(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $target = Client::factory()->for($company)->create([
            'name' => 'Telefone Cliente',
            'phone' => '(71) 99999-1234',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('pdv.clients.search', ['q' => '999912'], false))
            ->assertOk()
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_pdv_client_search_ignores_accents_and_case(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $target = Client::factory()->for($company)->create([
            'name' => 'João Ávila',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('pdv.clients.search', ['q' => 'joao av'], false))
            ->assertOk()
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_pdv_client_search_finds_client_by_cpf_with_and_without_mask(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $target = Client::factory()->for($company)->create([
            'name' => 'CPF Cliente',
            'cpf' => '123.456.789-01',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('pdv.clients.search', ['q' => '456789'], false))
            ->assertOk()
            ->assertJsonPath('data.0.id', $target->id);

        $this->actingAs($admin)
            ->getJson(route('pdv.clients.search', ['q' => '123.456'], false))
            ->assertOk()
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_pdv_client_search_does_not_return_clients_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        Client::factory()->for($otherCompany)->create([
            'name' => 'Cliente Outra Empresa',
            'phone' => '(71) 98888-7777',
            'cpf' => '987.654.321-00',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('pdv.clients.search', ['q' => 'Outra'], false))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_pdv_sale_with_walk_in_client_continues_working(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['price' => 40.00, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => '',
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'service_items' => [
                ['service_id' => $service->id],
            ],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $order = ServiceOrder::query()->firstOrFail();
        $this->assertSame(ServiceOrder::STATUS_PAID, $order->status);
        $this->assertDatabaseHas('clients', [
            'company_id' => $company->id,
            'name' => 'Cliente Balcao',
            'phone' => '0000000000',
        ]);
    }

    public function test_pdv_sale_with_selected_client_continues_working(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['price' => 40.00, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'service_items' => [
                ['service_id' => $service->id],
            ],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $order = ServiceOrder::query()->firstOrFail();
        $this->assertSame($client->id, $order->client_id);
    }

    public function test_pdv_only_sells_services_available_for_pos(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $publicOnly = Service::factory()->for($company)->create([
            'price' => 80,
            'active' => true,
            'is_publicly_available' => true,
            'available_for_pos' => false,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'service_items' => [['service_id' => $publicOnly->id]],
        ])->assertSessionHasErrors('service_items');
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

    public function test_pdv_appointment_sale_can_remove_service_and_sell_product_only(): void
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

        $response->assertRedirect(route('pdv.index', absolute: false));
        $response->assertSessionHasNoErrors();

        $order = ServiceOrder::query()->where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertSame(ServiceOrder::STATUS_PAID, $order->status);
        $this->assertSame('0.00', (string) $order->subtotal_services);
        $this->assertSame('12.00', (string) $order->subtotal_products);
        $this->assertSame('12.00', (string) $order->total);
        $this->assertSame('completed', $appointment->fresh()->status);
    }

    public function test_pdv_appointment_sale_can_replace_loaded_service_and_recalculate_total(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create([
            'active' => true,
            'commission_type' => 'percent',
            'commission_value' => 10,
        ]);
        $client = Client::factory()->for($company)->create();
        $bookedService = Service::factory()->for($company)->create(['price' => 30.00, 'active' => true]);
        $replacementService = Service::factory()->for($company)->create(['price' => 80.00, 'active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 20.00,
            'stock_quantity' => 4,
            'active' => true,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'service_id' => $bookedService->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'appointment_id' => $appointment->id,
            'payment_method' => 'pix',
            'user_id' => $admin->id,
            'discount_type' => 'fixed',
            'discount_value' => '10',
            'service_items' => [
                ['service_id' => $replacementService->id],
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('pdv.index', absolute: false));
        $response->assertSessionHasNoErrors();

        $order = ServiceOrder::query()
            ->where('appointment_id', $appointment->id)
            ->with(['items', 'payment'])
            ->firstOrFail();

        $this->assertSame(ServiceOrder::STATUS_PAID, $order->status);
        $this->assertSame('80.00', (string) $order->subtotal_services);
        $this->assertSame('40.00', (string) $order->subtotal_products);
        $this->assertSame('10.00', (string) $order->discount);
        $this->assertSame('110.00', (string) $order->total);
        $this->assertTrue($order->items->where('service_id', $replacementService->id)->isNotEmpty());
        $this->assertTrue($order->items->where('service_id', $bookedService->id)->isEmpty());
        $this->assertSame($replacementService->id, $order->payment?->service_id);
        $this->assertSame('7.00', (string) $order->payment?->commission_amount);
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

    public function test_pdv_sales_history_page_loads_with_filters(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['name' => 'Cliente PDV']);
        $service = Service::factory()->for($company)->create(['active' => true, 'price' => 80]);

        $order = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $admin->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 80,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 80,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(30),
        ]);

        $order->items()->create([
            'type' => 'service',
            'service_id' => $service->id,
            'professional_id' => $admin->id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => 80,
            'total_price' => 80,
        ]);
        $order->payment()->create([
            'company_id' => $company->id,
            'appointment_id' => null,
            'service_order_id' => $order->id,
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => 80,
            'payment_method' => 'pix',
            'commission_type' => null,
            'commission_rate' => null,
            'commission_amount' => 0,
            'net_amount' => 80,
            'paid_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($admin)
            ->get(route('pdv.sales', ['client_id' => $client->id, 'payment_method' => 'pix'], false))
            ->assertOk()
            ->assertSee('Histórico de Vendas')
            ->assertSee('Cliente PDV');
    }

    public function test_admin_can_correct_payment_method_from_pdv_sale_detail(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create(['active' => true, 'price' => 100]);

        $order = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $admin->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 100,
            'subtotal_products' => 20,
            'discount' => 0,
            'total' => 120,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(15),
        ]);

        $payment = Payment::query()->create([
            'company_id' => $company->id,
            'appointment_id' => null,
            'service_order_id' => $order->id,
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => 100,
            'payment_method' => 'cash',
            'commission_type' => null,
            'commission_rate' => null,
            'commission_amount' => 0,
            'net_amount' => 100,
            'paid_at' => now()->subMinutes(15),
        ]);
        $sale = ProductSale::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'service_order_id' => $order->id,
            'user_id' => $admin->id,
            'gross_amount' => 20,
            'payment_method' => 'cash',
            'sold_at' => now()->subMinutes(15),
        ]);

        CashMovement::query()->create([
            'company_id' => $company->id,
            'cash_register_id' => $company->cashRegisters()->create([
                'date' => now()->toDateString(),
                'opening_amount' => 0,
                'opened_by' => $admin->id,
                'opened_at' => now(),
            ])->id,
            'type' => 'inflow',
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'payment_method' => 'cash',
            'amount' => 100,
            'occurred_at' => now()->subMinutes(15),
            'description' => 'Teste',
        ]);
        CashMovement::query()->create([
            'company_id' => $company->id,
            'cash_register_id' => $company->cashRegisters()->first()->id,
            'type' => 'inflow',
            'source_type' => ProductSale::class,
            'source_id' => $sale->id,
            'payment_method' => 'cash',
            'amount' => 20,
            'occurred_at' => now()->subMinutes(15),
            'description' => 'Teste produto',
        ]);

        $this->actingAs($admin)
            ->patch(route('pdv.sales.payment-method.update', $order, false), [
                'payment_method' => 'card_debit',
                'reason' => 'Operador selecionou metodo incorreto no fechamento.',
            ])
            ->assertRedirect(route('pdv.sales.show', $order, false));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_method' => 'card_debit',
        ]);
        $this->assertDatabaseHas('product_sales', [
            'id' => $sale->id,
            'payment_method' => 'card_debit',
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'payment_method' => 'card_debit',
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'source_type' => ProductSale::class,
            'source_id' => $sale->id,
            'payment_method' => 'card_debit',
        ]);
    }

    public function test_staff_cannot_correct_payment_method_from_pdv_sale_detail(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create(['active' => true, 'role' => 'staff']);
        $client = Client::factory()->for($company)->create();

        $order = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $staff->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 20,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 20,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($staff)
            ->patch(route('pdv.sales.payment-method.update', $order, false), [
                'payment_method' => 'pix',
                'reason' => 'Teste',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_cancel_common_pdv_sale_and_remove_it_from_daily_revenue(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['price' => 100, 'active' => true]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'service_items' => [['service_id' => $service->id]],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $order = ServiceOrder::query()->with('payment')->firstOrFail();
        $this->assertSame(100.0, app(DailyDashboardService::class)->build($company->id, [
            'date' => CarbonImmutable::parse('2026-04-15'),
            'user_id' => null,
            'status' => null,
            'payment_method' => null,
        ])['kpis']['gross_revenue']);

        $this->actingAs($admin)
            ->patch(route('pdv.sales.cancel', $order, false), [
                'cancel_reason' => 'Venda teste lancada por engano.',
            ])
            ->assertRedirect(route('pdv.sales.show', $order, false));

        $order->refresh();
        $this->assertSame(ServiceOrder::STATUS_CANCELLED, $order->status);
        $this->assertSame($admin->id, $order->cancelled_by);
        $this->assertNotNull($order->cancelled_at);
        $this->assertDatabaseHas('payments', [
            'id' => $order->payment->id,
            'status' => Payment::STATUS_CANCELLED,
            'cancelled_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'type' => CashMovement::TYPE_OUTFLOW,
            'source_type' => Payment::class,
            'source_id' => $order->payment->id,
            'amount' => 100,
        ]);

        $this->assertSame(0.0, app(DailyDashboardService::class)->build($company->id, [
            'date' => CarbonImmutable::parse('2026-04-15'),
            'user_id' => null,
            'status' => null,
            'payment_method' => null,
        ])['kpis']['gross_revenue']);
    }

    public function test_cancel_membership_sale_without_usage_cancels_generated_membership(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano Teste',
            'price' => 70,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'cash',
            'membership_items' => [['membership_plan_id' => $plan->id]],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $order = ServiceOrder::query()->firstOrFail();
        $membership = CustomerMembership::query()->firstOrFail();
        $this->assertSame($order->id, $membership->service_order_id);

        $this->actingAs($admin)
            ->patch(route('pdv.sales.cancel', $order, false), [
                'cancel_reason' => 'Plano vendido para teste.',
            ])
            ->assertRedirect(route('pdv.sales.show', $order, false));

        $this->assertSame(CustomerMembership::STATUS_CANCELED, $membership->fresh()->status);
        $this->assertDatabaseHas('cash_movements', [
            'type' => CashMovement::TYPE_OUTFLOW,
            'source_type' => CustomerMembership::class,
            'source_id' => $membership->id,
            'amount' => 70,
        ]);
    }

    public function test_cancel_membership_sale_with_usage_is_blocked(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['active' => true]);
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano com Uso',
            'price' => 90,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'membership_items' => [['membership_plan_id' => $plan->id]],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $order = ServiceOrder::query()->firstOrFail();
        $membership = CustomerMembership::query()->firstOrFail();
        MembershipUsage::query()->create([
            'company_id' => $company->id,
            'customer_membership_id' => $membership->id,
            'client_id' => $client->id,
            'service_order_id' => $order->id,
            'service_id' => $service->id,
            'used_at' => now(),
            'quantity' => 1,
            'reference_type' => MembershipUsage::REF_SERVICE_ORDER,
            'reference_id' => $order->id,
            'description' => 'Uso parcial',
        ]);

        $this->actingAs($admin)
            ->patch(route('pdv.sales.cancel', $order, false), [
                'cancel_reason' => 'Teste com uso parcial.',
            ])
            ->assertSessionHasErrors('cancel_reason');

        $this->assertSame(ServiceOrder::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(CustomerMembership::STATUS_ACTIVE, $membership->fresh()->status);
    }

    public function test_user_without_permission_cannot_cancel_pdv_sale(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create(['active' => true, 'role' => 'staff']);
        $client = Client::factory()->for($company)->create();

        $order = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $staff->id,
            'status' => ServiceOrder::STATUS_PAID,
            'subtotal_services' => 25,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 25,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($staff)
            ->patch(route('pdv.sales.cancel', $order, false), [
                'cancel_reason' => 'Tentativa sem permissao.',
            ])
            ->assertForbidden();
    }

    public function test_force_delete_is_soft_delete_and_super_admin_only(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $superAdmin = User::factory()->for($company)->create([
            'active' => true,
            'role' => 'admin',
            'global_role' => 'super_admin',
        ]);
        $client = Client::factory()->for($company)->create();

        $order = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'professional_id' => $admin->id,
            'status' => ServiceOrder::STATUS_CANCELLED,
            'subtotal_services' => 30,
            'subtotal_products' => 0,
            'discount' => 0,
            'total' => 30,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(10),
            'cancelled_at' => now(),
            'cancelled_by' => $admin->id,
            'cancel_reason' => 'Teste',
        ]);

        $this->actingAs($admin)
            ->delete(route('pdv.sales.force-delete', $order, false), ['confirmation' => 'EXCLUIR'])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->delete(route('pdv.sales.force-delete', $order, false), ['confirmation' => 'EXCLUIR'])
            ->assertRedirect(route('pdv.sales', [], false));

        $this->assertSoftDeleted('service_orders', ['id' => $order->id]);
    }
}
