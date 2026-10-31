<?php

namespace App\Services;

use App\Models\CustomerMembership;
use App\Models\MembershipCheckoutIntention;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\Gateways\AsaasRecurringGateway;
use App\Services\Payments\V2\FinancialAccountContext;
use DomainException;
use Illuminate\Support\Facades\DB;

class MembershipCheckoutEventService
{
    public function process(FinancialAccountContext $context, PaymentWebhookEvent $event, string $token): void
    {
        $gateway = app(FinancialGatewayRegistry::class)->forContext($context);
        if (! $gateway instanceof AsaasRecurringGateway) {
            throw new DomainException('unsupported_event');
        }
        if ($event->resource_type === 'subscription') {
            $subscription = $gateway->getSubscription($context, $event->external_resource_id);
            $membership = $gateway->correlateSubscription($context, $subscription);
            DB::transaction(function () use ($event, $token, $membership, $subscription) {
                $this->assertLease($event, $token);
                $locked = CustomerMembership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
                if ($subscription->status === 'inactive' && ! in_array($locked->gateway_subscription_status, ['canceled', 'expired'], true)) {
                    $locked->update(['gateway_subscription_status' => 'inactive', 'cancel_requested_at' => $locked->cancel_requested_at ?? now()]);
                }
                if (in_array($subscription->status, ['canceled', 'expired'], true) && ! in_array($locked->gateway_subscription_status, ['canceled', 'expired'], true)) {
                    $locked->update(['gateway_subscription_status' => $subscription->status]);
                    app(SubscriptionFinancialStateService::class)->recalculate($locked->id, $event->id);
                }
            }, 3);

            return;
        }
        DB::transaction(function () use ($context, $event, $token) {
            $this->assertLease($event, $token);
            $checkout = $event->safe_payload['checkout'] ?? [];
            $intention = MembershipCheckoutIntention::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
                ->where('external_checkout_id', $event->external_resource_id)->lockForUpdate()->first();
            // Recovery after a timeout is permitted only with the opaque reference AND the dedicated gateway customer.
            if (! $intention && filled($checkout['externalReference'] ?? null) && filled($checkout['customer'] ?? null)) {
                $intention = MembershipCheckoutIntention::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
                    ->where('reference', $checkout['externalReference'])->where('external_customer_id', $checkout['customer'])
                    ->whereNotNull('creation_started_at')->whereNull('external_checkout_id')->lockForUpdate()->first();
                if ($intention) {
                    $intention->update(['external_checkout_id' => $event->external_resource_id]);
                    CustomerMembership::whereKey($intention->customer_membership_id)->update(['external_checkout_id' => $event->external_resource_id]);
                }
            }
            if (! $intention || ($checkout['customer'] ?? null) !== $intention->external_customer_id
                || (isset($checkout['externalReference']) && $checkout['externalReference'] !== $intention->reference)) {
                throw new DomainException('uncorrelated_subscription');
            }
            $next = match ($event->event_type) {
                'CHECKOUT_PAID' => 'awaiting_payment', 'CHECKOUT_EXPIRED' => 'expired', 'CHECKOUT_CANCELED' => 'canceled', default => 'ready',
            };
            if ($intention->status !== 'awaiting_payment' && ($next === 'awaiting_payment' || ! in_array($intention->status, ['canceled', 'expired'], true))) {
                // Checkout events describe the journey only. They never create paid invoices or activate benefits.
                $intention->update(['status' => $next]);
            }
        }, 3);
    }

    private function assertLease(PaymentWebhookEvent $event, string $token): void
    {
        $locked = PaymentWebhookEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
        if ($locked->processing_token !== $token || $locked->processing_status !== 'processing' || $locked->lease_until?->isPast()) {
            throw new DomainException('stale_processing_lease');
        }
    }
}
