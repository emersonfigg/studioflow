<?php

namespace App\Services\Payments\V2;

use DateTimeImmutable;

final readonly class NormalizedEvent
{
    public function __construct(
        public string $eventId,
        public string $externalAccountId,
        public string $type,
        public string $resourceType,
        public string $resourceId,
        public DateTimeImmutable $occurredAt,
        public string $status,
    ) {}
}
