<?php

namespace Tests\Support;

use App\Services\Payments\Contracts\V2\RecurringPaymentGatewayInterface;
use App\Services\Payments\V2\CheckoutRequest;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\Payments\V2\GatewayResource;
use App\Services\Payments\V2\NormalizedEvent;
use App\Services\Payments\V2\SubscriptionCheckout;
use Illuminate\Support\Facades\DB;

class FakeRecurringGateway implements RecurringPaymentGatewayInterface
{
    public int $calls = 0;

    public bool $failOnce = false;

    public int $delayMicroseconds = 0;

    public string $subscriptionStatus = 'active';

    public array $pages = [];

    public array $transactionDepths = [];

    public function __construct(public GatewayResource $invoice) {}

    public function createCustomer(FinancialAccountContext $account, int $clientId, array $customer): GatewayResource
    {
        throw new \LogicException('No writes in Stage 2.');
    }

    public function createSubscriptionCheckout(FinancialAccountContext $account, CheckoutRequest $request): SubscriptionCheckout
    {
        throw new \LogicException('No checkout in Stage 2.');
    }

    public function getSubscription(FinancialAccountContext $account, string $subscriptionId): GatewayResource
    {
        return new GatewayResource($subscriptionId, $this->subscriptionStatus, ['account_id' => $account->externalAccountId]);
    }

    public function getInvoice(FinancialAccountContext $account, string $invoiceId): GatewayResource
    {
        $this->calls++;
        $this->transactionDepths[] = DB::transactionLevel();
        if ($this->delayMicroseconds) {
            usleep($this->delayMicroseconds);
        }
        if ($this->failOnce) {
            $this->failOnce = false;
            throw new \RuntimeException('simulated private gateway message');
        }

        return $this->invoice;
    }

    public function getPaymentStatus(FinancialAccountContext $account, string $paymentId): GatewayResource
    {
        return $this->getInvoice($account, $paymentId);
    }

    public function cancelRenewal(FinancialAccountContext $account, string $subscriptionId, string $policy): GatewayResource
    {
        throw new \LogicException('No remote mutations in Stage 2.');
    }

    public function normalizeWebhook(FinancialAccountContext $account, array $authenticatedPayload): NormalizedEvent
    {
        throw new \LogicException('Tests reconcile authoritative snapshots instead.');
    }

    public function listChargesForReconciliation(FinancialAccountContext $account, ?string $cursor = null): array
    {
        return $this->pages[$cursor ?? 'first'] ?? ['items' => [$this->invoice], 'next_cursor' => null];
    }
}
