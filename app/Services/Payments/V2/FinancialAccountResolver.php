<?php

namespace App\Services\Payments\V2;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Models\CompanyPaymentIntegration;
use DomainException;

final class FinancialAccountResolver
{
    /** No default-account fallback: historical references must remain unambiguous. */
    public function resolve(int $companyId, int $integrationId, PaymentProvider $provider, PaymentIntegrationEnvironment $environment): FinancialAccountContext
    {
        $integration = CompanyPaymentIntegration::query()
            ->where('company_id', $companyId)->whereKey($integrationId)
            ->where('provider', $provider->value)->where('environment', $environment->value)
            ->firstOrFail();

        if (! $integration->account_verified_at || ! filled($integration->external_account_id)) {
            throw new DomainException('Identidade financeira ainda não verificada.');
        }

        return new FinancialAccountContext($companyId, $integrationId, $provider, $environment, $integration->external_account_id);
    }

    /** Read-only validation, including after a credential rotation or disconnect. */
    public function assertCurrent(FinancialAccountContext $context): void
    {
        if ($this->resolve($context->companyId, $context->integrationId, $context->provider, $context->environment)->key() !== $context->key()) {
            throw new DomainException('A identidade da conta financeira foi alterada.');
        }
    }
}
