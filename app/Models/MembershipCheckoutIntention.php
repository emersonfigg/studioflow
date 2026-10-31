<?php

namespace App\Models;

use App\Models\Concerns\ValidatesFinancialCompany;
use Illuminate\Database\Eloquent\Model;

class MembershipCheckoutIntention extends Model
{
    use ValidatesFinancialCompany;

    protected $table = 'membership_checkout_intentions';

    protected $fillable = ['public_identity_id', 'membership_plan_id', 'external_customer_id', 'creation_started_at', 'reference', 'company_id', 'payment_account_id', 'client_id', 'customer_membership_id', 'commercial_snapshot', 'terms_version', 'accepted_terms_at', 'status', 'external_checkout_id', 'expires_at'];

    protected function casts(): array
    {
        return ['creation_started_at' => 'datetime', 'commercial_snapshot' => 'array', 'accepted_terms_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    protected function financialReferences(): array
    {
        return ['payment_account_id' => CompanyPaymentIntegration::class, 'client_id' => Client::class, 'customer_membership_id' => CustomerMembership::class, 'membership_plan_id' => MembershipPlan::class, 'public_identity_id' => MembershipPublicIdentity::class];
    }
}
