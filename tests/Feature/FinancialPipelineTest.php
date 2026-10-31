<?php

namespace Tests\Feature;

use App\Enums\PaymentIntegrationEnvironment as Environment;
use App\Enums\PaymentProvider;
use App\Jobs\IdentifyOverdueInvoices;
use App\Jobs\ProcessFinancialEvent;
use App\Jobs\ReconcileSubscription;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipInvoice;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\PaymentAccountCustomer;
use App\Models\PaymentWebhookEvent;
use App\Services\FinancialEventProcessor;
use App\Services\MembershipService;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\Payments\V2\FinancialAccountResolver;
use App\Services\Payments\V2\GatewayResource;
use App\Services\SubscriptionFinancialStateService;
use App\Services\SubscriptionReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeRecurringGateway;
use Tests\TestCase;

class FinancialPipelineTest extends TestCase
{
    use RefreshDatabase;

    private CompanyPaymentIntegration $account;

    private CustomerMembership $membership;

    private FakeRecurringGateway $gateway;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        Http::preventStrayRequests();
        Queue::fake();
        config(['financial.reconciliation_enabled' => true]);
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::create(['company_id' => $company->id, 'name' => 'Sandbox', 'price' => '50.00', 'billing_cycle' => 'monthly']);
        $this->secret = str_repeat('x', 32).$company->id;
        $this->account = CompanyPaymentIntegration::create(['company_id' => $company->id, 'provider' => PaymentProvider::Asaas, 'environment' => Environment::Sandbox,
            'external_account_id' => 'acct_'.$company->id, 'account_verified_at' => now(), 'webhook_secret' => $this->secret, 'active' => true]);
        $this->membership = CustomerMembership::create(['company_id' => $company->id, 'client_id' => $client->id, 'membership_plan_id' => $plan->id,
            'payment_account_id' => $this->account->id, 'billing_mode' => 'recurring_v2', 'origin' => 'sandbox_test', 'external_subscription_id' => 'sub_'.$company->id,
            'status' => 'pending', 'starts_at' => '2026-10-01', 'current_cycle_starts_at' => '2026-10-01', 'current_cycle_ends_at' => '2026-10-30',
            'commercial_snapshot' => ['amount' => '50.00', 'grace_days' => 2]]);
        PaymentAccountCustomer::create(['company_id' => $company->id, 'payment_account_id' => $this->account->id, 'client_id' => $client->id, 'external_customer_id' => 'cus_'.$client->id]);
        $this->gateway = new FakeRecurringGateway($this->snapshot());
        app(FinancialGatewayRegistry::class)->registerForTesting($this->account->id, $this->gateway);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function context(): FinancialAccountContext
    {
        return app(FinancialAccountResolver::class)->resolve($this->account->company_id, $this->account->id, PaymentProvider::Asaas, Environment::Sandbox);
    }

    private function snapshot(string $status = 'confirmed', array $replace = [], string $id = 'pay_1'): GatewayResource
    {
        return new GatewayResource($id, $status, array_replace(['account_id' => $this->account->external_account_id, 'subscription_id' => $this->membership->external_subscription_id,
            'customer_id' => 'cus_'.$this->membership->client_id, 'amount' => '50.00', 'currency' => 'BRL', 'due_date' => '2026-10-01',
            'coverage_starts_at' => '2026-10-01 00:00:00', 'coverage_ends_at' => '2026-11-01 00:00:00', 'observed_at' => now()->toIso8601String(),
            'confirmed_at' => '2026-10-01 10:00:00'], $replace));
    }

    private function payload(string $id = 'evt_1', array $replace = []): array
    {
        return array_replace_recursive(['id' => $id, 'event' => 'PAYMENT_CONFIRMED', 'account' => ['id' => $this->account->external_account_id],
            'payment' => ['id' => 'pay_1', 'status' => 'CONFIRMED']], $replace);
    }

    private function receive(?array $payload = null, ?string $secret = null, ?string $environment = null)
    {
        return $this->postJson(route('webhooks.company-payments.asaas.account', ['integration' => $this->account->id, 'environment' => $environment ?? 'sandbox']), $payload ?? $this->payload(), ['asaas-access-token' => $secret ?? $this->secret]);
    }

    private function process(): PaymentWebhookEvent
    {
        $event = PaymentWebhookEvent::query()->latest('id')->firstOrFail();
        app(FinancialEventProcessor::class)->process($event->id);

        return $event->fresh();
    }

    public function test_valid_secret_persists_safe_event_before_async_work(): void
    {
        $this->receive($this->payload(replace: ['payment' => ['creditCard' => ['number' => 'SENSITIVE'], 'customerName' => 'PRIVATE']]))->assertOk();
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseCount('membership_invoices', 0);
        $this->assertStringNotContainsString('SENSITIVE', json_encode(PaymentWebhookEvent::first()->safe_payload));
        $this->assertStringNotContainsString('PRIVATE', json_encode(PaymentWebhookEvent::first()->safe_payload));
        Queue::assertPushedOn('finance', ProcessFinancialEvent::class);
        Http::assertNothingSent();
    }

    public function test_secret_is_required(): void
    {
        $this->postJson(route('webhooks.company-payments.asaas'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_incorrect_secret_is_rejected(): void
    {
        $this->receive(secret: 'wrong')->assertForbidden();
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_account_and_environment_mismatches_are_rejected(): void
    {
        $this->receive($this->payload(replace: ['account' => ['id' => 'another_company']]))->assertForbidden();
        $this->receive(environment: 'production')->assertForbidden();
        $this->receive($this->payload(replace: ['environment' => 'production']))->assertForbidden();
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_same_secret_in_two_accounts_cannot_route_legacy_url(): void
    {
        CompanyPaymentIntegration::create(['company_id' => Company::factory()->create()->id, 'provider' => PaymentProvider::Asaas, 'environment' => 'sandbox', 'webhook_secret' => $this->secret]);
        $this->postJson(route('webhooks.company-payments.asaas'), $this->payload(), ['asaas-access-token' => $this->secret])->assertForbidden();
    }

    public function test_duplicate_event_and_repeated_processing_are_idempotent(): void
    {
        $this->receive()->assertOk();
        $this->receive()->assertOk();
        $this->process();
        $this->process();
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseCount('membership_payments', 1);
        $this->assertDatabaseCount('subscription_status_history', 1);
        $this->assertSame(1, $this->gateway->calls);
    }

    public function test_event_id_reuse_with_changed_content_is_rejected(): void
    {
        $this->receive()->assertOk();
        $this->receive($this->payload(replace: ['payment' => ['id' => 'other']]))->assertStatus(409);
    }

    public function test_unknown_event_is_quarantined_without_processing(): void
    {
        $this->receive($this->payload(replace: ['event' => 'UNRECOGNIZED_EVENT']))->assertOk();
        $this->assertSame('quarantined', PaymentWebhookEvent::first()->processing_status);
        Queue::assertNothingPushed();
    }

    public function test_renewal_creates_missing_invoice_and_shadow_payment_without_activating_benefits(): void
    {
        $before = $this->membership->fresh();
        $this->receive()->assertOk();
        $this->assertSame('processed', $this->process()->processing_status);
        $this->assertDatabaseCount('membership_invoices', 1);
        $this->assertDatabaseHas('membership_payments', ['status' => 'paid', 'cycle_starts_at' => null]);
        $after = $this->membership->fresh();
        $this->assertSame('active', $after->financial_status);
        $this->assertSame('pending', $after->status);
        $this->assertEquals($before->current_cycle_ends_at, $after->current_cycle_ends_at);
        $this->assertNull(app(MembershipService::class)->getActiveMembershipForClient($after->company_id, $after->client_id, now()));
    }

    public function test_late_payment_and_overdue_never_reactivate_canceled_contract(): void
    {
        $this->membership->update(['status' => 'canceled', 'canceled_at' => now()]);
        $this->receive()->assertOk();
        $this->process();
        $this->assertDatabaseHas('membership_payments', ['status' => 'paid']);
        $this->assertSame('canceled', $this->membership->fresh()->financial_status);
        $this->gateway->invoice = $this->snapshot('overdue', ['confirmed_at' => null], 'pay_2');
        $this->receive($this->payload('evt_late', ['payment' => ['id' => 'pay_2']]))->assertOk();
        $this->process();
        $this->assertSame('canceled', $this->membership->fresh()->status);
        $this->assertSame('canceled', $this->membership->fresh()->financial_status);
    }

    public function test_out_of_order_event_cannot_regress_paid_invoice(): void
    {
        $this->receive()->assertOk();
        $this->process();
        $this->gateway->invoice = $this->snapshot('overdue', ['confirmed_at' => null, 'observed_at' => '2026-10-19 10:00:00']);
        $this->receive($this->payload('evt_old', ['event' => 'PAYMENT_OVERDUE', 'payment' => ['status' => 'OVERDUE']]))->assertOk();
        $this->process();
        $this->assertSame('confirmed', MembershipInvoice::first()->status);
        $this->assertSame('active', $this->membership->fresh()->financial_status);
        $this->assertDatabaseCount('subscription_status_history', 1);
    }

    public function test_gateway_failure_is_safe_and_retry_succeeds(): void
    {
        $this->gateway->failOnce = true;
        $this->receive()->assertOk();
        try {
            $this->process();
            $this->fail('Must retry.');
        } catch (\RuntimeException $error) {
            $this->assertSame('financial_event_retry_required', $error->getMessage());
        }
        $this->assertSame('failed', PaymentWebhookEvent::first()->processing_status);
        $this->assertDatabaseCount('membership_payments', 0);
        $event = $this->process();
        $this->assertSame(2, $event->attempts);
        $this->assertSame('processed', $event->processing_status);
        $this->assertSame([1, 1], $this->gateway->transactionDepths); // Only the test's transaction was open.
    }

    public function test_overlapping_claims_and_stale_worker_are_fenced(): void
    {
        $this->receive()->assertOk();
        $event = PaymentWebhookEvent::first();
        $processor = app(FinancialEventProcessor::class);
        $oldToken = $processor->claim($event->id);
        $this->assertNull($processor->claim($event->id));
        PaymentWebhookEvent::whereKey($event->id)->update(['lease_until' => now()->subSecond()]);
        $newToken = $processor->claim($event->id);
        $this->assertNotSame($oldToken, $newToken);
        $this->expectException(\DomainException::class);
        app(SubscriptionReconciliationService::class)->applyInvoice($this->context(), $this->snapshot(), $event->id, $oldToken);
    }

    public function test_reconciliation_without_webhook_is_idempotent_and_audited(): void
    {
        $service = app(SubscriptionReconciliationService::class);
        $service->reconcile($this->context(), $this->membership->id);
        $service->reconcile($this->context(), $this->membership->id);
        $this->assertDatabaseCount('payment_webhook_events', 0);
        $this->assertDatabaseCount('membership_invoices', 1);
        $this->assertDatabaseCount('membership_payments', 1);
        $this->assertDatabaseCount('subscription_status_history', 1);
        $this->assertSame(2, DB::table('subscription_reconciliations')->where('status', 'completed')->count());
    }

    public function test_legacy_contract_is_not_adopted_or_modified(): void
    {
        $this->membership->update(['billing_mode' => null, 'payment_account_id' => null, 'status' => 'active']);
        $before = $this->membership->fresh()->getRawOriginal();
        $this->receive()->assertOk();
        $this->assertSame('quarantined', $this->process()->processing_status);
        $this->assertSame($before, $this->membership->fresh()->getRawOriginal());
        $this->assertDatabaseCount('membership_payments', 0);
    }

    public function test_cross_company_subscription_and_customer_mismatch_are_quarantined(): void
    {
        $this->gateway->invoice = $this->snapshot(replace: ['subscription_id' => 'sub_other_company']);
        $this->receive()->assertOk();
        $this->assertSame('uncorrelated_subscription', $this->process()->error_code);
        $this->gateway->invoice = $this->snapshot(replace: ['customer_id' => 'cus_other']);
        $this->receive($this->payload('evt_2'))->assertOk();
        $this->assertSame('customer_mismatch', $this->process()->error_code);
        $this->assertDatabaseCount('membership_payments', 0);
    }

    public function test_paused_contract_and_unpaid_gap_do_not_gain_financial_coverage(): void
    {
        $this->membership->update(['status' => 'paused']);
        $this->receive()->assertOk();
        $this->process();
        $this->assertSame('suspended', $this->membership->fresh()->financial_status);
        $this->assertSame('paused', $this->membership->fresh()->status);
        $this->assertSame('contract_paused', $this->membership->fresh()->suspension_reason);
    }

    public function test_grace_and_overdue_detection_use_paid_coverage_not_active_label(): void
    {
        $this->gateway->invoice = $this->snapshot(replace: ['coverage_ends_at' => '2026-10-19 00:00:00']);
        $this->receive()->assertOk();
        $this->process();
        $this->assertSame('overdue', $this->membership->fresh()->financial_status);
        Carbon::setTestNow('2026-10-22 10:00:00');
        app(SubscriptionFinancialStateService::class)->recalculate($this->membership->id);
        $this->assertSame('suspended', $this->membership->fresh()->financial_status);
        $this->assertSame('pending', $this->membership->fresh()->status);
    }

    public function test_production_execution_remains_disabled_even_with_flag_enabled(): void
    {
        $this->app->instance('env', 'production');
        $this->receive()->assertOk();
        $this->assertSame('financial_execution_disabled', $this->process()->error_code);
        $this->assertSame(0, $this->gateway->calls);
        $this->assertDatabaseCount('membership_payments', 0);
    }

    public function test_missing_adapter_and_disabled_feature_are_quarantined(): void
    {
        config(['financial.reconciliation_enabled' => false]);
        $this->receive()->assertOk();
        $this->assertSame('quarantined', $this->process()->processing_status);
        $this->assertSame(0, $this->gateway->calls);
        $this->artisan('financial:dispatch')->assertSuccessful();
    }

    public function test_reconciliation_failure_is_recorded_and_can_be_retried(): void
    {
        $this->gateway->pages = ['first' => ['items' => [$this->snapshot(replace: ['amount' => '999.00'])], 'next_cursor' => null]];
        try {
            app(SubscriptionReconciliationService::class)->reconcile($this->context(), $this->membership->id);
            $this->fail('Bad amount must be rejected.');
        } catch (\DomainException) {
            $this->assertSame('failed', DB::table('subscription_reconciliations')->latest('id')->value('status'));
        }
        $this->gateway->pages = [];
        app(SubscriptionReconciliationService::class)->reconcile($this->context(), $this->membership->id);
        $this->assertSame('completed', DB::table('subscription_reconciliations')->latest('id')->value('status'));
    }

    public function test_identify_overdue_does_not_replace_confirmed_payments(): void
    {
        $service = app(SubscriptionReconciliationService::class);
        $service->applyInvoice($this->context(), $this->snapshot('pending', ['confirmed_at' => null]));
        (new IdentifyOverdueInvoices($this->context(), $this->membership->id))->handle(app(FinancialGatewayRegistry::class), app(SubscriptionFinancialStateService::class));
        $this->assertSame('overdue', MembershipInvoice::first()->status);
        $service->applyInvoice($this->context(), $this->snapshot());
        (new IdentifyOverdueInvoices($this->context(), $this->membership->id))->handle(app(FinancialGatewayRegistry::class), app(SubscriptionFinancialStateService::class));
        $this->assertSame('confirmed', MembershipInvoice::first()->status);
    }

    public function test_authenticated_bound_legacy_charge_records_money_without_contract_activation(): void
    {
        config(['financial.reconciliation_enabled' => false]);
        $this->membership->update(['billing_mode' => null, 'status' => 'canceled', 'canceled_at' => now()]);
        $this->account->update(['api_key' => 'sandbox_test_only']);
        MembershipPayment::create(['company_id' => $this->account->company_id, 'payment_account_id' => $this->account->id, 'customer_membership_id' => $this->membership->id,
            'client_id' => $this->membership->client_id, 'provider' => 'asaas', 'provider_payment_id' => 'pay_1', 'origin' => 'legacy_charge',
            'amount' => '50.00', 'status' => 'pending', 'raw_payload' => ['customer' => 'cus_legacy']]);
        Http::fake(['api-sandbox.asaas.com/v3/payments/pay_1' => Http::response(['id' => 'pay_1', 'customer' => 'cus_legacy', 'value' => '50.00', 'status' => 'CONFIRMED', 'confirmedDate' => '2026-10-01'])]);
        $this->receive()->assertOk();
        $this->assertSame('processed', $this->process()->processing_status);
        $this->assertDatabaseHas('membership_payments', ['provider_payment_id' => 'pay_1', 'status' => 'paid']);
        $this->assertSame('canceled', $this->membership->fresh()->status);
        $this->assertDatabaseCount('membership_invoices', 0);
        Http::assertSentCount(1);
    }

    public function test_bound_legacy_charge_rejects_gateway_amount_mismatch(): void
    {
        $this->membership->update(['billing_mode' => null]);
        $this->account->update(['api_key' => 'sandbox_test_only']);
        MembershipPayment::create(['company_id' => $this->account->company_id, 'payment_account_id' => $this->account->id, 'customer_membership_id' => $this->membership->id,
            'client_id' => $this->membership->client_id, 'provider' => 'asaas', 'provider_payment_id' => 'pay_1', 'origin' => 'legacy_charge',
            'amount' => '50.00', 'status' => 'pending', 'raw_payload' => ['customer' => 'cus_legacy']]);
        Http::fake(['api-sandbox.asaas.com/v3/payments/pay_1' => Http::response(['id' => 'pay_1', 'customer' => 'cus_legacy', 'value' => '999.00', 'status' => 'CONFIRMED', 'confirmedDate' => '2026-10-01'])]);
        $this->receive()->assertOk();
        $this->assertSame('legacy_charge_mismatch', $this->process()->error_code);
        $this->assertDatabaseHas('membership_payments', ['provider_payment_id' => 'pay_1', 'status' => 'pending']);
    }

    public function test_paid_future_invoice_does_not_bridge_unpaid_coverage_gap(): void
    {
        $this->gateway->invoice = $this->snapshot(replace: ['coverage_starts_at' => '2026-11-01', 'coverage_ends_at' => '2026-12-01', 'due_date' => '2026-11-01']);
        $this->receive()->assertOk();
        $this->process();
        $this->assertNull($this->membership->fresh()->paid_through);
        $this->assertSame('pending', $this->membership->fresh()->financial_status);
    }

    public function test_actual_missing_v2_capability_does_not_fallback_to_v1_recurring(): void
    {
        $this->app->instance(FinancialGatewayRegistry::class, new FinancialGatewayRegistry);
        $this->receive()->assertOk();
        $this->assertSame('capability_unavailable', $this->process()->error_code);
        $this->assertDatabaseCount('membership_payments', 0);
        Http::assertNothingSent();
    }

    public function test_exhausted_event_is_not_retried_forever(): void
    {
        $this->receive()->assertOk();
        PaymentWebhookEvent::query()->update(['attempts' => 5, 'processing_status' => 'failed']);
        $this->process();
        $this->assertSame(5, PaymentWebhookEvent::first()->attempts);
        $this->assertSame(0, $this->gateway->calls);
    }

    public function test_confirmation_without_authoritative_date_cannot_create_coverage(): void
    {
        $this->gateway->invoice = $this->snapshot(replace: ['confirmed_at' => null]);
        $this->receive()->assertOk();
        $this->assertSame('quarantined', $this->process()->processing_status);
        $this->assertDatabaseCount('membership_payments', 0);
        $this->assertNull($this->membership->fresh()->paid_through);
    }

    public function test_paginated_reconciliation_and_cursor_loop_are_handled(): void
    {
        $this->gateway->pages = ['first' => ['items' => [], 'next_cursor' => 'second'], 'second' => ['items' => [$this->snapshot()], 'next_cursor' => null]];
        app(SubscriptionReconciliationService::class)->reconcile($this->context(), $this->membership->id);
        $this->assertDatabaseCount('membership_payments', 1);
        $this->gateway->pages['second']['next_cursor'] = 'second';
        $this->expectException(\DomainException::class);
        app(SubscriptionReconciliationService::class)->reconcile($this->context(), $this->membership->id);
    }

    public function test_dispatch_recovers_persisted_event_and_schedules_reconciliation(): void
    {
        $this->receive()->assertOk();
        Queue::fake();
        $this->artisan('financial:dispatch')->assertSuccessful();
        Queue::assertPushed(ProcessFinancialEvent::class);
        Queue::assertPushed(ReconcileSubscription::class);
        Queue::assertPushed(IdentifyOverdueInvoices::class);
    }

    public function test_financial_refund_suspends_shadow_coverage_without_mutating_contract(): void
    {
        $this->receive()->assertOk();
        $this->process();
        $this->gateway->invoice = $this->snapshot('refunded');
        $this->receive($this->payload('evt_refund', ['event' => 'PAYMENT_REFUNDED']))->assertOk();
        $this->process();
        $this->assertSame('suspended', $this->membership->fresh()->financial_status);
        $this->assertSame('financial_payment_reversed', $this->membership->fresh()->suspension_reason);
        $this->assertSame('pending', $this->membership->fresh()->status);
        $this->assertDatabaseHas('membership_payments', ['status' => 'refunded']);
        $this->gateway->invoice = $this->snapshot();
        $this->receive($this->payload('evt_stale_paid'))->assertOk();
        $this->process();
        $this->assertSame('refunded', MembershipInvoice::first()->status);
    }

    public function test_contract_start_in_future_prevents_early_financial_activation(): void
    {
        $this->membership->update(['starts_at' => '2026-11-01']);
        $this->gateway->invoice = $this->snapshot(replace: ['coverage_starts_at' => '2026-11-01', 'coverage_ends_at' => '2026-12-01']);
        $this->receive()->assertOk();
        $this->process();
        $this->assertSame('pending', $this->membership->fresh()->financial_status);
    }

    public function test_event_recovery_runs_even_when_recurring_execution_is_disabled(): void
    {
        $this->receive()->assertOk();
        Queue::fake();
        config(['financial.reconciliation_enabled' => false]);
        $this->artisan('financial:dispatch')->assertSuccessful();
        Queue::assertPushed(ProcessFinancialEvent::class);
        Queue::assertNotPushed(ReconcileSubscription::class);
    }

    public function test_authenticated_unverified_account_is_durably_quarantined(): void
    {
        $unverified = CompanyPaymentIntegration::create(['company_id' => $this->account->company_id, 'provider' => 'asaas', 'environment' => 'sandbox', 'webhook_secret' => 'unverified_secret']);
        $this->postJson(route('webhooks.company-payments.asaas.account', ['integration' => $unverified->id, 'environment' => 'sandbox']), $this->payload(), ['asaas-access-token' => 'unverified_secret'])->assertOk();
        $this->assertSame('account_unverified', PaymentWebhookEvent::first()->error_code);
        $this->assertSame('quarantined', PaymentWebhookEvent::first()->processing_status);
        Queue::assertNothingPushed();
    }
}
