<?php

namespace App\Services\Payments\Contracts;

use App\Models\Client;
use App\Models\CustomerMembership;
use App\Models\MembershipPayment;
use App\Services\Payments\ParsedGatewayWebhook;

interface PaymentGatewayInterface
{
    /**
     * Ensure remote customer exists; persist refs on $client when needed.
     *
     * @return array{customer_id: string, raw?: array<string, mixed>}
     */
    public function createCustomer(Client $client): array;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createMembershipCharge(CustomerMembership $membership, array $options = []): array;

    public function cancelCharge(MembershipPayment $payment): void;

    /**
     * @return array<string, mixed>
     */
    public function getPaymentStatus(MembershipPayment $payment): array;

    public function parseWebhook(array $payload): ParsedGatewayWebhook;

    /**
     * Lightweight request to validate credentials.
     */
    public function ping(): void;
}
