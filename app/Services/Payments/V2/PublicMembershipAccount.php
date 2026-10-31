<?php

namespace App\Services\Payments\V2;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Models\CompanyPaymentIntegration;
use DomainException;

class PublicMembershipAccount
{
    public function resolve(int $companyId): FinancialAccountContext
    {
        if (app()->environment('production') || config('financial.public_checkout_enabled') !== true
            || config('financial.asaas_v2_enabled') !== true || config('financial.reconciliation_enabled') !== true) {
            throw new DomainException('financial_execution_disabled');
        }
        $accounts = CompanyPaymentIntegration::where('company_id', $companyId)->where('provider', PaymentProvider::Asaas)
            ->where('environment', PaymentIntegrationEnvironment::Sandbox)->where('default_for_memberships', true)->get();
        if ($accounts->count() !== 1) {
            throw new DomainException('ambiguous_membership_account');
        }
        $account = $accounts->first();
        $context = app(FinancialAccountResolver::class)->resolve($companyId, $account->id, PaymentProvider::Asaas, PaymentIntegrationEnvironment::Sandbox);
        $this->assertReady($context);

        return $context;
    }

    public function assertReady(FinancialAccountContext $context): CompanyPaymentIntegration
    {
        app(FinancialAccountResolver::class)->assertCurrent($context);
        $account = CompanyPaymentIntegration::where('company_id', $context->companyId)->findOrFail($context->integrationId);
        if (! $account->active || $account->status !== 'connected' || $account->account_enablement_status !== 'approved'
            || ! filled($account->api_key) || ! $account->connected_at || ($account->expires_at && $account->expires_at->isPast())
            || ! hash_equals($this->credentialFingerprint($account), (string) data_get($account->metadata, 'recurring_v2_credential_fingerprint', ''))) {
            throw new DomainException('membership_account_unavailable');
        }

        return $account;
    }

    public function credentialFingerprint(CompanyPaymentIntegration $account): string
    {
        return hash_hmac('sha256', implode('|', [$account->company_id, $account->id, $account->provider->value,
            $account->environment->value, $account->external_account_id, $account->api_key]), config('app.key'));
    }
}
