<?php

namespace App\Services;

use App\Models\Company;
use App\Models\MembershipPlan;
use App\Services\Payments\V2\PublicMembershipAccount;
use Illuminate\Support\Collection;

class PublicMembershipCatalog
{
    public const CYCLES = ['monthly' => 'MONTHLY', 'quarterly' => 'QUARTERLY', 'semiannual' => 'SEMIANNUALLY', 'annual' => 'YEARLY'];

    public const MONTHS = ['MONTHLY' => 1, 'QUARTERLY' => 3, 'SEMIANNUALLY' => 6, 'YEARLY' => 12];

    public function plans(Company $company): Collection
    {
        if (! $company->active) {
            return collect();
        }
        try {
            app(PublicMembershipAccount::class)->resolve($company->id);
        } catch (\DomainException) {
            return collect();
        }

        return MembershipPlan::where('company_id', $company->id)->where('active', true)->where('auto_renew', true)
            ->where('price', '>', 0)->whereIn('billing_cycle', array_keys(self::CYCLES))->with('services')->orderBy('price')->get()
            ->filter(fn ($plan) => filled($plan->terms_text) && $plan->services->every(fn ($service) => (int) $service->company_id === (int) $company->id) && ($plan->duration_days === null || $plan->duration_days === MembershipPlan::defaultCycleDays($plan->billing_cycle)))
            ->values();
    }

    public function snapshot(MembershipPlan $plan): array
    {
        $cycle = self::CYCLES[$plan->billing_cycle];

        return ['plan_id' => $plan->id, 'name' => $plan->name, 'description' => $plan->description,
            'amount' => (string) $plan->price, 'currency' => 'BRL', 'cycle' => $cycle, 'period_months' => self::MONTHS[$cycle],
            'checkout_policy' => 'asaas_calendar_v1', 'first_due_date' => now()->toDateString(), 'terms' => $plan->terms_text, 'grace_days' => 0,
            'terms_version' => hash('sha256', json_encode(['asaas_calendar_v1', $plan->id, $plan->price, $cycle, $plan->terms_text, $plan->max_services_per_cycle,
                $plan->max_product_discount_percent, $plan->max_service_discount_percent, $plan->services->map(fn ($s) => [$s->id, $s->pivot->getAttributes()])->all()], JSON_THROW_ON_ERROR)),
            'max_services_per_cycle' => $plan->max_services_per_cycle, 'product_discount' => $plan->max_product_discount_percent,
            'service_discount' => $plan->max_service_discount_percent,
            'services' => $plan->services->map(fn ($service) => ['id' => $service->id, 'name' => $service->name, 'included' => (bool) $service->pivot->included,
                'quantity' => $service->pivot->quantity_per_cycle, 'discount_percent' => $service->pivot->discount_percent])->all()];
    }
}
