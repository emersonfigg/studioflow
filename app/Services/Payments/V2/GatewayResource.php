<?php

namespace App\Services\Payments\V2;

/** Only allowlisted, non-sensitive gateway fields belong in data. */
final readonly class GatewayResource
{
    public function __construct(public string $id, public string $status, public array $data = []) {}
}
