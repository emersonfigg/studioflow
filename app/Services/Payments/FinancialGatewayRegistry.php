<?php

namespace App\Services\Payments;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Services\Payments\Contracts\V2\RecurringPaymentGatewayInterface;
use App\Services\Payments\Gateways\AsaasRecurringGateway;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\Payments\V2\FinancialAccountResolver;
use DomainException;

class FinancialGatewayRegistry
{
    private array $testingGateways = [];

    public function registerForTesting(int $accountId, RecurringPaymentGatewayInterface $gateway): void
    {
        if (! app()->environment('testing')) {
            throw new DomainException('test_gateway_forbidden');
        }
        $this->testingGateways[$accountId] = $gateway;
    }

    public function enabled(): bool
    {
        return ! app()->environment('production') && config('financial.reconciliation_enabled') === true;
    }

    public function forContext(FinancialAccountContext $context): RecurringPaymentGatewayInterface
    {
        if (! $this->enabled() || $context->environment !== PaymentIntegrationEnvironment::Sandbox) {
            throw new DomainException('financial_execution_disabled');
        }
        app(FinancialAccountResolver::class)->assertCurrent($context);

        if (isset($this->testingGateways[$context->integrationId])) {
            return $this->testingGateways[$context->integrationId];
        }
        if ($context->provider === PaymentProvider::Asaas && config('financial.asaas_v2_enabled') === true) {
            return app(AsaasRecurringGateway::class);
        }

        return app(PaymentGatewayManager::class)->recurringGatewayFor($context);
    }
}
