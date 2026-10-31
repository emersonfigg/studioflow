<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Models\Company;
use App\Models\CustomerMembership;
use App\Models\MembershipCheckoutIntention;
use App\Services\Payments\Contracts\V2\RecurringPaymentGatewayInterface;
use App\Services\Payments\FinancialAmount;
use App\Services\Payments\V2\CheckoutRequest;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\Payments\V2\GatewayResource;
use App\Services\Payments\V2\NormalizedEvent;
use App\Services\Payments\V2\PublicMembershipAccount;
use App\Services\Payments\V2\SubscriptionCheckout;
use App\Services\PublicMembershipCatalog;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Sandbox-only adapter. V1 adapters and appointment payments remain independent. */
class AsaasRecurringGateway implements RecurringPaymentGatewayInterface
{
    private function request(FinancialAccountContext $context, string $method, string $path, array $data = []): array
    {
        if (app()->environment('production') || $context->environment !== PaymentIntegrationEnvironment::Sandbox
            || $context->provider !== PaymentProvider::Asaas || config('financial.asaas_v2_enabled') !== true
            || config('financial.reconciliation_enabled') !== true) {
            throw new DomainException('financial_execution_disabled');
        }
        $account = app(PublicMembershipAccount::class)->assertReady($context);
        // No automatic retry, even for creates. Never include remote error bodies in exceptions or logs.
        try {
            $http = Http::baseUrl('https://api-sandbox.asaas.com/v3')->acceptJson()->withHeaders(['access_token' => $account->api_key, 'User-Agent' => 'StudioFlow/recurring-v2'])
                ->connectTimeout(5)->timeout(25)->withoutRedirecting();
            if ($method !== 'GET') {
                $status = $http->get('/myAccount/status');
                if (! $status->successful() || $status->json('id') !== $context->externalAccountId || $status->json('general') !== 'APPROVED') {
                    throw new \RuntimeException('asaas_account_not_ready');
                }
            }
            $response = $http->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
            if (! $response->successful() || ! is_array($response->json())) {
                throw new \RuntimeException('asaas_request_failed');
            }

            return $response->json();
        } catch (\Throwable) {
            throw new \RuntimeException('asaas_request_failed');
        }
    }

    private function id(mixed $id): string
    {
        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]{1,200}$/D', $id)) {
            throw new DomainException('resource_mismatch');
        }

        return $id;
    }

    public function createCustomer(FinancialAccountContext $context, int $clientId, array $customer): GatewayResource
    {
        $intention = MembershipCheckoutIntention::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
            ->where('client_id', $clientId)->where('reference', $customer['externalReference'] ?? '')->firstOrFail();
        if (! $intention->creation_started_at || $intention->external_customer_id) {
            throw new DomainException('customer_creation_already_claimed');
        }
        if (! MembershipCheckoutIntention::whereKey($intention->id)->whereNull('customer_request_started_at')->update(['customer_request_started_at' => now()])) {
            throw new DomainException('customer_creation_already_claimed');
        }
        $data = $this->request($context, 'POST', '/customers', array_intersect_key($customer, array_flip(['name', 'email', 'mobilePhone', 'externalReference'])));

        return new GatewayResource($this->id($data['id'] ?? null), 'created', ['account_id' => $context->externalAccountId]);
    }

    public function createSubscriptionCheckout(FinancialAccountContext $context, CheckoutRequest $request): SubscriptionCheckout
    {
        $intention = MembershipCheckoutIntention::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
            ->where('reference', $request->intentionId)->where('client_id', $request->clientId)->firstOrFail();
        $snapshot = $intention->commercial_snapshot;
        if (! $intention->external_customer_id || $intention->external_checkout_id || $intention->status !== 'creating'
            || FinancialAmount::cents($snapshot['amount']) !== $request->amountCents || $snapshot['cycle'] !== $request->cycle
            || ! hash_equals($intention->terms_version, $request->termsVersion) || ! isset(PublicMembershipCatalog::MONTHS[$request->cycle])) {
            throw new DomainException('checkout_binding_mismatch');
        }
        if (! MembershipCheckoutIntention::whereKey($intention->id)->whereNull('checkout_request_started_at')->update(['checkout_request_started_at' => now()])) {
            throw new DomainException('checkout_creation_already_claimed');
        }
        $company = Company::findOrFail($context->companyId);
        $callback = array_fill_keys(['successUrl', 'cancelUrl', 'expiredUrl'], route('public-memberships.result', [$company, $intention->reference]));
        $data = $this->request($context, 'POST', '/checkouts', ['billingTypes' => ['CREDIT_CARD'], 'chargeTypes' => ['RECURRENT'], 'minutesToExpire' => 60,
            'externalReference' => $intention->reference, 'customer' => $intention->external_customer_id, 'callback' => $callback,
            'items' => [['name' => $snapshot['name'], 'description' => mb_substr((string) ($snapshot['description'] ?? ''), 0, 500), 'quantity' => 1, 'value' => $request->amountCents / 100]],
            'subscription' => ['cycle' => $request->cycle, 'nextDueDate' => $snapshot['first_due_date'].' 12:00:00']]);
        $id = $this->id($data['id'] ?? null);

        return new SubscriptionCheckout($id, $this->checkoutUrl($id), CarbonImmutable::now()->addHour()->toDateTimeImmutable());
    }

    public function checkoutUrl(string $id): string
    {
        return 'https://sandbox.asaas.com/checkoutSession/show?id='.rawurlencode($this->id($id));
    }

    public function getSubscription(FinancialAccountContext $context, string $subscriptionId): GatewayResource
    {
        $data = $this->request($context, 'GET', '/subscriptions/'.$this->id($subscriptionId));
        if (($data['id'] ?? null) !== $subscriptionId) {
            throw new DomainException('resource_mismatch');
        }

        return new GatewayResource($subscriptionId, match ($data['status'] ?? '') {
            'ACTIVE' => 'active', 'INACTIVE' => 'inactive', 'EXPIRED' => 'expired', default => throw new DomainException('untrusted_gateway_snapshot'),
        }, ['account_id' => $context->externalAccountId, 'customer_id' => $data['customer'] ?? null, 'amount' => $data['value'] ?? null,
            'cycle' => $data['cycle'] ?? null, 'billing_type' => $data['billingType'] ?? null, 'external_reference' => $data['externalReference'] ?? null]);
    }

    /** First binding requires an authoritative payment returned by the checkoutSession filter. */
    public function correlateSubscription(FinancialAccountContext $context, GatewayResource $subscription): CustomerMembership
    {
        $existing = CustomerMembership::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
            ->where('billing_mode', 'recurring_v2')->where('external_subscription_id', $subscription->id)->first();
        $intention = MembershipCheckoutIntention::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
            ->where('external_customer_id', $subscription->data['customer_id'] ?? '')->first();
        if (! $intention || ! $intention->external_checkout_id) {
            throw new DomainException('uncorrelated_subscription');
        }
        $snapshot = $intention->commercial_snapshot;
        if (($subscription->data['account_id'] ?? null) !== $context->externalAccountId || ($subscription->data['cycle'] ?? null) !== $snapshot['cycle']
            || ($subscription->data['billing_type'] ?? null) !== 'CREDIT_CARD'
            || FinancialAmount::cents($subscription->data['amount'] ?? null) !== FinancialAmount::cents($snapshot['amount'])) {
            throw new DomainException('subscription_snapshot_mismatch');
        }
        if (! $existing) {
            $page = $this->request($context, 'GET', '/payments', ['checkoutSession' => $intention->external_checkout_id, 'subscription' => $subscription->id, 'limit' => 100, 'offset' => 0]);
            $proof = collect($page['data'] ?? [])->contains(fn ($p) => ($p['subscription'] ?? null) === $subscription->id
                && ($p['customer'] ?? null) === $intention->external_customer_id && FinancialAmount::cents($p['value'] ?? null) === FinancialAmount::cents($snapshot['amount']));
            if (! $proof) {
                throw new DomainException('uncorrelated_subscription');
            }
        }

        return DB::transaction(function () use ($intention, $subscription, $context) {
            $membership = CustomerMembership::where('company_id', $context->companyId)->whereKey($intention->customer_membership_id)->lockForUpdate()->firstOrFail();
            if ($membership->external_subscription_id && $membership->external_subscription_id !== $subscription->id) {
                throw new DomainException('subscription_binding_changed');
            }
            $membership->update(['external_subscription_id' => $subscription->id]);

            return $membership;
        }, 3);
    }

    public function getInvoice(FinancialAccountContext $context, string $invoiceId): GatewayResource
    {
        $data = $this->request($context, 'GET', '/payments/'.$this->id($invoiceId));
        if (($data['id'] ?? null) !== $invoiceId || ! is_string($data['subscription'] ?? null) || ($data['billingType'] ?? null) !== 'CREDIT_CARD') {
            throw new DomainException('resource_mismatch');
        }
        $subscription = $this->getSubscription($context, $data['subscription']);
        $membership = $this->correlateSubscription($context, $subscription);
        $snapshot = $membership->commercial_snapshot;
        $anchor = CarbonImmutable::parse($snapshot['first_due_date'])->startOfDay();
        $due = CarbonImmutable::parse($data['originalDueDate'] ?? $data['dueDate'])->startOfDay();
        $months = PublicMembershipCatalog::MONTHS[$snapshot['cycle']];
        $distance = ($due->year - $anchor->year) * 12 + $due->month - $anchor->month;
        if ($distance < 0 || $distance % $months !== 0 || ! $anchor->addMonthsNoOverflow($distance)->eq($due)) {
            throw new DomainException('invalid_financial_dates');
        }
        $end = $anchor->addMonthsNoOverflow($distance + $months);
        $status = match ($data['status'] ?? '') {
            'PENDING', 'AWAITING_RISK_ANALYSIS' => 'pending', 'CONFIRMED' => 'confirmed', 'RECEIVED' => 'received', 'OVERDUE' => 'overdue',
            'CREDIT_CARD_CAPTURE_REFUSED' => 'failed', 'REFUNDED', 'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL' => 'refunded',
            'DELETED' => 'canceled', default => throw new DomainException('untrusted_gateway_snapshot'),
        };

        return new GatewayResource($invoiceId, $status, ['account_id' => $context->externalAccountId, 'subscription_id' => $data['subscription'],
            'customer_id' => $data['customer'] ?? null, 'amount' => $data['value'] ?? null, 'currency' => 'BRL', 'due_date' => $due->toDateString(),
            'coverage_starts_at' => $due->toDateTimeString(), 'coverage_ends_at' => $end->toDateTimeString(), 'observed_at' => now()->toIso8601String(),
            'confirmed_at' => $data['confirmedDate'] ?? $data['paymentDate'] ?? null, 'received_at' => $status === 'received' ? ($data['paymentDate'] ?? null) : null]);
    }

    public function getPaymentStatus(FinancialAccountContext $context, string $invoiceId): GatewayResource
    {
        return $this->getInvoice($context, $invoiceId);
    }

    public function cancelRenewal(FinancialAccountContext $context, string $subscriptionId, string $policy): GatewayResource
    {
        if ($policy !== 'stop_future_preserve_open_invoices') {
            throw new DomainException('unsupported_cancellation_policy');
        }
        $subscription = $this->getSubscription($context, $subscriptionId);
        $membership = $this->correlateSubscription($context, $subscription);
        if ($subscription->status === 'active') {
            $this->request($context, 'PUT', '/subscriptions/'.$this->id($subscriptionId), ['status' => 'INACTIVE', 'updatePendingPayments' => false]);
        }
        $result = $this->getSubscription($context, $subscriptionId);
        if ($result->status === 'inactive') {
            // Financial evidence only; operational benefits remain unchanged until stage 4.
            $membership->update(['cancel_requested_at' => now(), 'gateway_subscription_status' => 'inactive']);
        }

        return $result;
    }

    public function normalizeWebhook(FinancialAccountContext $context, array $authenticatedPayload): NormalizedEvent
    {
        if (($authenticatedPayload['account']['id'] ?? $context->externalAccountId) !== $context->externalAccountId) {
            throw new DomainException('resource_mismatch');
        }
        $type = (string) ($authenticatedPayload['event'] ?? '');
        $resource = str_starts_with($type, 'PAYMENT_') ? 'payment' : (str_starts_with($type, 'SUBSCRIPTION_') ? 'subscription' : 'checkout');
        $data = $authenticatedPayload[$resource] ?? [];

        return new NormalizedEvent((string) $authenticatedPayload['id'], $context->externalAccountId, $type, $resource,
            $this->id($data['id'] ?? null), new \DateTimeImmutable($authenticatedPayload['dateCreated'] ?? 'now'), (string) ($data['status'] ?? 'unknown'));
    }

    public function listChargesForReconciliation(FinancialAccountContext $context, ?string $cursor = null): array
    {
        if ($cursor !== null && ! ctype_digit($cursor)) {
            throw new DomainException('invalid_reconciliation_cursor');
        }
        $offset = (int) ($cursor ?? 0);
        $page = $this->request($context, 'GET', '/payments', ['offset' => $offset, 'limit' => 100]);
        $items = [];
        foreach ($page['data'] ?? [] as $payment) {
            // Ignore unrelated historical payments; do not infer ownership from amount or phone.
            if (isset($payment['subscription']) && CustomerMembership::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
                ->where('billing_mode', 'recurring_v2')->where('origin', 'public_checkout')->where('external_subscription_id', $payment['subscription'])->exists()) {
                $items[] = $this->getInvoice($context, $this->id($payment['id']));
            }
        }
        if (($page['hasMore'] ?? false) && count($page['data'] ?? []) === 0) {
            throw new DomainException('invalid_reconciliation_cursor');
        }

        return ['items' => $items, 'next_cursor' => ($page['hasMore'] ?? false) ? (string) ($offset + count($page['data'])) : null];
    }

    /** Recovery of an unbound checkout is based on the persisted checkout ID, never customer phone or matching price alone. */
    public function listCheckoutCharges(FinancialAccountContext $context, int $intentionId, ?string $cursor = null): array
    {
        $intention = MembershipCheckoutIntention::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
            ->whereNotNull('public_identity_id')->whereNotNull('external_checkout_id')->whereNotNull('external_customer_id')->findOrFail($intentionId);
        if ($cursor !== null && ! ctype_digit($cursor)) {
            throw new DomainException('invalid_reconciliation_cursor');
        }
        $offset = (int) ($cursor ?? 0);
        $page = $this->request($context, 'GET', '/payments', ['checkoutSession' => $intention->external_checkout_id, 'limit' => 100, 'offset' => $offset]);
        $items = [];
        foreach ($page['data'] ?? [] as $payment) {
            if (($payment['customer'] ?? null) !== $intention->external_customer_id || empty($payment['subscription'])) {
                throw new DomainException('customer_mismatch');
            }
            $items[] = $this->getInvoice($context, $this->id($payment['id'] ?? null));
        }
        if (($page['hasMore'] ?? false) && count($page['data'] ?? []) === 0) {
            throw new DomainException('invalid_reconciliation_cursor');
        }

        return ['items' => $items, 'next_cursor' => ($page['hasMore'] ?? false) ? (string) ($offset + count($page['data'])) : null];
    }
}
