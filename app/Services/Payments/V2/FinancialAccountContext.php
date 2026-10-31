<?php

namespace App\Services\Payments\V2;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use InvalidArgumentException;

/** Immutable identity only. Credentials must never travel in DTOs or logs. */
final readonly class FinancialAccountContext
{
    public function __construct(
        public int $companyId,
        public int $integrationId,
        public PaymentProvider $provider,
        public PaymentIntegrationEnvironment $environment,
        public string $externalAccountId,
    ) {
        if ($companyId < 1 || $integrationId < 1 || trim($externalAccountId) === '') {
            throw new InvalidArgumentException('A conta financeira precisa de identidade explícita.');
        }
    }

    public function key(): string
    {
        return implode(':', [$this->companyId, $this->integrationId, $this->provider->value, $this->environment->value, $this->externalAccountId]);
    }
}
