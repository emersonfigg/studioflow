<?php

namespace App\Services;

use App\Models\CustomerMembership;
use App\Models\MembershipInvoice;
use App\Models\SubscriptionStatusHistory;
use Illuminate\Support\Facades\DB;

class SubscriptionFinancialStateService
{
    /** Shadow state only. Never modifies status, cycle dates, renewals or eligibility. */
    public function recalculate(int $membershipId, ?int $eventId = null, ?int $invoiceId = null): array
    {
        return DB::transaction(function () use ($membershipId, $eventId, $invoiceId) {
            $membership = CustomerMembership::query()->whereKey($membershipId)->lockForUpdate()->firstOrFail();
            if ($membership->billing_mode !== 'recurring_v2' || ! $membership->payment_account_id) {
                return ['status' => 'unclassified', 'paid_through' => null];
            }
            $invoices = MembershipInvoice::query()->where('company_id', $membership->company_id)
                ->where('payment_account_id', $membership->payment_account_id)->where('customer_membership_id', $membership->id)->get();
            $paid = $invoices->whereIn('status', ['confirmed', 'received'])->sortBy('coverage_starts_at');
            $cursor = $membership->starts_at->copy()->startOfDay();
            $through = null;
            foreach ($paid as $invoice) {
                if (! $invoice->confirmed_at || ! $invoice->coverage_starts_at || ! $invoice->coverage_ends_at) {
                    continue;
                }
                if ($invoice->coverage_starts_at->gt($cursor)) {
                    break; // Never bridge an unpaid interval.
                }
                if ($invoice->coverage_ends_at->gt($cursor)) {
                    $cursor = $invoice->coverage_ends_at->copy();
                    $through = $cursor->copy();
                }
            }
            $graceDays = min(30, max(0, (int) data_get($membership->commercial_snapshot, 'grace_days', 0)));
            $grace = $through?->copy()->addDays($graceDays);
            $overdue = $invoices->contains(fn ($invoice) => $invoice->due_date->lt(today()) && in_array($invoice->status, ['pending', 'overdue', 'failed'], true));
            $terminal = in_array($membership->status, ['canceled', 'expired'], true) ? $membership->status : null;
            $terminal ??= in_array($membership->financial_status, ['canceled', 'expired'], true) ? $membership->financial_status : null;
            $terminal ??= in_array($membership->gateway_subscription_status, ['canceled', 'expired'], true) ? $membership->gateway_subscription_status : null;
            if (! $terminal && ($membership->canceled_at || ($membership->ends_at && $membership->ends_at->endOfDay()->lt(now())))) {
                $terminal = $membership->canceled_at ? 'canceled' : 'expired';
            }
            $reason = null;
            if ($terminal) {
                $status = $terminal;
            } elseif ($membership->status === 'paused') {
                $status = 'suspended';
                $reason = 'contract_paused';
            } elseif ($membership->starts_at->gt(now())) {
                $status = 'pending';
            } elseif ($through && $through->gt(now())) {
                $status = 'active';
            } elseif ($grace && $grace->gte(now())) {
                $status = 'overdue';
            } elseif ($invoices->contains(fn ($invoice) => $invoice->status === 'refunded')) {
                $status = 'suspended';
                $reason = 'financial_payment_reversed';
            } elseif ($overdue || ($through && $through->lte(now()))) {
                $status = 'suspended';
                $reason = 'financial_coverage_ended';
            } else {
                $status = 'pending';
            }
            $previous = $membership->financial_status;
            $membership->update(['financial_status' => $status, 'paid_through' => $through, 'grace_until' => $grace,
                'suspension_reason' => $reason, 'suspended_at' => $status === 'suspended' ? ($membership->suspended_at ?? now()) : null]);
            if ($previous !== $status) {
                SubscriptionStatusHistory::create(['company_id' => $membership->company_id, 'customer_membership_id' => $membership->id,
                    'payment_webhook_event_id' => $eventId, 'membership_invoice_id' => $invoiceId,
                    'previous_status' => $previous ? 'financial.'.$previous : null, 'new_status' => 'financial.'.$status,
                    'reason' => $reason ?? 'financial_recalculation', 'occurred_at' => now()]);
            }

            return ['status' => $status, 'paid_through' => $through?->toIso8601String(), 'grace_until' => $grace?->toIso8601String(), 'reason' => $reason];
        });
    }
}
