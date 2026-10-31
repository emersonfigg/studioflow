<?php

namespace App\Services;

use App\Enums\LegacyMembershipClassification as Classification;
use App\Enums\MembershipPaymentStatus;
use App\Models\CashMovement;
use App\Models\CustomerMembership;
use App\Models\MembershipPayment;
use App\Models\ServiceOrder;

/** Inventory only: this classification is never an eligibility decision. */
final class LegacyMembershipClassifier
{
    /** @return array{classification: Classification, evidence: array<string, mixed>} */
    public function classify(int $companyId, int $membershipId): array
    {
        $membership = CustomerMembership::query()->where('company_id', $companyId)->findOrFail($membershipId);
        $payments = MembershipPayment::query()->where('customer_membership_id', $membershipId)->get();
        $inconsistent = $payments->contains(fn ($payment) => (int) $payment->company_id !== $companyId || (int) $payment->client_id !== (int) $membership->client_id);
        $inconsistent = $inconsistent || ! $membership->client || (int) $membership->client->company_id !== $companyId
            || ! $membership->plan || (int) $membership->plan->company_id !== $companyId;
        $order = $membership->service_order_id ? ServiceOrder::query()->find($membership->service_order_id) : null;
        $inconsistent = $inconsistent || ($membership->service_order_id && (! $order || (int) $order->company_id !== $companyId || (int) $order->client_id !== (int) $membership->client_id));
        $paid = $payments->filter(fn ($payment) => $payment->status === MembershipPaymentStatus::Paid && $payment->paid_at !== null && (float) $payment->amount > 0);
        $pos = $order && $order->status === ServiceOrder::STATUS_PAID && CashMovement::query()
            ->where('company_id', $companyId)->where('source_type', CustomerMembership::class)
            ->where('source_id', $membershipId)->where('type', CashMovement::TYPE_INFLOW)->where('amount', '>', 0)->exists();

        return [
            'classification' => $inconsistent ? Classification::Inconsistent : ($paid->isNotEmpty() ? Classification::PaymentEvidence : ($pos ? Classification::PointOfSale : Classification::Undetermined)),
            'evidence' => ['membership_id' => $membershipId, 'company_id' => $companyId, 'payment_ids' => $paid->pluck('id')->all(), 'pos_inflow' => (bool) $pos, 'recurrence_authorized' => false],
        ];
    }
}
