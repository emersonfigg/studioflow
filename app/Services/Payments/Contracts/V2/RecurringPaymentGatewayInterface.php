<?php

namespace App\Services\Payments\Contracts\V2;

use App\Services\Payments\V2\CheckoutRequest;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\Payments\V2\GatewayResource;
use App\Services\Payments\V2\NormalizedEvent;
use App\Services\Payments\V2\SubscriptionCheckout;

/** Optional capability: existing V1 adapters are intentionally unchanged. */
interface RecurringPaymentGatewayInterface
{
    public function createCustomer(FinancialAccountContext $account, int $clientId, array $customer): GatewayResource;

    public function createSubscriptionCheckout(FinancialAccountContext $account, CheckoutRequest $request): SubscriptionCheckout;

    public function getSubscription(FinancialAccountContext $account, string $subscriptionId): GatewayResource;

    public function getInvoice(FinancialAccountContext $account, string $invoiceId): GatewayResource;

    public function getPaymentStatus(FinancialAccountContext $account, string $paymentId): GatewayResource;

    /** Policy must explicitly define the treatment of outstanding invoices. */
    public function cancelRenewal(FinancialAccountContext $account, string $subscriptionId, string $policy): GatewayResource;

    /** Input must already be authenticated by the ingestion boundary. */
    public function normalizeWebhook(FinancialAccountContext $account, array $authenticatedPayload): NormalizedEvent;

    /** @return array{items: list<GatewayResource>, next_cursor: ?string} */
    public function listChargesForReconciliation(FinancialAccountContext $account, ?string $cursor = null): array;
}
