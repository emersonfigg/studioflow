<?php

namespace Tests\Feature;

use App\Enums\MembershipPaymentStatus;
use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MembershipPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAsaas(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, '/v3/customers') && $request->method() === 'POST') {
                return Http::response(['id' => 'cus_test_1'], 200);
            }

            if (str_contains($url, '/v3/payments') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pay_test_1',
                    'invoiceUrl' => 'https://sandbox.asaas.test/i/pay',
                    'status' => 'PENDING',
                    'dueDate' => '2026-05-20',
                    'value' => 99.9,
                    'billingType' => 'BOLETO',
                ], 200);
            }

            if (str_contains($url, '/v3/customers') && $request->method() === 'GET') {
                return Http::response(['object' => 'list', 'data' => [], 'totalCount' => 0], 200);
            }

            return Http::response(['error' => 'unmocked'], 500);
        });
    }

    public function test_company_without_gateway_cannot_create_online_membership_charge(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano',
            'price' => 50,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.memberships.store', $client), [
                'membership_plan_id' => $plan->id,
                'accepted_terms' => '1',
            ])
            ->assertSessionHasErrors('gateway');

        $this->assertDatabaseCount('customer_memberships', 0);
    }

    public function test_company_with_asaas_creates_pending_membership_and_payment(): void
    {
        $this->fakeAsaas();

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano',
            'price' => 99.9,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);

        CompanyPaymentIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => PaymentProvider::Asaas,
            'environment' => PaymentIntegrationEnvironment::Sandbox,
            'access_token' => '$aact_hmlg_testtoken',
            'active' => true,
            'default_for_memberships' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.memberships.store', $client), [
                'membership_plan_id' => $plan->id,
                'accepted_terms' => '1',
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('customer_memberships', [
            'client_id' => $client->id,
            'status' => CustomerMembership::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('membership_payments', [
            'client_id' => $client->id,
            'provider' => PaymentProvider::Asaas->value,
            'provider_payment_id' => 'pay_test_1',
            'status' => MembershipPaymentStatus::Pending->value,
        ]);
    }

    public function test_pending_membership_does_not_unlock_benefits(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano',
            'price' => 10,
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
            'ends_at' => null,
            'current_cycle_starts_at' => now()->toDateString(),
            'current_cycle_ends_at' => now()->addDays(29)->toDateString(),
            'auto_renew' => false,
        ]);

        MembershipPayment::query()->create([
            'company_id' => $company->id,
            'customer_membership_id' => $membership->id,
            'client_id' => $client->id,
            'provider' => PaymentProvider::Asaas,
            'provider_payment_id' => 'pay_x',
            'amount' => 10,
            'status' => MembershipPaymentStatus::Pending,
            'billing_type' => 'unknown',
            'cycle_starts_at' => $membership->current_cycle_starts_at,
            'cycle_ends_at' => $membership->current_cycle_ends_at,
        ]);

        $active = app(MembershipService::class)->getActiveMembershipForClient((int) $company->id, (int) $client->id, now());
        $this->assertNull($active);
    }

    public function test_webhook_paid_activates_membership_and_is_idempotent(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano',
            'price' => 10,
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
            'ends_at' => null,
            'current_cycle_starts_at' => now()->toDateString(),
            'current_cycle_ends_at' => now()->addDays(29)->toDateString(),
            'auto_renew' => false,
        ]);

        MembershipPayment::query()->create([
            'company_id' => $company->id,
            'customer_membership_id' => $membership->id,
            'client_id' => $client->id,
            'provider' => PaymentProvider::Asaas,
            'provider_payment_id' => 'pay_webhook_1',
            'amount' => 10,
            'status' => MembershipPaymentStatus::Pending,
            'billing_type' => 'unknown',
            'cycle_starts_at' => $membership->current_cycle_starts_at,
            'cycle_ends_at' => $membership->current_cycle_ends_at,
        ]);

        CompanyPaymentIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => PaymentProvider::Asaas,
            'environment' => PaymentIntegrationEnvironment::Sandbox,
            'access_token' => 'tok',
            'webhook_secret' => 'whsec',
            'active' => true,
            'default_for_memberships' => true,
        ]);

        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_webhook_1',
                'status' => 'CONFIRMED',
            ],
        ];

        $this->postJson(route('webhooks.company-payments.asaas'), $payload, [
            'asaas-access-token' => 'whsec',
        ])->assertOk();

        $membership->refresh();
        $this->assertSame(CustomerMembership::STATUS_ACTIVE, $membership->status);

        $this->postJson(route('webhooks.company-payments.asaas'), $payload, [
            'asaas-access-token' => 'whsec',
        ])->assertOk();

        $this->assertDatabaseCount('membership_payments', 1);
    }

    public function test_webhook_overdue_marks_membership_overdue(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano',
            'price' => 10,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);

        $membership = CustomerMembership::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => CustomerMembership::STATUS_ACTIVE,
            'starts_at' => now()->toDateString(),
            'ends_at' => null,
            'current_cycle_starts_at' => now()->toDateString(),
            'current_cycle_ends_at' => now()->addDays(29)->toDateString(),
            'auto_renew' => false,
        ]);

        MembershipPayment::query()->create([
            'company_id' => $company->id,
            'customer_membership_id' => $membership->id,
            'client_id' => $client->id,
            'provider' => PaymentProvider::Asaas,
            'provider_payment_id' => 'pay_od_1',
            'amount' => 10,
            'status' => MembershipPaymentStatus::Pending,
            'billing_type' => 'unknown',
            'cycle_starts_at' => $membership->current_cycle_starts_at,
            'cycle_ends_at' => $membership->current_cycle_ends_at,
        ]);

        CompanyPaymentIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => PaymentProvider::Asaas,
            'environment' => PaymentIntegrationEnvironment::Sandbox,
            'access_token' => 'tok',
            'active' => true,
            'default_for_memberships' => true,
        ]);

        $this->postJson(route('webhooks.company-payments.asaas'), [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => [
                'id' => 'pay_od_1',
                'status' => 'OVERDUE',
            ],
        ])->assertOk();

        $membership->refresh();
        $this->assertSame(CustomerMembership::STATUS_OVERDUE, $membership->status);

        $this->assertNull(app(MembershipService::class)->getActiveMembershipForClient((int) $company->id, (int) $client->id, now()));
    }

    public function test_admin_cannot_update_other_company_integration(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $adminA = User::factory()->admin()->for($companyA)->create();
        $integrationB = CompanyPaymentIntegration::query()->create([
            'company_id' => $companyB->id,
            'provider' => PaymentProvider::Asaas,
            'environment' => PaymentIntegrationEnvironment::Sandbox,
            'access_token' => 'secret-b',
            'active' => true,
            'default_for_memberships' => true,
        ]);

        $this->actingAs($adminA)
            ->patch(route('company.payment-integrations.update', $integrationB), [
                'provider' => PaymentProvider::Asaas->value,
                'environment' => PaymentIntegrationEnvironment::Sandbox->value,
                'active' => true,
                'default_for_memberships' => false,
            ])
            ->assertNotFound();
    }

    public function test_integration_secrets_are_encrypted_at_rest(): void
    {
        $company = Company::factory()->create();
        $integration = CompanyPaymentIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => PaymentProvider::Asaas,
            'environment' => PaymentIntegrationEnvironment::Sandbox,
            'access_token' => 'plain-secret-token-xyz',
            'active' => true,
            'default_for_memberships' => true,
        ]);

        $raw = (string) DB::table('company_payment_integrations')->where('id', $integration->id)->value('access_token');
        $this->assertStringNotContainsString('plain-secret-token-xyz', $raw);
        $this->assertSame('plain-secret-token-xyz', $integration->fresh()->access_token);
    }

    public function test_active_paid_membership_allows_benefits_legacy_without_payment_rows(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano',
            'price' => 10,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);

        CustomerMembership::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => CustomerMembership::STATUS_ACTIVE,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => null,
            'current_cycle_starts_at' => now()->subDay()->toDateString(),
            'current_cycle_ends_at' => now()->addDays(28)->toDateString(),
            'auto_renew' => false,
        ]);

        $m = app(MembershipService::class)->getActiveMembershipForClient((int) $company->id, (int) $client->id, now());
        $this->assertNotNull($m);
    }
}
