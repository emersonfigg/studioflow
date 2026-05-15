<?php

namespace App\Services\Payments\Gateways;

use App\Models\Client;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipPayment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\ParsedGatewayWebhook;
use RuntimeException;

class GalaxyPayGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly CompanyPaymentIntegration $integration,
    ) {}

    public function createCustomer(Client $client): array
    {
        throw new RuntimeException('Galaxy Pay ainda não está disponível. Use Asaas ou aguarde a liberação.');
    }

    public function createMembershipCharge(CustomerMembership $membership, array $options = []): array
    {
        throw new RuntimeException('Galaxy Pay ainda não está disponível. Use Asaas ou aguarde a liberação.');
    }

    public function cancelCharge(MembershipPayment $payment): void
    {
        throw new RuntimeException('Galaxy Pay ainda não está disponível.');
    }

    public function getPaymentStatus(MembershipPayment $payment): array
    {
        throw new RuntimeException('Galaxy Pay ainda não está disponível.');
    }

    public function parseWebhook(array $payload): ParsedGatewayWebhook
    {
        throw new RuntimeException('Galaxy Pay ainda não está disponível.');
    }

    public function ping(): void
    {
        throw new RuntimeException('Galaxy Pay ainda não está disponível.');
    }
}
