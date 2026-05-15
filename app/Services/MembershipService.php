<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CustomerMembership;
use App\Models\MembershipPlan;
use App\Models\MembershipUsage;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MembershipService
{
    public function __construct(
        private readonly MembershipBillingService $membershipBilling,
    ) {}

    public function getActiveMembershipForClient(int $companyId, int $clientId, CarbonInterface $at): ?CustomerMembership
    {
        $date = Carbon::parse($at)->toDateString();

        /** @var CustomerMembership|null $m */
        $m = CustomerMembership::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->where('status', CustomerMembership::STATUS_ACTIVE)
            ->whereDate('starts_at', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $date);
            })
            ->with(['plan.services'])
            ->orderByDesc('starts_at')
            ->first();

        if (! $m) {
            return null;
        }

        $this->rollCyclesIfNeeded($m, $at);
        $m->refresh();

        if ($m->status !== CustomerMembership::STATUS_ACTIVE) {
            return null;
        }

        if (! $this->membershipBilling->membershipCanUseBenefits($m)) {
            return null;
        }

        return $m->load(['plan.services']);
    }

    public function rollCyclesIfNeeded(CustomerMembership $membership, CarbonInterface $at): void
    {
        $m = CustomerMembership::query()->whereKey($membership->id)->first();

        if (! $m || $m->status !== CustomerMembership::STATUS_ACTIVE) {
            return;
        }

        $plan = $m->plan()->first();

        if (! $plan) {
            return;
        }

        $cursor = Carbon::parse($at)->startOfDay();
        $guard = 0;

        while ($cursor->toDateString() > $m->current_cycle_ends_at->toDateString() && $guard++ < 128) {
            if ($this->membershipBilling->requiresPaymentProof($m)) {
                if (! $this->membershipBilling->hasPaidForCycle($m, $m->current_cycle_starts_at)) {
                    $m->update(['status' => CustomerMembership::STATUS_OVERDUE]);

                    return;
                }
            }

            if ($m->ends_at !== null && $cursor->toDateString() > $m->ends_at->toDateString()) {
                $m->update(['status' => CustomerMembership::STATUS_EXPIRED]);

                return;
            }

            if (! $m->auto_renew) {
                $m->update(['status' => CustomerMembership::STATUS_EXPIRED]);

                return;
            }

            $cycleDays = $plan->resolvedCycleDays();
            $nextStart = $m->current_cycle_ends_at->copy()->addDay();
            $nextEnd = $nextStart->copy()->addDays(max(0, $cycleDays - 1));

            $m->update([
                'current_cycle_starts_at' => $nextStart->toDateString(),
                'current_cycle_ends_at' => $nextEnd->toDateString(),
            ]);

            $m->refresh();
        }
    }

    /**
     * @return array{
     *   membership: ?CustomerMembership,
     *   service_discount: float,
     *   product_discount: float,
     *   lines: list<array{item_id: int, service_id: int, discount_amount: float, consumes: bool}>
     * }
     */
    public function computeClosureBenefit(
        ServiceOrder $order,
        ?Appointment $appointment,
        CarbonInterface $closedAt,
    ): array {
        if (! $order->client_id) {
            return $this->emptyBenefit();
        }

        $membership = $this->getActiveMembershipForClient((int) $order->company_id, (int) $order->client_id, $closedAt);

        if (! $membership || ! $membership->isUsable()) {
            return $this->emptyBenefit();
        }

        $plan = $membership->plan;
        $plan->loadMissing('services');

        $lines = [];
        $serviceDiscount = 0.0;
        $cycleUses = $this->countUsagesInCurrentCycle($membership);
        $plannedInThisOrder = 0;

        foreach ($order->items->where('type', ServiceOrderItem::TYPE_SERVICE) as $item) {
            /** @var ServiceOrderItem $item */
            $serviceId = $item->service_id;

            if (! $serviceId) {
                continue;
            }

            $pivotService = $plan->services->firstWhere('id', $serviceId);

            if (! $pivotService) {
                continue;
            }

            $pivot = $pivotService->pivot;
            $qtyLimit = $pivot->quantity_per_cycle;

            $usedForService = (int) MembershipUsage::query()
                ->where('company_id', $order->company_id)
                ->where('customer_membership_id', $membership->id)
                ->where('service_id', $serviceId)
                ->whereBetween('used_at', [
                    $membership->current_cycle_starts_at->startOfDay(),
                    $membership->current_cycle_ends_at->endOfDay(),
                ])
                ->sum('quantity');

            if ($qtyLimit !== null && $usedForService >= (int) $qtyLimit) {
                continue;
            }

            $planMaxUses = $plan->max_services_per_cycle;

            if ($planMaxUses !== null && $cycleUses + $plannedInThisOrder >= (int) $planMaxUses) {
                continue;
            }

            $lineTotal = round((float) $item->total_price, 2);
            $discountAmount = 0.0;

            if ($pivot->included) {
                $discountAmount = $lineTotal;
            } else {
                $pct = (float) ($pivot->discount_percent ?? 0);

                if ($plan->max_service_discount_percent !== null) {
                    $pct = min($pct, (float) $plan->max_service_discount_percent);
                }

                $discountAmount = round($lineTotal * ($pct / 100), 2);
            }

            if ($discountAmount <= 0) {
                continue;
            }

            $serviceDiscount += $discountAmount;
            $plannedInThisOrder++;

            $lines[] = [
                'item_id' => (int) $item->id,
                'service_id' => (int) $serviceId,
                'discount_amount' => $discountAmount,
                'consumes' => true,
            ];
        }

        $productDiscount = 0.0;
        $productSubtotal = round((float) $order->items->where('type', ServiceOrderItem::TYPE_PRODUCT)->sum('total_price'), 2);

        if ($productSubtotal > 0 && $plan->max_product_discount_percent !== null) {
            $pct = min(100.0, (float) $plan->max_product_discount_percent);
            $productDiscount = round($productSubtotal * ($pct / 100), 2);
        }

        return [
            'membership' => $membership,
            'service_discount' => round($serviceDiscount, 2),
            'product_discount' => round($productDiscount, 2),
            'lines' => $lines,
        ];
    }

    public function countUsagesInCurrentCycle(CustomerMembership $membership): int
    {
        return (int) MembershipUsage::query()
            ->where('company_id', $membership->company_id)
            ->where('customer_membership_id', $membership->id)
            ->whereBetween('used_at', [
                $membership->current_cycle_starts_at->startOfDay(),
                $membership->current_cycle_ends_at->endOfDay(),
            ])
            ->sum('quantity');
    }

    /**
     * @param  array<string, mixed>  $benefit
     */
    public function recordUsagesFromClosure(
        ServiceOrder $order,
        ?Appointment $appointment,
        array $benefit,
        CarbonInterface $closedAt,
    ): void {
        $membership = $benefit['membership'] ?? null;

        if (! $membership instanceof CustomerMembership || empty($benefit['lines'])) {
            return;
        }

        $refType = $appointment ? MembershipUsage::REF_APPOINTMENT : MembershipUsage::REF_SERVICE_ORDER;
        $refId = $appointment ? (int) $appointment->id : (int) $order->id;

        foreach ($benefit['lines'] as $line) {
            if (empty($line['consumes'])) {
                continue;
            }

            $serviceId = (int) $line['service_id'];

            MembershipUsage::query()->firstOrCreate(
                [
                    'company_id' => $order->company_id,
                    'customer_membership_id' => $membership->id,
                    'reference_type' => $refType,
                    'reference_id' => $refId,
                    'service_id' => $serviceId,
                ],
                [
                    'client_id' => (int) $order->client_id,
                    'appointment_id' => $appointment?->id,
                    'service_order_id' => $order->id,
                    'used_at' => $closedAt,
                    'quantity' => 1,
                    'description' => 'Consumo na conclusão da comanda',
                ],
            );
        }
    }

    /**
     * @return array{
     *   active: false
     * }|array{
     *   active: true,
     *   membership: CustomerMembership,
     *   plan_name: string,
     *   billing_cycle_label: string,
     *   cycle_label: string,
     *   service_rules: Collection<int, array<string, mixed>>
     * }
     */
    public function membershipSummaryForClient(int $companyId, int $clientId): array
    {
        $membership = $this->getActiveMembershipForClient($companyId, $clientId, now());

        if (! $membership) {
            return ['active' => false];
        }

        $plan = $membership->plan;
        $plan->loadMissing('services');

        return [
            'active' => true,
            'membership' => $membership,
            'plan_name' => $plan->name,
            'billing_cycle_label' => MembershipPlan::billingCycleLabel($plan->billing_cycle),
            'cycle_label' => $membership->current_cycle_starts_at?->format('d/m/Y')
                .' — '
                .$membership->current_cycle_ends_at?->format('d/m/Y'),
            'service_rules' => $plan->services->map(fn ($s): array => [
                'service_id' => $s->id,
                'name' => $s->name,
                'included' => (bool) $s->pivot->included,
                'discount_percent' => $s->pivot->discount_percent,
                'quantity_per_cycle' => $s->pivot->quantity_per_cycle,
                'remaining' => $this->remainingUsesForService(
                    $membership,
                    (int) $s->id,
                    $s->pivot->quantity_per_cycle !== null ? (int) $s->pivot->quantity_per_cycle : null,
                ),
            ]),
        ];
    }

    private function remainingUsesForService(CustomerMembership $membership, int $serviceId, ?int $limit): ?int
    {
        if ($limit === null) {
            return null;
        }

        $used = (int) MembershipUsage::query()
            ->where('customer_membership_id', $membership->id)
            ->where('service_id', $serviceId)
            ->whereBetween('used_at', [
                $membership->current_cycle_starts_at->startOfDay(),
                $membership->current_cycle_ends_at->endOfDay(),
            ])
            ->sum('quantity');

        return max(0, $limit - $used);
    }

    /**
     * @return array{membership: ?CustomerMembership, service_discount: float, product_discount: float, lines: array<int, array<string, mixed>>}
     */
    private function emptyBenefit(): array
    {
        return [
            'membership' => null,
            'service_discount' => 0.0,
            'product_discount' => 0.0,
            'lines' => [],
        ];
    }
}
