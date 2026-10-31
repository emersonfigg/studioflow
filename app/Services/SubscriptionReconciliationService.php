<?php

namespace App\Services;

use App\Enums\MembershipPaymentStatus;
use App\Models\CustomerMembership;
use App\Models\MembershipCheckoutIntention;
use App\Models\MembershipInvoice;
use App\Models\MembershipPayment;
use App\Models\PaymentAccountCustomer;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\FinancialAmount;
use App\Services\Payments\FinancialErrorCode;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\Payments\V2\GatewayResource;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionReconciliationService
{
    public function __construct(private readonly FinancialGatewayRegistry $gateways, private readonly SubscriptionFinancialStateService $states) {}

    /** Validate an authoritative snapshot. No financial conclusions from webhook bodies. */
    public function applyInvoice(FinancialAccountContext $context, GatewayResource $resource, ?int $eventId = null, ?string $leaseToken = null, ?int $expectedMembershipId = null): void
    {
        $data = $resource->data;
        if (($data['account_id'] ?? null) !== $context->externalAccountId || $resource->id === ''
            || ! in_array($resource->status, ['pending', 'confirmed', 'received', 'overdue', 'failed', 'canceled', 'refunded'], true)) {
            throw new DomainException('untrusted_gateway_snapshot');
        }
        $membership = CustomerMembership::query()->where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
            ->where('external_subscription_id', $data['subscription_id'] ?? '')->where('billing_mode', 'recurring_v2')->first();
        if (! $membership || ($expectedMembershipId !== null && (int) $membership->id !== $expectedMembershipId)) {
            throw new DomainException('uncorrelated_subscription');
        }
        $customer = PaymentAccountCustomer::query()->where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
            ->where('client_id', $membership->client_id)->value('external_customer_id');
        if ($membership->origin === 'public_checkout') {
            $customer = MembershipCheckoutIntention::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
                ->where('customer_membership_id', $membership->id)->value('external_customer_id');
        }
        if (! $customer || $customer !== ($data['customer_id'] ?? null)) {
            throw new DomainException('customer_mismatch');
        }
        $amount = (string) ($data['amount'] ?? '');
        $cents = FinancialAmount::cents($data['amount'] ?? null);
        if ($cents === null || $cents <= 0
            || ($data['currency'] ?? null) !== 'BRL'
            || $cents !== FinancialAmount::cents(data_get($membership->commercial_snapshot, 'amount'))) {
            throw new DomainException('amount_mismatch');
        }
        foreach (['due_date', 'coverage_starts_at', 'coverage_ends_at', 'observed_at'] as $date) {
            if (empty($data[$date]) || ! is_string($data[$date])) {
                throw new DomainException('missing_financial_dates');
            }
        }
        try {
            $start = Carbon::parse($data['coverage_starts_at']);
            $end = Carbon::parse($data['coverage_ends_at']);
            $observed = Carbon::parse($data['observed_at']);
            $due = Carbon::parse($data['due_date'])->toDateString();
            $confirmed = isset($data['confirmed_at']) ? Carbon::parse($data['confirmed_at']) : null;
            $received = isset($data['received_at']) ? Carbon::parse($data['received_at']) : null;
        } catch (\Throwable) {
            throw new DomainException('invalid_financial_dates');
        }
        if ($end->lte($start) || $observed->gt(now()->addMinutes(5)) || (in_array($resource->status, ['confirmed', 'received'], true) && ! $confirmed)) {
            throw new DomainException('invalid_financial_dates');
        }
        DB::transaction(function () use ($context, $membership, $resource, $amount, $due, $start, $end, $observed, $confirmed, $received, $eventId, $leaseToken) {
            if ($eventId !== null) {
                $event = PaymentWebhookEvent::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
                if ($event->processing_token !== $leaseToken || $event->processing_status !== 'processing' || $event->lease_until->lt(now())) {
                    throw new DomainException('stale_processing_lease');
                }
            }
            $locked = CustomerMembership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->payment_account_id !== $context->integrationId || $locked->billing_mode !== 'recurring_v2'
                || $locked->external_subscription_id !== $membership->external_subscription_id
                || (int) $locked->client_id !== (int) $membership->client_id || $locked->commercial_snapshot !== $membership->commercial_snapshot) {
                throw new DomainException('subscription_binding_changed');
            }
            $invoice = MembershipInvoice::query()->where('payment_account_id', $context->integrationId)->where('external_invoice_id', $resource->id)->first();
            if ($invoice && (int) $invoice->customer_membership_id !== (int) $locked->id) {
                throw new DomainException('invoice_binding_mismatch');
            }
            if ($invoice && $invoice->gateway_observed_at && $invoice->gateway_observed_at->gt($observed)) {
                return;
            }
            if ($invoice && (! $invoice->coverage_starts_at->eq($start) || ! $invoice->coverage_ends_at->eq($end))) {
                throw new DomainException('invoice_coverage_changed');
            }
            if ($invoice && ($invoice->status === 'refunded' && $resource->status !== 'refunded')) {
                return;
            }
            if ($invoice && in_array($invoice->status, ['confirmed', 'received'], true) && in_array($resource->status, ['pending', 'overdue', 'failed', 'canceled'], true)) {
                return;
            }
            if ($invoice && $invoice->status === 'received' && $resource->status === 'confirmed') {
                return;
            }
            $invoice = MembershipInvoice::query()->updateOrCreate(['payment_account_id' => $context->integrationId, 'external_invoice_id' => $resource->id],
                ['company_id' => $context->companyId, 'customer_membership_id' => $locked->id, 'currency' => 'BRL', 'amount' => $amount,
                    'status' => $resource->status, 'due_date' => $due, 'coverage_starts_at' => $start, 'coverage_ends_at' => $end,
                    'confirmed_at' => $confirmed, 'received_at' => $received, 'gateway_observed_at' => $observed]);
            $status = match ($resource->status) {
                'confirmed', 'received' => MembershipPaymentStatus::Paid,
                'overdue' => MembershipPaymentStatus::Overdue, 'failed' => MembershipPaymentStatus::Failed,
                'refunded' => MembershipPaymentStatus::Refunded, 'canceled' => MembershipPaymentStatus::Canceled,
                default => MembershipPaymentStatus::Pending,
            };
            // V1 provider_payment_id and cycle fields remain untouched. Shadow payments cannot unlock a legacy cycle.
            MembershipPayment::query()->updateOrCreate(['payment_account_id' => $context->integrationId, 'external_transaction_id' => $resource->id],
                ['company_id' => $context->companyId, 'customer_membership_id' => $locked->id, 'membership_invoice_id' => $invoice->id,
                    'client_id' => $locked->client_id, 'provider' => $context->provider, 'origin' => 'reconciliation_v2',
                    'amount' => $amount, 'status' => $status, 'due_date' => $due, 'confirmed_at' => $confirmed,
                    'received_at' => $received, 'paid_at' => $status === MembershipPaymentStatus::Paid ? $confirmed : null,
                    'refunded_amount' => $status === MembershipPaymentStatus::Refunded ? $amount : null]);
            $this->states->recalculate($locked->id, $eventId, $invoice->id);
        }, 3);
    }

    public function reconcile(FinancialAccountContext $context, int $membershipId): void
    {
        $gateway = $this->gateways->forContext($context);
        $membership = CustomerMembership::query()->where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
            ->where('billing_mode', 'recurring_v2')->findOrFail($membershipId);
        $runId = DB::table('subscription_reconciliations')->insertGetId(['company_id' => $context->companyId, 'payment_account_id' => $context->integrationId,
            'customer_membership_id' => $membershipId, 'status' => 'processing', 'started_at' => now()]);
        $count = 0;
        try {
            $subscription = $gateway->getSubscription($context, $membership->external_subscription_id);
            if ($subscription->id !== $membership->external_subscription_id || ($subscription->data['account_id'] ?? null) !== $context->externalAccountId) {
                throw new DomainException('subscription_snapshot_mismatch');
            }
            // Never overwrite a terminal financial state with a late ACTIVE snapshot.
            if (in_array($subscription->status, ['canceled', 'expired'], true)) {
                DB::transaction(function () use ($membershipId, $subscription) {
                    $locked = CustomerMembership::query()->whereKey($membershipId)->lockForUpdate()->firstOrFail();
                    if (! in_array($locked->gateway_subscription_status, ['canceled', 'expired'], true)) {
                        $locked->update(['gateway_subscription_status' => $subscription->status]);
                    }
                    $this->states->recalculate($membershipId);
                });
            }
            $cursor = null;
            $seen = [];
            do {
                $page = $gateway->listChargesForReconciliation($context, $cursor);
                foreach ($page['items'] as $resource) {
                    if (($resource->data['subscription_id'] ?? null) === $membership->external_subscription_id) {
                        $this->applyInvoice($context, $resource, expectedMembershipId: $membershipId);
                        $count++;
                    }
                }
                $cursor = $page['next_cursor'];
                if ($cursor !== null && (isset($seen[$cursor]) || count($seen) >= 1000)) {
                    throw new DomainException('invalid_reconciliation_cursor');
                }
                if ($cursor !== null) {
                    $seen[$cursor] = true;
                }
            } while ($cursor !== null);
            $this->states->recalculate($membershipId);
            DB::table('subscription_reconciliations')->where('id', $runId)->update(['status' => 'completed', 'charges_checked' => $count, 'finished_at' => now()]);
        } catch (\Throwable $error) {
            DB::table('subscription_reconciliations')->where('id', $runId)->update(['status' => 'failed', 'charges_checked' => $count, 'finished_at' => now(), 'error_code' => FinancialErrorCode::fromException($error)]);
            throw $error;
        }
    }
}
