<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\CustomerMembership;
use App\Models\MembershipCheckoutIntention;
use App\Models\MembershipPublicIdentity;
use App\Services\Payments\FinancialAmount;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\V2\CheckoutRequest;
use App\Services\Payments\V2\PublicMembershipAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicMembershipEnrollment
{
    public function contract(Company $company, MembershipPublicIdentity $identity, array $data): MembershipCheckoutIntention
    {
        abort_unless((int) $identity->company_id === (int) $company->id, 403);
        $context = app(PublicMembershipAccount::class)->resolve($company->id);
        $plan = app(PublicMembershipCatalog::class)->plans($company)->firstWhere('id', (int) $data['membership_plan_id']);
        abort_unless($plan, 404);
        $snapshot = app(PublicMembershipCatalog::class)->snapshot($plan);
        if (! hash_equals($snapshot['terms_version'], $data['terms_version'])) {
            throw ValidationException::withMessages(['terms_version' => 'O plano foi atualizado. Revise o preço e as condições novamente.']);
        }
        $intention = DB::transaction(function () use ($company, $identity, $plan, $context, $snapshot, $data) {
            $locked = MembershipPublicIdentity::where('company_id', $company->id)->whereKey($identity->id)->lockForUpdate()->firstOrFail();
            $existing = MembershipCheckoutIntention::where('public_identity_id', $locked->id)->where('membership_plan_id', $plan->id)->first();
            if ($existing) {
                if ((int) $existing->payment_account_id !== $context->integrationId) {
                    throw new \DomainException('membership_account_changed');
                }

                return $existing;
            }
            if (! $locked->client_id) {
                // Email ownership was verified; a phone number never establishes financial identity.
                $matches = Client::where('company_id', $company->id)->whereRaw('LOWER(email) = ?', [mb_strtolower($locked->email)])->get();
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages(['identity' => 'Não foi possível concluir a identificação. Contate o estabelecimento.']);
                }
                $client = $matches->first() ?? Client::create(['company_id' => $company->id, 'name' => $data['name'], 'email' => $locked->email,
                    'phone' => preg_replace('/\D/', '', $data['phone']), 'active' => true]);
                $locked->update(['client_id' => $client->id]);
            }
            $membership = CustomerMembership::create(['company_id' => $company->id, 'client_id' => $locked->client_id, 'membership_plan_id' => $plan->id,
                'payment_account_id' => $context->integrationId, 'billing_mode' => 'recurring_v2', 'origin' => 'public_checkout', 'status' => 'pending',
                'financial_status' => 'pending', 'starts_at' => $snapshot['first_due_date'], 'auto_renew' => true,
                'current_cycle_starts_at' => $snapshot['first_due_date'],
                'current_cycle_ends_at' => CarbonImmutable::parse($snapshot['first_due_date'])->addMonthsNoOverflow($snapshot['period_months'])->subDay()->toDateString(),
                'commercial_snapshot' => $snapshot, 'terms_version' => $snapshot['terms_version'], 'accepted_terms_at' => now(),
                'accepted_terms_ip' => $data['ip'], 'accepted_terms_user_agent' => $data['user_agent']]);

            return MembershipCheckoutIntention::create(['reference' => (string) Str::uuid(), 'company_id' => $company->id,
                'payment_account_id' => $context->integrationId, 'client_id' => $locked->client_id, 'customer_membership_id' => $membership->id,
                'public_identity_id' => $locked->id, 'membership_plan_id' => $plan->id, 'commercial_snapshot' => $snapshot,
                'terms_version' => $snapshot['terms_version'], 'accepted_terms_at' => now(), 'status' => 'pending']);
        }, 3);
        // This durable, atomic claim is deliberately never reset on timeout or uncertain HTTP responses.
        $claimed = MembershipCheckoutIntention::whereKey($intention->id)->whereNull('creation_started_at')
            ->update(['creation_started_at' => now(), 'status' => 'creating']);
        if (! $claimed) {
            return $intention->fresh();
        }
        try {
            $gateway = app(FinancialGatewayRegistry::class)->forContext($context);
            $customer = $gateway->createCustomer($context, $intention->client_id, ['name' => $data['name'], 'email' => $identity->email,
                'mobilePhone' => preg_replace('/\D/', '', $data['phone']), 'externalReference' => $intention->reference]);
            $intention->update(['external_customer_id' => $customer->id]);
            $checkout = $gateway->createSubscriptionCheckout($context, new CheckoutRequest($intention->reference, $intention->client_id,
                FinancialAmount::cents($intention->commercial_snapshot['amount']), 'BRL', $intention->commercial_snapshot['cycle'], $intention->terms_version,
                array_merge($intention->commercial_snapshot, ['customer_id' => $customer->id, 'callback' => array_fill_keys(['successUrl', 'cancelUrl', 'expiredUrl'],
                    route('public-memberships.result', [$company, $intention->reference]))])));
            DB::transaction(function () use ($intention, $checkout) {
                $locked = MembershipCheckoutIntention::whereKey($intention->id)->lockForUpdate()->firstOrFail();
                if ($locked->external_checkout_id && $locked->external_checkout_id !== $checkout->id) {
                    throw new \DomainException('checkout_binding_mismatch');
                }
                $locked->update(['external_checkout_id' => $checkout->id, 'expires_at' => $checkout->expiresAt,
                    'status' => in_array($locked->status, ['pending', 'creating', 'creation_unknown'], true) ? 'ready' : $locked->status]);
                CustomerMembership::whereKey($intention->customer_membership_id)->update(['external_checkout_id' => $checkout->id]);
            }, 3);
        } catch (\Throwable) {
            MembershipCheckoutIntention::whereKey($intention->id)->where('status', 'creating')->update(['status' => 'creation_unknown']);
        }

        return $intention->fresh();
    }
}
