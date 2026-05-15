<?php

namespace App\Services;

use App\Enums\MembershipPaymentBillingType;
use App\Enums\MembershipPaymentStatus;
use App\Enums\PaymentProvider;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Services\Payments\ParsedGatewayWebhook;
use App\Services\Payments\PaymentGatewayManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $payment = MembershipPayment::query()
            ->where('provider', PaymentProvider::Asaas)
            ->where('provider_payment_id', $parsed->providerPaymentId)
            ->first();

        if (! $payment) {
            Log::warning('asaas.webhook.unknown_payment', ['id' => $parsed->providerPaymentId, 'event' => $parsed->event]);

            return;
        }

        $integration = CompanyPaymentIntegration::query()
            ->where('company_id', $payment->company_id)
            ->where('provider', PaymentProvider::Asaas)
            ->where('active', true)
            ->orderByDesc('default_for_memberships')
            ->orderByDesc('id')
            ->first();

        if ($integration && filled($integration->webhook_secret)) {
            $expected = trim((string) $integration->webhook_secret);
            $given = trim((string) ($accessTokenHeader ?? ''));
            if ($expected !== '' && ! hash_equals($expected, $given)) {
                abort(403, 'Assinatura do webhook inválida.');
            }
        }

        Log::info('asaas.webhook.received', [
            'company_id' => $payment->company_id,
            'event' => $parsed->event,
            'payment_id' => $parsed->providerPaymentId,
            'payload' => $parsed->payload,
        ]);

        if ($this->shouldMarkPaid($parsed)) {
            $this->markPaymentPaid($payment, $parsed->payload);

            return;
        }

        if ($this->shouldMarkOverdue($parsed)) {
            $this->markPaymentOverdue($payment);

            return;
        }

        if ($this->shouldMarkCanceled($parsed)) {
            $this->markPaymentCanceled($payment);

            return;
        }

        if ($this->shouldMarkRefunded($parsed)) {
            $this->markPaymentRefunded($payment);

            return;
        }
    }

    public function markPaymentPaid(MembershipPayment $payment, array $rawPayload = []): void
    {
        DB::transaction(function () use ($payment, $rawPayload): void {
            /** @var MembershipPayment|null $locked */
            $locked = MembershipPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            if ($locked->status === MembershipPaymentStatus::Paid && $locked->paid_at) {
                return;
            }

            $locked->status = MembershipPaymentStatus::Paid;
            $locked->paid_at = now();
            if ($rawPayload !== []) {
                $locked->raw_payload = array_merge($locked->raw_payload ?? [], ['webhook' => $rawPayload]);
            }
            $locked->save();

            $membership = CustomerMembership::query()->whereKey($locked->customer_membership_id)->lockForUpdate()->first();
            if (! $membership) {
                return;
            }

            if (in_array($membership->status, [
                CustomerMembership::STATUS_PENDING,
                CustomerMembership::STATUS_OVERDUE,
            ], true)) {
                $membership->status = CustomerMembership::STATUS_ACTIVE;
                $membership->save();
            }
        });
    }

    public function markPaymentOverdue(MembershipPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = MembershipPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            if ($locked->status === MembershipPaymentStatus::Paid) {
                return;
            }

            $locked->status = MembershipPaymentStatus::Overdue;
            $locked->save();

            $membership = CustomerMembership::query()->whereKey($locked->customer_membership_id)->lockForUpdate()->first();
            if (! $membership) {
                return;
            }

            $membership->status = CustomerMembership::STATUS_OVERDUE;
            $membership->save();
        });
    }

    public function markPaymentCanceled(MembershipPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = MembershipPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $locked->status = MembershipPaymentStatus::Canceled;
            $locked->save();

            $membership = CustomerMembership::query()->whereKey($locked->customer_membership_id)->lockForUpdate()->first();
            if (! $membership) {
                return;
            }

            if ($membership->status === CustomerMembership::STATUS_PENDING) {
                $membership->status = CustomerMembership::STATUS_CANCELED;
                $membership->canceled_at = now();
                $membership->save();
            } elseif ($membership->status === CustomerMembership::STATUS_ACTIVE) {
                $membership->status = CustomerMembership::STATUS_OVERDUE;
                $membership->save();
            }
        });
    }

    public function markPaymentRefunded(MembershipPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = MembershipPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $locked->status = MembershipPaymentStatus::Refunded;
            $locked->save();

            $membership = CustomerMembership::query()->whereKey($locked->customer_membership_id)->lockForUpdate()->first();
            if (! $membership) {
                return;
            }

            $membership->status = CustomerMembership::STATUS_CANCELED;
            $membership->canceled_at = now();
            $membership->save();
        });
    }

    private function shouldMarkPaid(ParsedGatewayWebhook $parsed): bool
    {
        $event = Str::upper($parsed->event);
        $status = Str::upper((string) ($parsed->providerPaymentStatus ?? ''));

        if (in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED_IN_UNIT'], true)) {
            return true;
        }

        return in_array($status, ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'], true);
    }

    private function shouldMarkOverdue(ParsedGatewayWebhook $parsed): bool
    {
        $event = Str::upper($parsed->event);
        $status = Str::upper((string) ($parsed->providerPaymentStatus ?? ''));

        return str_contains($event, 'OVERDUE') || $status === 'OVERDUE';
    }

    private function shouldMarkCanceled(ParsedGatewayWebhook $parsed): bool
    {
        $event = Str::upper($parsed->event);

        return str_contains($event, 'DELETED') || str_contains($event, 'CANCELED');
    }

    private function shouldMarkRefunded(ParsedGatewayWebhook $parsed): bool
    {
        $event = Str::upper($parsed->event);

        return str_contains($event, 'REFUNDED');
    }
}
