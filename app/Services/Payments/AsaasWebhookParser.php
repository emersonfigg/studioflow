<?php

namespace App\Services\Payments;

final class AsaasWebhookParser
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function parse(array $payload): ParsedGatewayWebhook
    {
        $event = (string) ($payload['event'] ?? '');
        $payment = $payload['payment'] ?? [];
        if (! is_array($payment)) {
            $payment = [];
        }

        $id = (string) ($payment['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('Webhook Asaas sem identificador de pagamento.');
        }

        $status = isset($payment['status']) ? (string) $payment['status'] : null;

        return new ParsedGatewayWebhook(
            providerPaymentId: $id,
            event: $event !== '' ? $event : 'UNKNOWN',
            providerPaymentStatus: $status,
            payload: $payload,
        );
    }
}
