<?php

namespace Tests\Feature;

use App\Enums\MembershipPaymentBillingType;
use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Payments\Gateways\MercadoPagoGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyPaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mercado_pago.client_id', 'mp-client-id');
        config()->set('services.mercado_pago.client_secret', 'mp-client-secret');
        config()->set('services.mercado_pago.oauth_redirect_uri', 'http://localhost/company/payment-integrations/mercado-pago/callback');
        config()->set('services.mercado_pago.api_base_url', 'https://api.mercadopago.com');
        config()->set('services.mercado_pago.auth_base_url', 'https://auth.mercadopago.com.br');
    }

    public function test_connect_redirects_to_mercado_pago_with_state_and_redirect_uri(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $response = $this->actingAs($admin)->get(route('company.payment-integrations.mercado-pago.connect', absolute: false));

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('https://auth.mercadopago.com.br/authorization', (string) $location);
        $this->assertStringContainsString('client_id=mp-client-id', (string) $location);
        $this->assertStringContainsString(urlencode('http://localhost/company/payment-integrations/mercado-pago/callback'), (string) $location);

        $state = session('mercado_pago_oauth_state');
        $this->assertIsArray($state);
        $this->assertSame($company->id, $state['company_id']);
        $this->assertNotEmpty($state['state']);
        $this->assertStringContainsString('state='.$state['state'], (string) $location);
    }

    public function test_connect_is_blocked_when_oauth_uses_placeholder_configuration(): void
    {
        config()->set('services.mercado_pago.client_id', 'SEU_CLIENT_ID');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $response = $this->actingAs($admin)->get(route('company.payment-integrations.mercado-pago.connect', absolute: false));

        $response->assertRedirect(route('company.payment-integrations.index', absolute: false));
        $response->assertSessionHasErrors('oauth');
    }

    public function test_callback_without_valid_state_fails(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $response = $this->actingAs($admin)->get(route('company.payment-integrations.mercado-pago.callback', [
            'code' => 'valid-code',
            'state' => 'invalid-state',
        ], false));

        $response->assertRedirect(route('company.payment-integrations.index', absolute: false));
        $response->assertSessionHasErrors('oauth');
    }

    public function test_callback_with_error_returns_friendly_message(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $this->actingAs($admin)->withSession([
            'mercado_pago_oauth_state' => [
                'state' => 'safe-state',
                'company_id' => $company->id,
                'issued_at' => now()->timestamp,
            ],
        ]);

        $response = $this->get(route('company.payment-integrations.mercado-pago.callback', [
            'state' => 'safe-state',
            'error' => 'access_denied',
            'error_description' => 'O vendedor recusou a autorização.',
        ], false));

        $response->assertRedirect(route('company.payment-integrations.index', absolute: false));
        $response->assertSessionHasErrors('oauth');
    }

    public function test_callback_with_valid_code_saves_integration(): void
    {
        Http::fake([
            'https://api.mercadopago.com/oauth/token' => Http::response([
                'access_token' => 'mp-access-token',
                'refresh_token' => 'mp-refresh-token',
                'expires_in' => 3600,
                'user_id' => 998877,
                'public_key' => 'APP_USR-public-key',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $this->actingAs($admin)->withSession([
            'mercado_pago_oauth_state' => [
                'state' => 'safe-state',
                'company_id' => $company->id,
                'issued_at' => now()->timestamp,
            ],
        ]);

        $response = $this->get(route('company.payment-integrations.mercado-pago.callback', [
            'state' => 'safe-state',
            'code' => 'oauth-valid-code',
        ], false));

        $response->assertOk();
        $response->assertSee('Mercado Pago conectado');
        $response->assertSee('Voltar ao StudioFlow');

        $integration = CompanyPaymentIntegration::query()
            ->where('company_id', $company->id)
            ->where('provider', PaymentProvider::MercadoPago)
            ->firstOrFail();

        $this->assertSame('mp-access-token', $integration->access_token);
        $this->assertSame('mp-refresh-token', $integration->refresh_token);
        $this->assertSame('998877', $integration->account_identifier);
        $this->assertSame('connected', $integration->status);
        $this->assertTrue($integration->active);
        $this->assertNotNull($integration->connected_at);
        $this->assertNotNull($integration->expires_at);
    }

    public function test_callback_saves_tokens_encrypted_at_rest(): void
    {
        Http::fake([
            'https://api.mercadopago.com/oauth/token' => Http::response([
                'access_token' => 'secret-oauth-access',
                'refresh_token' => 'secret-oauth-refresh',
                'expires_in' => 3600,
                'user_id' => 1010,
            ], 200),
        ]);

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);

        $this->actingAs($admin)->withSession([
            'mercado_pago_oauth_state' => [
                'state' => 'enc-state',
                'company_id' => $company->id,
                'issued_at' => now()->timestamp,
            ],
        ]);

        $this->get(route('company.payment-integrations.mercado-pago.callback', [
            'state' => 'enc-state',
            'code' => 'enc-code',
        ], false))
            ->assertOk()
            ->assertSee('Mercado Pago conectado');

        $integration = CompanyPaymentIntegration::query()->where('company_id', $company->id)->firstOrFail();
        $rawAccess = (string) DB::table('company_payment_integrations')->where('id', $integration->id)->value('access_token');
        $rawRefresh = (string) DB::table('company_payment_integrations')->where('id', $integration->id)->value('refresh_token');

        $this->assertStringNotContainsString('secret-oauth-access', $rawAccess);
        $this->assertStringNotContainsString('secret-oauth-refresh', $rawRefresh);
    }

    public function test_callback_does_not_create_integration_for_other_company(): void
    {
        Http::fake();

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $adminB = User::factory()->admin()->for($companyB)->create(['active' => true]);

        $this->actingAs($adminB)->withSession([
            'mercado_pago_oauth_state' => [
                'state' => 'cross-company-state',
                'company_id' => $companyA->id,
                'issued_at' => now()->timestamp,
            ],
        ]);

        $this->get(route('company.payment-integrations.mercado-pago.callback', [
            'state' => 'cross-company-state',
            'code' => 'oauth-code',
        ], false))
            ->assertRedirect(route('company.payment-integrations.index', absolute: false))
            ->assertSessionHasErrors('oauth');

        $this->assertDatabaseCount('company_payment_integrations', 0);
    }

    public function test_disconnect_marks_integration_inactive(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $integration = CompanyPaymentIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => PaymentProvider::MercadoPago,
            'environment' => PaymentIntegrationEnvironment::Production,
            'access_token' => 'mp-token',
            'refresh_token' => 'mp-refresh',
            'active' => true,
            'status' => 'connected',
        ]);

        $this->actingAs($admin)
            ->post(route('company.payment-integrations.mercado-pago.disconnect', absolute: false))
            ->assertRedirect(route('company.payment-integrations.index', absolute: false));

        $integration->refresh();
        $this->assertFalse($integration->active);
        $this->assertSame('disconnected', $integration->status);
    }

    public function test_gateway_uses_company_token(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('Bearer company-oauth-token', $request->header('Authorization')[0] ?? null);

            return Http::response(['id' => 123], 200);
        });

        $company = Company::factory()->create();
        $integration = CompanyPaymentIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => PaymentProvider::MercadoPago,
            'environment' => PaymentIntegrationEnvironment::Production,
            'access_token' => 'company-oauth-token',
            'refresh_token' => 'refresh-x',
            'active' => true,
            'status' => 'connected',
            'expires_at' => now()->addDays(30),
        ]);

        app(PaymentGatewayManager::class)->gatewayFor($integration)->ping();
    }

    public function test_refresh_token_updates_access_and_refresh_tokens(): void
    {
        Http::fake([
            'https://api.mercadopago.com/oauth/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 7200,
                'user_id' => 777,
            ], 200),
            'https://api.mercadopago.com/users/me' => Http::response(['id' => 777], 200),
        ]);

        $company = Company::factory()->create();
        $integration = CompanyPaymentIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => PaymentProvider::MercadoPago,
            'environment' => PaymentIntegrationEnvironment::Production,
            'access_token' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
            'active' => true,
            'status' => 'connected',
            'expires_at' => now()->addDays(1),
        ]);

        (new MercadoPagoGateway($integration))->ping();

        $integration->refresh();
        $this->assertSame('new-access-token', $integration->access_token);
        $this->assertSame('new-refresh-token', $integration->refresh_token);
        $this->assertSame('777', $integration->account_identifier);
        $this->assertSame('connected', $integration->status);
    }

    public function test_company_without_connected_mercado_pago_cannot_generate_charge(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano MP',
            'price' => 89.90,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);

        $membership = CustomerMembership::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => CustomerMembership::STATUS_PENDING,
            'starts_at' => now()->toDateString(),
            'current_cycle_starts_at' => now()->toDateString(),
            'current_cycle_ends_at' => now()->addDays(29)->toDateString(),
            'auto_renew' => false,
        ]);

        $integration = CompanyPaymentIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => PaymentProvider::MercadoPago,
            'environment' => PaymentIntegrationEnvironment::Production,
            'active' => true,
            'status' => 'connected',
            'access_token' => null,
        ]);

        $this->expectExceptionMessage('Conecte a conta Mercado Pago da empresa antes de gerar cobranças.');

        (new MercadoPagoGateway($integration))->createMembershipCharge($membership, [
            'billing_type' => MembershipPaymentBillingType::Unknown,
        ]);
    }
}
