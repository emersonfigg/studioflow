<?php

namespace Tests\Feature;

use App\Enums\LegacyMembershipClassification as Classification;
use App\Enums\MembershipPaymentStatus;
use App\Enums\PaymentIntegrationEnvironment as Environment;
use App\Enums\PaymentProvider as Provider;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipInvoice;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\PaymentAccountCustomer;
use App\Models\PaymentWebhookEvent;
use App\Models\ServiceOrder;
use App\Models\SubscriptionStatusHistory;
use App\Services\LegacyMembershipClassifier;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\V2\FinancialAccountResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MembershipFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $driver = config('database.default');
        $this->assertContains($driver, ['sqlite', 'pgsql']);
        if ($driver === 'sqlite') {
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        } else {
            $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
            $this->assertSame('55438', (string) config('database.connections.pgsql.port'));
            $this->assertStringStartsWith('studioflow_finance_test_', config('database.connections.pgsql.database'));
            $this->assertStringContainsString('PostgreSQL 18.', DB::selectOne('SELECT version() AS version')->version);
        }
        Http::preventStrayRequests();
    }

    private function account(Company $company, Environment $environment = Environment::Sandbox, Provider $provider = Provider::Asaas): CompanyPaymentIntegration
    {
        return CompanyPaymentIntegration::create(['company_id' => $company->id, 'provider' => $provider, 'environment' => $environment, 'external_account_id' => 'account-'.$company->id.'-'.$environment->value, 'account_verified_at' => now(), 'active' => true]);
    }

    private function membership(Company $company, string $status = 'active'): CustomerMembership
    {
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::create(['company_id' => $company->id, 'name' => 'Legacy', 'price' => 50, 'billing_cycle' => 'monthly']);

        return CustomerMembership::create(['company_id' => $company->id, 'client_id' => $client->id, 'membership_plan_id' => $plan->id, 'status' => $status, 'starts_at' => '2026-10-01', 'current_cycle_starts_at' => '2026-10-01', 'current_cycle_ends_at' => '2026-10-30', 'auto_renew' => true]);
    }

    private function payment(CustomerMembership $membership, string $status, ?string $paidAt = null): MembershipPayment
    {
        return MembershipPayment::create(['company_id' => $membership->company_id, 'client_id' => $membership->client_id, 'customer_membership_id' => $membership->id, 'provider' => Provider::Asaas, 'amount' => 50, 'status' => $status, 'paid_at' => $paidAt]);
    }

    public function test_context_is_explicit_and_does_not_contain_credentials(): void
    {
        $company = Company::factory()->create();
        $sandbox = $this->account($company);
        $production = $this->account($company, Environment::Production);
        $resolver = new FinancialAccountResolver;
        $context = $resolver->resolve($company->id, $sandbox->id, Provider::Asaas, Environment::Sandbox);
        $other = $resolver->resolve($company->id, $production->id, Provider::Asaas, Environment::Production);
        $this->assertNotSame($context->key(), $other->key());
        $this->assertArrayNotHasKey('access_token', get_object_vars($context));
        Http::assertNothingSent();
    }

    public function test_context_rejects_other_company(): void
    {
        $account = $this->account(Company::factory()->create());
        $other = Company::factory()->create();
        $this->expectException(ModelNotFoundException::class);
        (new FinancialAccountResolver)->resolve($other->id, $account->id, Provider::Asaas, Environment::Sandbox);
    }

    public function test_context_rejects_environment_mismatch(): void
    {
        $company = Company::factory()->create();
        $account = $this->account($company);
        $this->expectException(ModelNotFoundException::class);
        (new FinancialAccountResolver)->resolve($company->id, $account->id, Provider::Asaas, Environment::Production);
    }

    public function test_unverified_legacy_account_is_not_silently_adopted(): void
    {
        $company = Company::factory()->create();
        $account = CompanyPaymentIntegration::create(['company_id' => $company->id, 'provider' => Provider::Asaas, 'environment' => Environment::Sandbox, 'account_identifier' => 'legacy-display-label']);
        $this->expectException(\DomainException::class);
        (new FinancialAccountResolver)->resolve($company->id, $account->id, Provider::Asaas, Environment::Sandbox);
    }

    public function test_verified_identity_cannot_be_reassigned_but_credentials_can_rotate(): void
    {
        $account = $this->account(Company::factory()->create());
        $account->update(['api_key' => 'replacement', 'active' => false]);
        $this->assertSame('replacement', $account->fresh()->api_key);
        $this->expectException(\DomainException::class);
        $account->update(['environment' => Environment::Production]);
    }

    public function test_existing_adapters_still_implement_v1_and_v2_fails_closed(): void
    {
        $company = Company::factory()->create();
        $manager = new PaymentGatewayManager;
        foreach (Provider::cases() as $provider) {
            $this->assertInstanceOf(PaymentGatewayInterface::class, $manager->gatewayFor($this->account($company, Environment::Sandbox, $provider)));
        }
        $account = $this->account($company);
        $context = (new FinancialAccountResolver)->resolve($company->id, $account->id, Provider::Asaas, Environment::Sandbox);
        try {
            $manager->recurringGatewayFor($context);
            $this->fail('V1 must not be treated as recurring V2.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('V2', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_reference_is_scoped_to_account_and_environment(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        foreach ([Environment::Sandbox, Environment::Production] as $environment) {
            $account = $this->account($company, $environment);
            PaymentAccountCustomer::create(['company_id' => $company->id, 'payment_account_id' => $account->id, 'client_id' => $client->id, 'external_customer_id' => 'cus_same']);
        }
        $this->assertDatabaseCount('payment_account_customers', 2);
    }

    public function test_new_financial_reference_rejects_cross_company_client(): void
    {
        $company = Company::factory()->create();
        $account = $this->account($company);
        $client = Client::factory()->for(Company::factory()->create())->create();
        $this->expectException(\DomainException::class);
        PaymentAccountCustomer::create(['company_id' => $company->id, 'payment_account_id' => $account->id, 'client_id' => $client->id, 'external_customer_id' => 'bad']);
    }

    public function test_invoice_rejects_membership_from_another_account(): void
    {
        $company = Company::factory()->create();
        $membership = $this->membership($company);
        $membership->update(['payment_account_id' => $this->account($company)->id]);
        $other = $this->account($company, Environment::Production);
        $this->expectException(\DomainException::class);
        MembershipInvoice::create(['company_id' => $company->id, 'payment_account_id' => $other->id, 'customer_membership_id' => $membership->id, 'external_invoice_id' => 'inv', 'amount' => 50, 'status' => 'pending', 'due_date' => '2026-10-20']);
    }

    public function test_event_id_is_unique_per_account(): void
    {
        $company = Company::factory()->create();
        $account = $this->account($company);
        $data = ['company_id' => $company->id, 'payment_account_id' => $account->id, 'external_event_id' => 'evt_1', 'event_type' => 'PAYMENT_CONFIRMED', 'received_at' => now(), 'payload_hash' => hash('sha256', '{}')];
        PaymentWebhookEvent::create($data);
        $other = $this->account($company, Environment::Production);
        PaymentWebhookEvent::create(array_replace($data, ['payment_account_id' => $other->id]));
        $this->assertDatabaseCount('payment_webhook_events', 2);
        $this->expectException(QueryException::class);
        PaymentWebhookEvent::create($data);
    }

    public function test_legacy_classification_is_read_only_and_never_authorizes_recurrence(): void
    {
        $company = Company::factory()->create();
        $membership = $this->membership($company, 'canceled');
        $before = $membership->fresh()->getRawOriginal();
        $classifier = new LegacyMembershipClassifier;
        $this->assertSame(Classification::Undetermined, $classifier->classify($company->id, $membership->id)['classification']);
        $this->payment($membership, MembershipPaymentStatus::Pending->value);
        $this->assertSame(Classification::Undetermined, $classifier->classify($company->id, $membership->id)['classification']);
        $this->payment($membership, MembershipPaymentStatus::Paid->value, '2026-10-01');
        $result = $classifier->classify($company->id, $membership->id);
        $this->assertSame(Classification::PaymentEvidence, $result['classification']);
        $this->assertFalse($result['evidence']['recurrence_authorized']);
        $this->assertSame($before, $membership->fresh()->getRawOriginal());
    }

    public function test_pos_classification_requires_paid_order_and_cash_evidence(): void
    {
        $company = Company::factory()->create();
        $membership = $this->membership($company);
        $order = ServiceOrder::create(['company_id' => $company->id, 'client_id' => $membership->client_id, 'status' => ServiceOrder::STATUS_PAID, 'opened_at' => now()]);
        $membership->update(['service_order_id' => $order->id]);
        $classifier = new LegacyMembershipClassifier;
        $this->assertSame(Classification::Undetermined, $classifier->classify($company->id, $membership->id)['classification']);
        $register = CashRegister::factory()->for($company)->create();
        CashMovement::create(['company_id' => $company->id, 'cash_register_id' => $register->id, 'type' => CashMovement::TYPE_INFLOW, 'amount' => 50, 'description' => 'fixture', 'payment_method' => 'cash', 'source_type' => CustomerMembership::class, 'source_id' => $membership->id, 'occurred_at' => now()]);
        $this->assertSame(Classification::PointOfSale, $classifier->classify($company->id, $membership->id)['classification']);
    }

    public function test_inconsistent_legacy_evidence_is_flagged_without_repair(): void
    {
        $company = Company::factory()->create();
        $membership = $this->membership($company, 'paused');
        $payment = $this->payment($membership, 'paid', '2026-10-01');
        DB::table('membership_payments')->where('id', $payment->id)->update(['company_id' => Company::factory()->create()->id]);
        $this->assertSame(Classification::Inconsistent, (new LegacyMembershipClassifier)->classify($company->id, $membership->id)['classification']);
        $this->assertSame('paused', $membership->fresh()->status);
    }

    public function test_classifier_cannot_read_another_company_membership(): void
    {
        $membership = $this->membership(Company::factory()->create());
        $this->expectException(ModelNotFoundException::class);
        (new LegacyMembershipClassifier)->classify(Company::factory()->create()->id, $membership->id);
    }

    public function test_inventory_command_reports_without_backfill_or_status_changes(): void
    {
        $company = Company::factory()->create();
        $membership = $this->membership($company, 'overdue');
        $before = $membership->fresh()->getRawOriginal();
        $this->artisan('memberships:inspect-legacy', ['company' => $company->id])->assertSuccessful();
        $this->assertSame($before, $membership->fresh()->getRawOriginal());
        $this->artisan('memberships:inspect-legacy', ['company' => 'invalid'])->assertExitCode(2);
        Http::assertNothingSent();
    }

    public function test_database_also_rejects_cross_company_references_without_eloquent(): void
    {
        $company = Company::factory()->create();
        $account = $this->account($company);
        $otherClient = Client::factory()->for(Company::factory()->create())->create();
        $this->expectException(QueryException::class);
        DB::table('payment_account_customers')->insert(['company_id' => $company->id, 'payment_account_id' => $account->id, 'client_id' => $otherClient->id, 'external_customer_id' => 'invalid']);
    }

    public function test_external_invoice_is_unique_within_financial_account(): void
    {
        $company = Company::factory()->create();
        $account = $this->account($company);
        $membership = $this->membership($company);
        $membership->update(['payment_account_id' => $account->id]);
        $data = ['company_id' => $company->id, 'payment_account_id' => $account->id, 'customer_membership_id' => $membership->id, 'external_invoice_id' => 'inv_1', 'amount' => '50.00', 'status' => 'pending', 'due_date' => '2026-10-20'];
        MembershipInvoice::create($data);
        $this->expectException(QueryException::class);
        MembershipInvoice::create($data);
    }

    public function test_external_transaction_cannot_be_duplicated(): void
    {
        $company = Company::factory()->create();
        $account = $this->account($company);
        $membership = $this->membership($company);
        $membership->update(['payment_account_id' => $account->id]);
        $data = ['company_id' => $company->id, 'payment_account_id' => $account->id, 'customer_membership_id' => $membership->id, 'client_id' => $membership->client_id, 'provider' => Provider::Asaas, 'amount' => '50.00', 'status' => 'pending', 'external_transaction_id' => 'txn_1'];
        MembershipPayment::create($data);
        $this->expectException(QueryException::class);
        MembershipPayment::create($data);
    }

    public function test_history_rejects_event_from_another_account_of_same_company(): void
    {
        $company = Company::factory()->create();
        $account = $this->account($company);
        $membership = $this->membership($company);
        $membership->update(['payment_account_id' => $account->id]);
        $other = $this->account($company, Environment::Production);
        $event = PaymentWebhookEvent::create(['company_id' => $company->id, 'payment_account_id' => $other->id, 'external_event_id' => 'evt_other', 'event_type' => 'PAYMENT_CONFIRMED', 'received_at' => now(), 'payload_hash' => hash('sha256', '{}')]);
        $this->expectException(\DomainException::class);
        SubscriptionStatusHistory::create(['company_id' => $company->id, 'customer_membership_id' => $membership->id, 'payment_webhook_event_id' => $event->id, 'new_status' => 'active', 'reason' => 'fixture', 'occurred_at' => now()]);
    }
}
