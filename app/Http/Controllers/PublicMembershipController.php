<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\MembershipCheckoutIntention;
use App\Services\MembershipPublicIdentityService;
use App\Services\Payments\Gateways\AsaasRecurringGateway;
use App\Services\PublicMembershipCatalog;
use App\Services\PublicMembershipEnrollment;
use Illuminate\Http\Request;

class PublicMembershipController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $plans = app(PublicMembershipCatalog::class)->plans($company);
        $selected = $plans->firstWhere('id', (int) $request->query('plan'));
        $snapshot = $selected ? app(PublicMembershipCatalog::class)->snapshot($selected) : null;
        $identity = app(MembershipPublicIdentityService::class)->current($request, $company->id);

        return response()->view('public-memberships.index', compact('company', 'plans', 'selected', 'snapshot', 'identity'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'same-origin');
    }

    public function identify(Request $request, Company $company)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'membership_plan_id' => ['required', 'integer']]);
        abort_unless(app(PublicMembershipCatalog::class)->plans($company)->contains('id', (int) $data['membership_plan_id']), 404);
        app(MembershipPublicIdentityService::class)->issue($request, $company->id, $data['email']);

        return redirect()->route('public-memberships.index', [$company, 'plan' => $data['membership_plan_id']])->with('status', 'code-sent');
    }

    public function verify(Request $request, Company $company)
    {
        $data = $request->validate(['code' => ['required', 'digits:6'], 'membership_plan_id' => ['required', 'integer']]);
        app(MembershipPublicIdentityService::class)->verify($request, $company->id, $data['code']);

        return redirect()->route('public-memberships.index', [$company, 'plan' => $data['membership_plan_id']]);
    }

    public function store(Request $request, Company $company)
    {
        $data = $request->validate(['membership_plan_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[+() 0-9-]{8,30}$/'], 'accepted_terms' => ['accepted'], 'terms_version' => ['required', 'size:64']]);
        $identity = app(MembershipPublicIdentityService::class)->current($request, $company->id);
        abort_unless($identity, 403, 'Confirme seu e-mail antes de contratar.');
        try {
            $intention = app(PublicMembershipEnrollment::class)->contract($company, $identity,
                $data + ['ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000)]);
        } catch (\DomainException) {
            return back()->withErrors(['checkout' => 'Contratação indisponível. Entre em contato com o estabelecimento.']);
        }
        // Redirect only to the fixed Sandbox host, using the persisted ID, never a gateway-provided URL.
        if ($intention->status === 'ready' && $intention->expires_at?->isFuture()) {
            return redirect()->away(app(AsaasRecurringGateway::class)->checkoutUrl($intention->external_checkout_id));
        }

        return redirect()->route('public-memberships.result', [$company, $intention->reference]);
    }

    public function result(Request $request, Company $company, string $reference)
    {
        $identity = app(MembershipPublicIdentityService::class)->current($request, $company->id);
        abort_unless($identity, 403);
        $intention = MembershipCheckoutIntention::where('company_id', $company->id)->where('public_identity_id', $identity->id)->where('reference', $reference)->firstOrFail();

        // Navigation is not financial evidence. This GET never changes any contract or invoice.
        return response()->view('public-memberships.result', compact('company', 'intention'))->header('Cache-Control', 'no-store, private');
    }
}
