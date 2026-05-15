<?php

namespace App\Services\Payments;

final class ParsedGatewayWebhook
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $providerPaymentId,
        public readonly string $event,
        public readonly ?string $providerPaymentStatus,
        public readonly array $payload = [],
    ) {}
}
