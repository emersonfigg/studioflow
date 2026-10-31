<?php

namespace App\Services;

use App\Models\CompanyPaymentIntegration;
use App\Models\MembershipPayment;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\FinancialErrorCode;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\V2\FinancialAccountResolver;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialEventProcessor
{
    public function __construct(private readonly FinancialGatewayRegistry $gateways, private readonly FinancialAccountResolver $accounts, private readonly SubscriptionReconciliationService $reconciliation) {}

    public function claim(int $eventId): ?string
    {
        return DB::transaction(function () use ($eventId) {
            $event = PaymentWebhookEvent::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            if (in_array($event->processing_status, ['processed', 'quarantined'], true)
                || $event->attempts >= config('financial.max_attempts')
                || ($event->processing_status === 'processing' && $event->lease_until?->gt(now()))) {
                return null;
            }
            $token = (string) Str::uuid();
            $event->update(['processing_status' => 'processing', 'processing_token' => $token,
                'lease_until' => now()->addSeconds(config('financial.lease_seconds')), 'attempts' => $event->attempts + 1, 'error_code' => null]);

            return $token;
        }, 3);
    }

    public function process(int $eventId): void
    {
        $token = $this->claim($eventId);
        if ($token === null) {
            return;
        }
        $event = PaymentWebhookEvent::findOrFail($eventId);
        try {
            $account = CompanyPaymentIntegration::query()->where('company_id', $event->company_id)->findOrFail($event->payment_account_id);
            $context = $this->accounts->resolve($event->company_id, $account->id, $account->provider, $account->environment);
            if (in_array($event->resource_type, ['checkout', 'subscription'], true)) {
                app(MembershipCheckoutEventService::class)->process($context, $event, $token);
                PaymentWebhookEvent::whereKey($eventId)->where('processing_token', $token)
                    ->update(['processing_status' => 'processed', 'processed_at' => now(), 'lease_until' => null]);

                return;
            }
            if ($event->resource_type !== 'payment') {
                throw new DomainException('unsupported_event');
            }
            $legacy = MembershipPayment::query()->where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
                ->where('provider', $context->provider)->where('provider_payment_id', $event->external_resource_id)->where('origin', 'legacy_charge')->first();
            if ($legacy) {
                app(LegacyChargeReconciliationService::class)->reconcile($context, $legacy, $event->id, $token);
            } else {
                $gateway = $this->gateways->forContext($context);
                // Remote request occurs after claim committed and outside all SQL transactions.
                $resource = $gateway->getInvoice($context, $event->external_resource_id);
                if ($resource->id !== $event->external_resource_id) {
                    throw new DomainException('resource_mismatch');
                }
                $this->reconciliation->applyInvoice($context, $resource, $event->id, $token);
            }
            PaymentWebhookEvent::query()->whereKey($eventId)->where('processing_token', $token)
                ->update(['processing_status' => 'processed', 'processed_at' => now(), 'lease_until' => null]);
        } catch (DomainException $error) {
            if ($error->getMessage() === 'stale_processing_lease') {
                PaymentWebhookEvent::query()->whereKey($eventId)->where('processing_token', $token)
                    ->update(['processing_status' => 'failed', 'error_code' => 'stale_processing_lease', 'lease_until' => null]);
                throw new \RuntimeException('financial_event_retry_required');
            }
            PaymentWebhookEvent::query()->whereKey($eventId)->where('processing_token', $token)
                ->update(['processing_status' => 'quarantined', 'error_code' => FinancialErrorCode::fromException($error), 'lease_until' => null]);
        } catch (\Throwable) {
            PaymentWebhookEvent::query()->whereKey($eventId)->where('processing_token', $token)
                ->update(['processing_status' => 'failed', 'error_code' => 'gateway_unavailable', 'lease_until' => null]);
            // Queue failures must not serialize raw HTTP errors, credentials or payloads.
            throw new \RuntimeException('financial_event_retry_required');
        }
    }
}
