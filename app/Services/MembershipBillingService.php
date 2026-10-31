<?php

namespace App\Services;

use App\Enums\MembershipPaymentBillingType;
use App\Enums\MembershipPaymentStatus;
use App\Models\CustomerMembership;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Services\Payments\ParsedGatewayWebhook;
use App\Services\Payments\PaymentGatewayManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MembershipBillingService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
    ) {}

    public function requiresPaymentProof(CustomerMembership $membership): bool
    {
        return $membership->membershipPayments()->exists();
    }

    public function hasPaidForCycle(CustomerMembership $membership, CarbonInterface|string|null $cycleStart): bool
    {
        if ($cycleStart === null) {
            return false;
        }

        $start = $cycleStart instanceof CarbonInterface
            ? $cycleStart->format('Y-m-d')
            : (string) $cycleStart;

        return MembershipPayment::query()
            ->where('customer_membership_id', $membership->id)
            ->where('status', MembershipPaymentStatus::Paid)
            ->whereDate('cycle_starts_at', $start)
            ->exists();
    }

    public function membershipCanUseBenefits(CustomerMembership $membership): bool
    {
        if ($membership->status !== CustomerMembership::STATUS_ACTIVE) {
            return false;
        }

        if (! $this->requiresPaymentProof($membership)) {
            return true;
        }

        return $this->hasPaidForCycle($membership, $membership->current_cycle_starts_at);
    }

    /**
     * @return array{payment: MembershipPayment, charge: array<string, mixed>}
     */
    public function createInitialMembershipCharge(
        CustomerMembership $membership,
        MembershipPlan $plan,
    ): array {
        $integration = $this->gatewayManager->resolveActiveIntegrationForMemberships((int) $membership->company_id);
        $gateway = $this->gatewayManager->gatewayFor($integration);

        return DB::transaction(function () use ($membership, $plan, $integration, $gateway): array {
            $charge = $gateway->createMembershipCharge($membership, [
                'due_date' => now()->addDays(3)->toDateString(),
                'billing_type' => 'UNDEFINED',
            ]);

            $payment = MembershipPayment::query()->create([
                'payment_account_id' => $integration->account_verified_at && filled($integration->external_account_id) ? $integration->id : null,
                'origin' => 'legacy_charge',
                'company_id' => $membership->company_id,
                'customer_membership_id' => $membership->id,
                'client_id' => $membership->client_id,
                'provider' => $integration->provider,
                'provider_payment_id' => $charge['provider_payment_id'],
                'provider_subscription_id' => $charge['provider_subscription_id'] ?? null,
                'amount' => $charge['amount'] ?? $plan->price,
                'status' => MembershipPaymentStatus::Pending,
                'billing_type' => $charge['billing_type'] ?? MembershipPaymentBillingType::Unknown,
                'due_date' => isset($charge['due_date']) ? Carbon::parse((string) $charge['due_date'])->toDateString() : null,
                'cycle_starts_at' => $membership->current_cycle_starts_at?->format('Y-m-d'),
                'cycle_ends_at' => $membership->current_cycle_ends_at?->format('Y-m-d'),
                'invoice_url' => $charge['invoice_url'] ?? null,
                'pix_qr_code' => $charge['pix_qr_code'] ?? null,
                'pix_copy_paste' => $charge['pix_copy_paste'] ?? null,
                'raw_payload' => $charge['raw'] ?? [],
            ]);

            return ['payment' => $payment, 'charge' => $charge];
        });
    }

    public function handleAsaasWebhook(ParsedGatewayWebhook $parsed, ?string $accessTokenHeader): void
    {
        app(WebhookIngestionService::class)->ingestAsaas($parsed->payload, $accessTokenHeader);
    }

    public function markPaymentPaid(MembershipPayment $payment, array $rawPayload = []): void
    {
        $this->recordLegacyPaymentStatus($payment, MembershipPaymentStatus::Paid);
    }

    public function markPaymentOverdue(MembershipPayment $payment): void
    {
        $this->recordLegacyPaymentStatus($payment, MembershipPaymentStatus::Overdue);
    }

    public function markPaymentCanceled(MembershipPayment $payment): void
    {
        $this->recordLegacyPaymentStatus($payment, MembershipPaymentStatus::Canceled);
    }

    public function markPaymentRefunded(MembershipPayment $payment): void
    {
        $this->recordLegacyPaymentStatus($payment, MembershipPaymentStatus::Refunded);
    }

    // Recording money never grants or revokes a legacy contract's benefits.
    private function recordLegacyPaymentStatus(MembershipPayment $payment, MembershipPaymentStatus $status): void
    {
        DB::transaction(function () use ($payment, $status): void {
            $locked = MembershipPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === MembershipPaymentStatus::Refunded
                || ($locked->status === MembershipPaymentStatus::Paid && $status !== MembershipPaymentStatus::Refunded)) {
                return;
            }
            $locked->status = $status;
            if ($status === MembershipPaymentStatus::Paid) {
                $locked->paid_at ??= now();
            }
            $locked->save();
        });
    }
}
