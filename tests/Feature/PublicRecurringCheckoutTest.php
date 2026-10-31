<?php

namespace Tests\Feature;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Jobs\ReconcileMembershipCheckout;
use App\Mail\MembershipIdentityCode;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipCheckoutIntention;
use App\Models\MembershipInvoice;
use App\Models\MembershipPlan;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use App\Services\FinancialEventProcessor;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\Gateways\AsaasRecurringGateway;
use App\Services\Payments\V2\PublicMembershipAccount;
use App\Services\PublicMembershipCatalog;
use App\Services\SubscriptionReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicRecurringCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private CompanyPaymentIntegration $account;

    private MembershipPlan $plan;

    private string $paymentStatus = 'CONFIRMED';

    private string $subscriptionStatus = 'ACTIVE';

    private string $due = '2026-10-23';

    private string $paymentId = 'pay_first';

    private bool $timeout = false;

    private bool $proof = true;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-23 10:00:00');
        Carbon::setTestNow('2026-10-23 10:00:00');
        config(['financial.reconciliation_enabled' => true, 'financial.asaas_v2_enabled' => true, 'financial.public_checkout_enabled' => true]);
        $this->withoutVite();
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();
        $this->company = Company::factory()->create(['active' => true]);
        $this->account = CompanyPaymentIntegration::create(['company_id' => $this->company->id, 'provider' => PaymentProvider::Asaas,
            'environment' => PaymentIntegrationEnvironment::Sandbox, 'external_account_id' => 'account_test', 'account_verified_at' => now(),
            'api_key' => 'sandbox-secret', 'webhook_secret' => str_repeat('w', 40), 'active' => true, 'default_for_memberships' => true,
            'status' => 'connected', 'connected_at' => now(), 'account_enablement_status' => 'approved']);
        $this->account->mergeMetadata(['recurring_v2_credential_fingerprint' => app(PublicMembershipAccount::class)->credentialFingerprint($this->account)]);
        $this->plan = MembershipPlan::create(['company_id' => $this->company->id, 'name' => 'Clube Seguro', 'description' => 'Plano de homologação',
            'price' => '99.90', 'billing_cycle' => 'monthly', 'active' => true, 'auto_renew' => true, 'terms_text' => 'Renovação mensal no cartão.']);
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/v3/myAccount/status') {
                return Http::response(['id' => 'account_test', 'general' => 'APPROVED']);
            }
            if ($path === '/v3/customers' && $request->method() === 'POST') {
                return Http::response(['id' => 'cus_dedicated']);
            }
            if ($path === '/v3/checkouts') {
                if ($this->timeout) {
                    throw new ConnectionException('Private remote error');
                }

                return Http::response(['id' => 'checkout_test']);
            }
            if ($path === '/v3/subscriptions/sub_test') {
                if ($request->method() === 'PUT') {
                    $this->subscriptionStatus = 'INACTIVE';
                }

                return Http::response(['id' => 'sub_test', 'customer' => 'cus_dedicated', 'status' => $this->subscriptionStatus, 'value' => '99.90', 'cycle' => 'MONTHLY', 'billingType' => 'CREDIT_CARD']);
            }
            if ($path === '/v3/payments') {
                return Http::response(['hasMore' => false, 'data' => $this->proof ? [$this->payment()] : []]);
            }
            if ($path === '/v3/payments/'.$this->paymentId) {
                return Http::response($this->payment());
            }

            return Http::response([], 404);
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payment(): array
    {
        return ['id' => $this->paymentId, 'subscription' => 'sub_test', 'customer' => 'cus_dedicated', 'value' => '99.90',
            'billingType' => 'CREDIT_CARD', 'status' => $this->paymentStatus, 'dueDate' => $this->due, 'confirmedDate' => '2026-10-23', 'paymentDate' => '2026-10-23'];
    }

    private function identify(string $email = 'buyer@example.test'): void
    {
        $this->post(route('public-memberships.identify', $this->company), ['email' => $email, 'membership_plan_id' => $this->plan->id])->assertRedirect();
        $code = Mail::sent(MembershipIdentityCode::class)->last()->code;
        $this->post(route('public-memberships.verify', $this->company), ['code' => $code, 'membership_plan_id' => $this->plan->id])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function order(array $replace = [])
    {
        return $this->post(route('public-memberships.store', $this->company), array_replace(['membership_plan_id' => $this->plan->id, 'name' => 'Cliente Teste',
            'phone' => '71999990000', 'accepted_terms' => '1', 'terms_version' => app(PublicMembershipCatalog::class)->snapshot($this->plan->fresh()->load('services'))['terms_version']], $replace));
    }

    private function enroll(): MembershipCheckoutIntention
    {
        $this->identify();
        $this->order()->assertRedirect('https://sandbox.asaas.com/checkoutSession/show?id=checkout_test');

        return MembershipCheckoutIntention::firstOrFail();
    }

    private function event(string $type, string $id = 'evt_test', array $replace = []): PaymentWebhookEvent
    {
        $resource = str_starts_with($type, 'CHECKOUT_') ? 'checkout' : (str_starts_with($type, 'SUBSCRIPTION_') ? 'subscription' : 'payment');
        $body = [$resource => ['id' => match ($resource) {
            'checkout' => 'checkout_test', 'subscription' => 'sub_test', default => $this->paymentId
        }, 'customer' => 'cus_dedicated'],
            'id' => $id, 'event' => $type, 'account' => ['id' => 'account_test']];
        $this->postJson(route('webhooks.company-payments.asaas.account', [$this->account->id, 'sandbox']), array_replace_recursive($body, $replace),
            ['asaas-access-token' => str_repeat('w', 40)])->assertOk();
        $event = PaymentWebhookEvent::where('external_event_id', $id)->firstOrFail();
        app(FinancialEventProcessor::class)->process($event->id);

        return $event->fresh();
    }

    public function test_active_plan_visible_before_booking_services(): void
    {
        $this->get(route('public-bookings.create', $this->company))->assertOk()->assertSeeInOrder(['CLUBE DE ASSINATURAS', 'Clube Seguro', 'Assinar agora', 'booking-filters']);
    }

    public function test_inactive_plan_is_not_available(): void
    {
        $this->plan->update(['active' => false]);
        $this->get(route('public-bookings.create', $this->company))->assertOk()->assertDontSee('Clube Seguro');
        $this->post(route('public-memberships.identify', $this->company), ['email' => 'buyer@example.test', 'membership_plan_id' => $this->plan->id])->assertNotFound();
    }

    public function test_valid_secure_public_contract_and_exact_account_checkout(): void
    {
        $intention = $this->enroll();
        $this->assertSame($this->account->id, $intention->payment_account_id);
        $this->assertSame('ready', $intention->status);
        $this->assertSame('pending', CustomerMembership::first()->status);
        $this->assertNotNull(CustomerMembership::first()->accepted_terms_at);
        Http::assertSent(fn ($r) => $r->url() === 'https://api-sandbox.asaas.com/v3/checkouts' && $r->hasHeader('access_token', 'sandbox-secret')
            && $r['customer'] === 'cus_dedicated' && $r['billingTypes'] === ['CREDIT_CARD'] && $r['chargeTypes'] === ['RECURRENT'] && $r['externalReference'] === $intention->reference);
    }

    public function test_unverified_account_blocks_enrollment(): void
    {
        DB::table('company_payment_integrations')->where('id', $this->account->id)->update(['account_verified_at' => null]);
        $this->get(route('public-bookings.create', $this->company))->assertOk()->assertDontSee('Clube Seguro');
        $this->assertDatabaseCount('customer_memberships', 0);
        Http::assertNothingSent();
    }

    public function test_repeated_order_and_new_verified_session_do_not_duplicate(): void
    {
        $this->enroll();
        $this->order()->assertRedirect();
        $this->identify();
        $this->order()->assertRedirect();
        $this->assertDatabaseCount('customer_memberships', 1);
        $this->assertDatabaseCount('membership_checkout_intentions', 1);
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/checkouts')));
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/customers')));
    }

    public function test_callback_cannot_confirm_finances(): void
    {
        $intention = $this->enroll();
        $this->get(route('public-memberships.result', [$this->company, $intention->reference]).'?status=paid')->assertOk();
        $this->assertSame('pending', CustomerMembership::first()->financial_status);
        $this->assertDatabaseCount('membership_invoices', 0);
    }

    public function test_payment_confirmed_correlates_checkout_and_sets_only_financial_state(): void
    {
        $this->enroll();
        $this->assertSame('processed', $this->event('PAYMENT_CONFIRMED')->processing_status);
        $membership = CustomerMembership::first();
        $this->assertSame('active', $membership->financial_status);
        $this->assertSame('pending', $membership->status);
        $this->assertSame('sub_test', $membership->external_subscription_id);
        $this->assertDatabaseCount('membership_invoices', 1);
        $this->assertSame('2026-10-23', $membership->current_cycle_starts_at->toDateString());
        $this->assertFalse($membership->isUsable());
    }

    public function test_abandoned_checkout_has_no_financial_activation(): void
    {
        $this->enroll();
        $this->assertSame('processed', $this->event('CHECKOUT_CANCELED')->processing_status);
        $this->assertSame('canceled', MembershipCheckoutIntention::first()->status);
        $this->assertSame('pending', CustomerMembership::first()->financial_status);
    }

    public function test_checkout_expiration_does_not_create_another_checkout(): void
    {
        $this->enroll();
        $this->event('CHECKOUT_EXPIRED');
        $this->order()->assertRedirect();
        $this->assertSame('expired', MembershipCheckoutIntention::first()->status);
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/checkouts')));
    }

    public function test_payment_refusal_does_not_unlock_benefits(): void
    {
        $this->enroll();
        $this->paymentStatus = 'CREDIT_CARD_CAPTURE_REFUSED';
        $this->event('PAYMENT_CREDIT_CARD_CAPTURE_REFUSED');
        $this->assertSame('failed', MembershipInvoice::first()->status);
        $this->assertFalse(CustomerMembership::first()->isUsable());
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $this->enroll();
        $this->event('PAYMENT_CONFIRMED');
        $this->event('PAYMENT_CONFIRMED');
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseCount('membership_invoices', 1);
        $this->assertDatabaseCount('membership_payments', 1);
    }

    public function test_out_of_order_events_do_not_regress_paid_invoice_or_checkout(): void
    {
        $this->enroll();
        $this->event('PAYMENT_CONFIRMED');
        $this->event('CHECKOUT_PAID', 'evt_paid');
        $this->event('CHECKOUT_CREATED', 'evt_late');
        $this->paymentStatus = 'PENDING';
        $this->event('PAYMENT_CREATED', 'evt_old');
        $this->assertSame('confirmed', MembershipInvoice::first()->status);
        $this->assertSame('awaiting_payment', MembershipCheckoutIntention::first()->status);
    }

    public function test_company_isolation_on_plan_identity_and_callback(): void
    {
        $intention = $this->enroll();
        $other = Company::factory()->create();
        $this->get(route('public-memberships.result', [$other, $intention->reference]))->assertForbidden();
        $foreign = MembershipPlan::create(['company_id' => $other->id, 'name' => 'Foreign', 'price' => '99.90', 'billing_cycle' => 'monthly', 'active' => true]);
        $this->order(['membership_plan_id' => $foreign->id])->assertNotFound();
    }

    public function test_recurring_renewal_without_local_invoice_creates_next_coverage(): void
    {
        $this->enroll();
        $this->event('PAYMENT_CONFIRMED');
        $this->paymentId = 'pay_renewal';
        $this->due = '2026-11-23';
        $this->event('PAYMENT_CONFIRMED', 'evt_renewal');
        $this->assertDatabaseCount('membership_invoices', 2);
        $this->assertSame('2026-12-23', CustomerMembership::first()->paid_through->toDateString());
    }

    public function test_cancel_renewal_preserves_existing_invoices_and_contract_status(): void
    {
        $this->enroll();
        $this->event('PAYMENT_CONFIRMED');
        $context = app(PublicMembershipAccount::class)->resolve($this->company->id);
        $result = app(AsaasRecurringGateway::class)->cancelRenewal($context, 'sub_test', 'stop_future_preserve_open_invoices');
        $this->assertSame('inactive', $result->status);
        $this->assertDatabaseCount('membership_invoices', 1);
        $this->assertSame('pending', CustomerMembership::first()->status);
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
    }

    public function test_booking_remains_accessible_with_checkout_disabled(): void
    {
        config(['financial.public_checkout_enabled' => false]);
        $this->get(route('public-bookings.create', $this->company))->assertOk()->assertSee('booking-filters')->assertDontSee('CLUBE DE ASSINATURAS');
        Http::assertNothingSent();
    }

    public function test_timeout_never_automatically_repeats_customer_or_checkout_creation(): void
    {
        $this->identify();
        $this->timeout = true;
        $this->order()->assertRedirect();
        $this->order()->assertRedirect();
        $this->assertSame('creation_unknown', MembershipCheckoutIntention::first()->status);
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/customers')));
        $this->assertDatabaseCount('customer_memberships', 1);
        $this->assertSame('pending', CustomerMembership::first()->financial_status);
    }

    public function test_phone_alone_does_not_identify_or_reveal_existing_customer(): void
    {
        $old = Client::factory()->for($this->company)->create(['phone' => '71999990000', 'email' => 'old@example.test', 'name' => 'Private Existing Name']);
        $this->order()->assertForbidden();
        $this->enroll();
        $this->assertNotSame($old->id, CustomerMembership::first()->client_id);
        $this->get(route('public-memberships.index', [$this->company, 'plan' => $this->plan->id]))->assertDontSee('Private Existing Name');
    }

    public function test_wrong_or_consumed_email_code_is_rejected(): void
    {
        $this->post(route('public-memberships.identify', $this->company), ['email' => 'buyer@example.test', 'membership_plan_id' => $this->plan->id]);
        $code = Mail::sent(MembershipIdentityCode::class)->last()->code;
        $this->post(route('public-memberships.verify', $this->company), ['code' => '000000', 'membership_plan_id' => $this->plan->id])->assertSessionHasErrors('code');
        $this->post(route('public-memberships.verify', $this->company), ['code' => $code, 'membership_plan_id' => $this->plan->id])->assertRedirect();
        $this->post(route('public-memberships.verify', $this->company), ['code' => $code, 'membership_plan_id' => $this->plan->id])->assertSessionHasErrors('code');
    }

    public function test_unproven_subscription_remains_quarantined(): void
    {
        $this->enroll();
        $this->proof = false;
        $this->assertSame('quarantined', $this->event('PAYMENT_CONFIRMED')->processing_status);
        $this->assertNull(CustomerMembership::first()->external_subscription_id);
        $this->assertDatabaseCount('membership_invoices', 0);
    }

    public function test_checkout_paid_alone_does_not_activate_finances(): void
    {
        $this->enroll();
        $this->event('CHECKOUT_PAID');
        $this->assertSame('pending', CustomerMembership::first()->financial_status);
        $this->assertDatabaseCount('membership_invoices', 0);
    }

    public function test_disconnected_account_and_ambiguous_defaults_fail_closed(): void
    {
        $this->identify();
        $this->account->update(['active' => false]);
        $this->order()->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('membership_checkout_intentions', 0);
        Http::assertNothingSent();
    }

    public function test_production_remains_blocked_even_with_flags_enabled(): void
    {
        app()->detectEnvironment(fn () => 'production');
        try {
            $this->assertCount(0, app(PublicMembershipCatalog::class)->plans($this->company));
            Http::assertNothingSent();
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_price_change_requires_fresh_terms_acceptance(): void
    {
        $this->identify();
        $version = app(PublicMembershipCatalog::class)->snapshot($this->plan->load('services'))['terms_version'];
        $this->plan->update(['price' => '110.00']);
        $this->order(['terms_version' => $version])->assertSessionHasErrors('terms_version');
        $this->assertDatabaseCount('customer_memberships', 0);
    }

    public function test_credential_rotation_requires_new_account_verification(): void
    {
        $this->identify();
        $this->account->update(['api_key' => 'rotated-other-key']);
        $this->order()->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('customer_memberships', 0);
        Http::assertNothingSent();
    }

    public function test_api_account_identity_mismatch_prevents_any_create(): void
    {
        $this->identify();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://api-sandbox.asaas.com/v3/myAccount/status' => Http::response(['id' => 'another_account', 'general' => 'APPROVED'])]);
        $this->order()->assertRedirect();
        $this->assertSame('creation_unknown', MembershipCheckoutIntention::first()->status);
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
    }

    public function test_subscription_event_can_correlate_but_never_confirms_payment(): void
    {
        $this->enroll();
        $this->assertSame('processed', $this->event('SUBSCRIPTION_CREATED')->processing_status);
        $this->assertSame('sub_test', CustomerMembership::first()->external_subscription_id);
        $this->assertSame('pending', CustomerMembership::first()->financial_status);
        $this->assertDatabaseCount('membership_invoices', 0);
    }

    public function test_stop_renewal_preserves_paid_coverage_during_reconciliation(): void
    {
        $this->enroll();
        $this->event('PAYMENT_CONFIRMED');
        $this->subscriptionStatus = 'INACTIVE';
        $this->event('SUBSCRIPTION_INACTIVATED', 'evt_stop');
        $this->assertSame('inactive', CustomerMembership::first()->gateway_subscription_status);
        $this->assertSame('active', CustomerMembership::first()->financial_status);
        $this->assertSame('2026-11-23', CustomerMembership::first()->paid_through->toDateString());
    }

    public function test_calendar_renewal_at_month_end_does_not_drift(): void
    {
        CarbonImmutable::setTestNow('2026-01-31 10:00:00');
        Carbon::setTestNow('2026-01-31 10:00:00');
        $this->due = '2026-01-31';
        $this->enroll();
        $this->event('PAYMENT_CONFIRMED');
        $this->paymentId = 'pay_feb';
        $this->due = '2026-02-28';
        $this->event('PAYMENT_CONFIRMED', 'evt_feb');
        $this->assertSame('2026-03-31', CustomerMembership::first()->paid_through->toDateString());
    }

    public function test_pagination_and_invalid_cursor_are_explicit(): void
    {
        $this->enroll();
        $this->event('PAYMENT_CONFIRMED');
        $context = app(PublicMembershipAccount::class)->resolve($this->company->id);
        $gateway = app(AsaasRecurringGateway::class);
        $page = $gateway->listChargesForReconciliation($context, '100');
        $this->assertCount(1, $page['items']);
        $this->assertNull($page['next_cursor']);
        Http::assertSent(fn ($r) => parse_url($r->url(), PHP_URL_PATH) === '/v3/payments' && ($r['offset'] ?? null) == 100);
        $this->expectException(\DomainException::class);
        $gateway->listChargesForReconciliation($context, 'invalid');
    }

    public function test_sandbox_account_verification_reads_identity_wallet_and_approval_only(): void
    {
        DB::table('company_payment_integrations')->where('id', $this->account->id)->update(['account_verified_at' => null, 'external_account_id' => null]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://api-sandbox.asaas.com/v3/wallets' => Http::response(['data' => [['id' => 'wallet_test']]]),
            'https://api-sandbox.asaas.com/v3/myAccount/status' => Http::response(['id' => 'account_test', 'general' => 'APPROVED'])]);
        $this->artisan('financial:verify-asaas-sandbox', ['integration' => $this->account->id, 'account-id' => 'account_test', 'wallet-id' => 'wallet_test', '--confirm-account-ownership' => true])->assertSuccessful();
        $this->assertNotNull($this->account->fresh()->account_verified_at);
        app(PublicMembershipAccount::class)->resolve($this->company->id);
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
    }

    public function test_verification_rejects_foreign_external_account_id(): void
    {
        DB::table('company_payment_integrations')->where('id', $this->account->id)->update(['account_verified_at' => null, 'external_account_id' => null]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://api-sandbox.asaas.com/v3/wallets' => Http::response(['data' => [['id' => 'wallet_test']]]),
            'https://api-sandbox.asaas.com/v3/myAccount/status' => Http::response(['id' => 'foreign_account', 'general' => 'APPROVED'])]);
        $this->artisan('financial:verify-asaas-sandbox', ['integration' => $this->account->id, 'account-id' => 'account_test', 'wallet-id' => 'wallet_test', '--confirm-account-ownership' => true])->assertFailed();
        $this->assertNull($this->account->fresh()->account_verified_at);
    }

    public function test_scheduler_recovers_checkout_when_initial_webhooks_are_missing(): void
    {
        $intention = $this->enroll();
        $this->artisan('financial:dispatch')->assertSuccessful();
        Queue::assertPushed(ReconcileMembershipCheckout::class, fn ($job) => $job->intentionId === $intention->id && $job->context->integrationId === $this->account->id);
        $context = app(PublicMembershipAccount::class)->resolve($this->company->id);
        $job = new ReconcileMembershipCheckout($context, $intention->id);
        $job->handle(app(SubscriptionReconciliationService::class), app(FinancialGatewayRegistry::class));
        $this->assertSame('sub_test', CustomerMembership::first()->external_subscription_id);
        $this->assertSame('active', CustomerMembership::first()->financial_status);
        $this->assertSame('pending', CustomerMembership::first()->status);
        $this->assertDatabaseCount('payment_webhook_events', 0);
        $this->assertDatabaseCount('membership_invoices', 1);
    }

    public function test_admin_cancellation_stops_gateway_renewal_without_canceling_paid_contract(): void
    {
        $this->enroll();
        $this->event('PAYMENT_CONFIRMED');
        $admin = User::factory()->admin()->for($this->company)->create();
        $membership = CustomerMembership::first();
        $this->actingAs($admin)->patch(route('customer-memberships.cancel', $membership))->assertSessionHas('status', 'membership-renewal-canceled');
        $this->get(route('clients.show', $membership->client_id))->assertOk()->assertSee('Renovação cancelada')->assertSee('Pagamento confirmado');
        $this->assertSame('INACTIVE', $this->subscriptionStatus);
        $this->assertFalse($membership->fresh()->auto_renew);
        $this->assertSame('pending', $membership->fresh()->status);
        $this->assertSame('active', $membership->fresh()->financial_status);
    }

    public function test_old_admin_resume_cannot_unlock_public_contract_before_financial_confirmation(): void
    {
        $this->enroll();
        $admin = User::factory()->admin()->for($this->company)->create();
        $membership = CustomerMembership::first();
        $this->actingAs($admin)->patch(route('customer-memberships.resume', $membership))->assertSessionHasErrors('membership');
        $this->assertSame('pending', $membership->fresh()->status);
        $this->assertFalse($membership->fresh()->isUsable());
    }
}
