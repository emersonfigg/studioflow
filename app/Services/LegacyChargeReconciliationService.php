<?php

namespace App\Services;

use App\Enums\MembershipPaymentStatus;
use App\Models\CompanyPaymentIntegration;
use App\Models\MembershipPayment;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\FinancialAmount;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\V2\FinancialAccountContext;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LegacyChargeReconciliationService
{
    /** Existing one-off capability, never a recurring checkout or an automatic contract activation. */
    public function reconcile(FinancialAccountContext $context, MembershipPayment $payment, int $eventId, string $token): void
    {
        $account = CompanyPaymentIntegration::query()->where('company_id', $context->companyId)->findOrFail($context->integrationId);
        $details = app(PaymentGatewayManager::class)->gatewayFor($account)->getPaymentStatus($payment);
        if (($details['id'] ?? null) !== $payment->provider_payment_id || ! empty($details['subscription'])
            || empty($payment->raw_payload['customer']) || ($details['customer'] ?? null) !== $payment->raw_payload['customer']
            || FinancialAmount::cents($details['value'] ?? null) === null
            || FinancialAmount::cents($details['value']) !== FinancialAmount::cents($payment->amount)) {
            throw new DomainException('legacy_charge_mismatch');
        }
        $status = match ($details['status'] ?? null) {
            'CONFIRMED', 'RECEIVED' => MembershipPaymentStatus::Paid,
            'PENDING' => MembershipPaymentStatus::Pending, 'OVERDUE' => MembershipPaymentStatus::Overdue,
            'REFUNDED' => MembershipPaymentStatus::Refunded, 'DELETED' => MembershipPaymentStatus::Canceled,
            default => throw new DomainException('unsupported_legacy_status'),
        };
        $confirmed = $details['confirmedDate'] ?? $details['paymentDate'] ?? null;
        if ($status === MembershipPaymentStatus::Paid && ! $confirmed) {
            throw new DomainException('missing_confirmation_date');
        }
        DB::transaction(function () use ($context, $payment, $eventId, $token, $status, $confirmed, $details) {
            $event = PaymentWebhookEvent::whereKey($eventId)->lockForUpdate()->firstOrFail();
            if ($event->processing_token !== $token || $event->processing_status !== 'processing' || $event->lease_until->lt(now())) {
                throw new DomainException('stale_processing_lease');
            }
            $locked = MembershipPayment::where('company_id', $context->companyId)->where('payment_account_id', $context->integrationId)
                ->where('origin', 'legacy_charge')->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === MembershipPaymentStatus::Refunded
                || ($locked->status === MembershipPaymentStatus::Paid && $status !== MembershipPaymentStatus::Refunded)) {
                return;
            }
            $locked->update(['status' => $status, 'paid_at' => $status === MembershipPaymentStatus::Paid ? Carbon::parse($confirmed) : $locked->paid_at,
                'confirmed_at' => $confirmed ? Carbon::parse($confirmed) : null,
                'received_at' => ($details['status'] ?? null) === 'RECEIVED' && isset($details['paymentDate']) ? Carbon::parse($details['paymentDate']) : $locked->received_at]);
        }, 3);
    }
}
