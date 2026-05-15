<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\Client;
use App\Models\CustomerMembership;
use App\Models\MembershipPlan;
use App\Services\MembershipBillingService;
use App\Services\Payments\PaymentGatewayManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class CustomerMembershipController extends Controller
{
    public function store(Request $request, Client $client, MembershipBillingService $billing): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($client->company_id === $request->user()->company_id, 404);

        $companyId = (int) $request->user()->company_id;

        try {
            app(PaymentGatewayManager::class)->resolveActiveIntegrationForMemberships($companyId);
        } catch (PaymentGatewayNotConfiguredException $e) {
            return redirect()
                ->route('clients.show', $client)
                ->withInput()
                ->withErrors(['gateway' => $e->getMessage()]);
        }

        $data = $request->validate([
            'membership_plan_id' => [
                'required',
                Rule::exists('membership_plans', 'id')->where('company_id', $companyId)->where('active', true),
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'auto_renew' => ['sometimes', 'boolean'],
            'accepted_terms' => ['sometimes', 'boolean'],
        ]);

        $plan = MembershipPlan::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $data['membership_plan_id'])
            ->firstOrFail();

        $starts = isset($data['starts_at'])
            ? CarbonImmutable::parse((string) $data['starts_at'])->startOfDay()
            : CarbonImmutable::now()->startOfDay();
        $cycleDays = $plan->resolvedCycleDays();
        $cycleEnd = $starts->addDays(max(0, $cycleDays - 1));

        try {
            $paymentId = DB::transaction(function () use ($billing, $companyId, $client, $plan, $data, $starts, $cycleEnd): int {
                $membership = CustomerMembership::query()->create([
                    'company_id' => $companyId,
                    'client_id' => $client->id,
                    'membership_plan_id' => $plan->id,
                    'status' => CustomerMembership::STATUS_PENDING,
                    'starts_at' => $starts->toDateString(),
                    'ends_at' => isset($data['ends_at']) ? CarbonImmutable::parse((string) $data['ends_at'])->toDateString() : null,
                    'current_cycle_starts_at' => $starts->toDateString(),
                    'current_cycle_ends_at' => $cycleEnd->toDateString(),
                    'auto_renew' => (bool) ($data['auto_renew'] ?? $plan->auto_renew),
                    'accepted_terms_at' => ! empty($data['accepted_terms']) ? now() : null,
                ]);

                $created = $billing->createInitialMembershipCharge($membership, $plan);

                return (int) $created['payment']->id;
            });
        } catch (Throwable $e) {
            return redirect()
                ->route('clients.show', $client)
                ->withInput()
                ->withErrors(['gateway' => $e->getMessage() ?: 'Não foi possível gerar a cobrança.']);
        }

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'membership-created')
            ->with('membership_payment_id', $paymentId);
    }

    public function pause(Request $request, CustomerMembership $customerMembership): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureMembershipCompany($request, $customerMembership);
        $customerMembership->update(['status' => CustomerMembership::STATUS_PAUSED]);

        return back()->with('status', 'membership-paused');
    }

    public function resume(Request $request, CustomerMembership $customerMembership, MembershipBillingService $billing): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureMembershipCompany($request, $customerMembership);

        if ($billing->requiresPaymentProof($customerMembership)
            && ! $billing->hasPaidForCycle($customerMembership, $customerMembership->current_cycle_starts_at)) {
            return back()->withErrors([
                'membership' => 'Não é possível retomar: pagamento do ciclo não confirmado.',
            ]);
        }

        $customerMembership->update(['status' => CustomerMembership::STATUS_ACTIVE]);

        return back()->with('status', 'membership-resumed');
    }

    public function cancel(Request $request, CustomerMembership $customerMembership): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureMembershipCompany($request, $customerMembership);
        $customerMembership->update([
            'status' => CustomerMembership::STATUS_CANCELED,
            'canceled_at' => now(),
        ]);

        return back()->with('status', 'membership-canceled');
    }

    private function ensureMembershipCompany(Request $request, CustomerMembership $membership): void
    {
        abort_unless($membership->company_id === $request->user()->company_id, 404);
    }
}
