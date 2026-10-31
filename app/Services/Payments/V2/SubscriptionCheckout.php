<?php

namespace App\Services\Payments\V2;

use DateTimeImmutable;

final readonly class SubscriptionCheckout
{
    public function __construct(public string $id, public string $url, public DateTimeImmutable $expiresAt) {}
}
