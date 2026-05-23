<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipPlanController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $plans = MembershipPlan::query()
            ->where('company_id', $request->user()->company_id)
            ->withCount('customerMemberships')
            ->orderBy('name')
            ->get();

        return view('membership-plans.index', ['plans' => $plans]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('membership-plans.create', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $companyId = (int) $request->user()->company_id;
        $data = $this->validatedPlan($request, $companyId);
        $services = array_values($data['services'] ?? []);
        unset($data['services']);

        $plan = MembershipPlan::query()->create([
            ...$data,
            'company_id' => $companyId,
        ]);

        $this->syncPlanServices($plan, $companyId, $services);

        return redirect()->route('membership-plans.edit', $plan)->with('status', 'membership-plan-created');
    }

    public function edit(Request $request, MembershipPlan $membershipPlan): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensurePlanCompany($request, $membershipPlan);

        $membershipPlan->load('services');

        return view('membership-plans.edit', [
            'plan' => $membershipPlan,
            ...$this->formData($request),
        ]);
    }

    public function update(Request $request, MembershipPlan $membershipPlan): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensurePlanCompany($request, $membershipPlan);

        $companyId = (int) $request->user()->company_id;
        $data = $this->validatedPlan($request, $companyId);
        $services = array_values($data['services'] ?? []);
        unset($data['services']);

        $membershipPlan->update($data);
        $this->syncPlanServices($membershipPlan, $companyId, $services);

        return redirect()->route('membership-plans.edit', $membershipPlan)->with('status', 'membership-plan-updated');
    }

    public function show(Request $request, MembershipPlan $membershipPlan): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensurePlanCompany($request, $membershipPlan);

        $memberships = $membershipPlan->customerMemberships()
            ->with('client')
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('membership-plans.show', [
            'plan' => $membershipPlan->load('services'),
            'memberships' => $memberships,
        ]);
    }

    public function toggleActive(Request $request, MembershipPlan $membershipPlan): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensurePlanCompany($request, $membershipPlan);

        $membershipPlan->update(['active' => ! $membershipPlan->active]);

        return back()->with('status', 'membership-plan-toggled');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $companyId = (int) $request->user()->company_id;

        return [
            'services' => Service::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'billingCycles' => MembershipPlan::BILLING_CYCLES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPlan(Request $request, int $companyId): array
    {
        $request->mergeIfMissing(['active' => true, 'auto_renew' => false]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', Rule::in(MembershipPlan::BILLING_CYCLES)],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'active' => ['sometimes', 'boolean'],
            'auto_renew' => ['sometimes', 'boolean'],
            'max_services_per_cycle' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'max_product_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_service_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms_text' => ['nullable', 'string'],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('company_id', $companyId)],
            'services.*.included' => ['sometimes', 'boolean'],
            'services.*.quantity_per_cycle' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'services.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'services.*.special_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['auto_renew'] = $request->boolean('auto_renew');

        return $data;
    }

    /**
     * @param  list<array{service_id: int, included?: bool, quantity_per_cycle?: int|null, discount_percent?: float|null, special_duration_minutes?: int|null}>  $rows
     */
    private function syncPlanServices(MembershipPlan $plan, int $companyId, array $rows): void
    {
        $sync = [];

        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['service_id'])) {
                continue;
            }

            $serviceId = (int) $row['service_id'];
            $included = (bool) ($row['included'] ?? false);
            $disc = isset($row['discount_percent']) && $row['discount_percent'] !== '' && $row['discount_percent'] !== null
                ? (float) $row['discount_percent']
                : null;
            $qty = isset($row['quantity_per_cycle']) && $row['quantity_per_cycle'] !== '' && $row['quantity_per_cycle'] !== null
                ? (int) $row['quantity_per_cycle']
                : null;
            $specialDuration = isset($row['special_duration_minutes']) && $row['special_duration_minutes'] !== '' && $row['special_duration_minutes'] !== null
                ? (int) $row['special_duration_minutes']
                : null;

            if (! $included && ($disc === null || $disc <= 0) && $qty === null && $specialDuration === null) {
                continue;
            }

            $sync[$serviceId] = [
                'company_id' => $companyId,
                'quantity_per_cycle' => $qty,
                'discount_percent' => $disc,
                'included' => $included,
                'special_duration_minutes' => $specialDuration,
            ];
        }

        $plan->services()->sync($sync);
    }

    private function ensurePlanCompany(Request $request, MembershipPlan $plan): void
    {
        abort_unless($plan->company_id === $request->user()->company_id, 404);
    }
}
