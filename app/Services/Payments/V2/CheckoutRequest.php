<?php

namespace App\Services\Payments\V2;

use InvalidArgumentException;

final readonly class CheckoutRequest
{
    public function __construct(
        public string $intentionId,
        public int $clientId,
        public int $amountCents,
        public string $currency,
        public string $cycle,
        public string $termsVersion,
        public array $snapshot,
    ) {
        if ($intentionId === '' || $clientId < 1 || $amountCents < 1 || $currency !== 'BRL' || $termsVersion === '') {
            throw new InvalidArgumentException('Intenção de contratação inválida.');
        }
    }
}
