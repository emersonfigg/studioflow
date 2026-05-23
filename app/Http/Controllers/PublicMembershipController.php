<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\CustomerMembership;
use App\Models\MembershipPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicMembershipController extends Controller
{
    public function index(Company $company): View
    {
        $plans = MembershipPlan::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->with('services')
            ->orderBy('price')
            ->get();

        return view('public-memberships.index', compact('company', 'plans'));
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'membership_plan_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'accepted_terms' => ['accepted'],
        ], [
            'accepted_terms.accepted' => 'Voce precisa aceitar os termos para contratar a assinatura.',
        ]);

        $plan = MembershipPlan::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->findOrFail((int) $data['membership_plan_id']);

        $client = Client::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'phone' => preg_replace('/\D+/', '', (string) $data['phone']) ?: (string) $data['phone'],
            ],
            [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'active' => true,
            ],
        );

        $start = now()->startOfDay();
        $end = $start->copy()->addDays(max(0, $plan->resolvedCycleDays() - 1));

        CustomerMembership::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => CustomerMembership::STATUS_PENDING,
            'starts_at' => $start->toDateString(),
            'ends_at' => $end->toDateString(),
            'renews_at' => $plan->auto_renew ? $end->copy()->addDay()->toDateString() : null,
            'current_cycle_starts_at' => $start->toDateString(),
            'current_cycle_ends_at' => $end->toDateString(),
            'auto_renew' => (bool) $plan->auto_renew,
            'accepted_terms_at' => now(),
            'accepted_terms_ip' => $request->ip(),
            'accepted_terms_user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return back()->with('status', 'membership-requested');
    }
}
