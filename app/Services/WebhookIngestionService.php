<?php

namespace App\Services;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Jobs\ProcessFinancialEvent;
use App\Models\CompanyPaymentIntegration;
use App\Models\PaymentWebhookEvent;
use Illuminate\Validation\ValidationException;

class WebhookIngestionService
{
    public function ingestAsaas(array $payload, ?string $secret, ?int $accountId = null, ?string $environment = null): PaymentWebhookEvent
    {
        abort_if($secret === null || $secret === '' || strlen($secret) > 255, 403);
        $query = CompanyPaymentIntegration::query()->where('provider', PaymentProvider::Asaas);
        if ($accountId !== null) {
            $query->whereKey($accountId)->where('environment', $environment);
        }
        // Legacy URL: identify one exact secret, never a default integration.
        $matches = $query->get()->filter(fn ($account) => filled($account->webhook_secret) && hash_equals((string) $account->webhook_secret, $secret));
        abort_unless($matches->count() === 1, 403);
        $account = $matches->first();
        $verified = $account->account_verified_at && filled($account->external_account_id);
        abort_if($verified && isset($payload['account']['id']) && $payload['account']['id'] !== $account->external_account_id, 403);
        abort_if(isset($payload['environment']) && $payload['environment'] !== $account->environment->value, 403);
        abort_if($environment !== null && PaymentIntegrationEnvironment::tryFrom($environment) === null, 403);

        foreach (['id', 'event'] as $required) {
            if (! isset($payload[$required]) || ! is_string($payload[$required]) || $payload[$required] === '' || strlen($payload[$required]) > 255) {
                throw ValidationException::withMessages([$required => 'Identificador de evento inválido.']);
            }
        }
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $safePayment = array_intersect_key($payment, array_flip(['id', 'subscription', 'customer', 'status', 'value', 'netValue', 'billingType', 'dueDate']));
        // Scalar values only: never persist arbitrary nested card/customer data.
        $safePayment = array_filter($safePayment, fn ($value) => is_scalar($value) || $value === null);
        $safe = ['id' => $payload['id'], 'event' => $payload['event'], 'payment' => $safePayment];
        ksort($safe['payment']);
        $resourceType = 'payment';
        if (str_starts_with($payload['event'], 'CHECKOUT_') || str_starts_with($payload['event'], 'SUBSCRIPTION_')) {
            $resourceType = str_starts_with($payload['event'], 'CHECKOUT_') ? 'checkout' : 'subscription';
            $resource = is_array($payload[$resourceType] ?? null) ? $payload[$resourceType] : [];
            $safeResource = array_filter(array_intersect_key($resource, array_flip(['id', 'customer', 'status', 'externalReference'])), fn ($v) => is_scalar($v) || $v === null);
            ksort($safeResource);
            $safe = ['id' => $payload['id'], 'event' => $payload['event'], $resourceType => $safeResource];
        }
        $hash = hash('sha256', json_encode($safe, JSON_THROW_ON_ERROR));
        $known = in_array($payload['event'], ['PAYMENT_CREATED', 'PAYMENT_UPDATED', 'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_OVERDUE', 'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED', 'PAYMENT_REFUNDED', 'PAYMENT_DELETED', 'PAYMENT_CHARGEBACK_REQUESTED'], true);
        if ($resourceType !== 'payment') {
            $known = config('financial.asaas_v2_enabled') === true && in_array($payload['event'], ['CHECKOUT_CREATED', 'CHECKOUT_PAID', 'CHECKOUT_CANCELED', 'CHECKOUT_EXPIRED',
                'SUBSCRIPTION_CREATED', 'SUBSCRIPTION_UPDATED', 'SUBSCRIPTION_INACTIVATED', 'SUBSCRIPTION_DELETED'], true);
        }
        $resourceId = $safe[$resourceType]['id'] ?? null;
        $processable = $verified && $known && is_string($resourceId) && $resourceId !== '' && strlen($resourceId) <= 255;
        $event = PaymentWebhookEvent::query()->firstOrCreate(
            ['payment_account_id' => $account->id, 'external_event_id' => $payload['id']],
            ['company_id' => $account->company_id, 'event_type' => $payload['event'], 'resource_type' => $processable ? $resourceType : 'unknown', 'external_resource_id' => $processable ? $resourceId : null, 'received_at' => now(), 'payload_hash' => $hash, 'safe_payload' => $safe, 'processing_status' => $processable ? 'pending' : 'quarantined', 'error_code' => $processable ? null : ($verified ? 'unsupported_event' : 'account_unverified')],
        );
        abort_unless(hash_equals($event->payload_hash, $hash), 409);
        if (in_array($event->processing_status, ['pending', 'failed'], true)) {
            ProcessFinancialEvent::dispatch($event->id)->onConnection(config('financial.queue_connection'))->onQueue(config('financial.queue'))->afterCommit();
        }

        return $event;
    }
}
