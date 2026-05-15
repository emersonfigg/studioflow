<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\AsaasGateway;
use App\Services\Payments\Gateways\GalaxyPayGateway;
use App\Services\Payments\Gateways\MercadoPagoGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function resolveActiveIntegrationForMemberships(int $companyId): CompanyPaymentIntegration
    {
        $integration = CompanyPaymentIntegration::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->where('default_for_memberships', true)
            ->orderByDesc('id')
            ->first();

        if (! $integration) {
            $integration = CompanyPaymentIntegration::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->orderByDesc('default_for_memberships')
                ->orderByDesc('id')
                ->first();
        }

        if (! $integration) {
            throw PaymentGatewayNotConfiguredException::forCompany();
        }

        return $integration;
    }

    public function gatewayFor(CompanyPaymentIntegration $integration): PaymentGatewayInterface
    {
        return match ($integration->provider) {
            PaymentProvider::Asaas => new AsaasGateway($integration),
            PaymentProvider::GalaxyPay => new GalaxyPayGateway($integration),
            PaymentProvider::MercadoPago => new MercadoPagoGateway($integration),
            default => throw new InvalidArgumentException('Provedor de pagamento inválido.'),
        };
    }

    public function gatewayForCompany(Company $company): PaymentGatewayInterface
    {
        $integration = $this->resolveActiveIntegrationForMemberships((int) $company->id);

        return $this->gatewayFor($integration);
    }
}
