<?php

namespace App\Jobs;

use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\Gateways\AsaasRecurringGateway;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\SubscriptionReconciliationService;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ReconcileMembershipCheckout extends FinancialJob
{
    public function __construct(public readonly FinancialAccountContext $context, public readonly int $intentionId)
    {
        parent::__construct();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('financial-checkout:'.$this->context->integrationId.':'.$this->intentionId))->shared()->releaseAfter(15)->expireAfter(180)];
    }

    public function handle(SubscriptionReconciliationService $service, FinancialGatewayRegistry $registry): void
    {
        try {
            $gateway = $registry->forContext($this->context);
            if (! $gateway instanceof AsaasRecurringGateway) {
                throw new \DomainException('unsupported_event');
            }
            $cursor = null;
            $seen = [];
            do {
                $page = $gateway->listCheckoutCharges($this->context, $this->intentionId, $cursor);
                foreach ($page['items'] as $invoice) {
                    $service->applyInvoice($this->context, $invoice);
                }
                $cursor = $page['next_cursor'];
                if ($cursor !== null && (isset($seen[$cursor]) || count($seen) >= 1000)) {
                    throw new \DomainException('invalid_reconciliation_cursor');
                }
                if ($cursor !== null) {
                    $seen[$cursor] = true;
                }
            } while ($cursor !== null);
        } catch (\Throwable) {
            throw new \RuntimeException('financial_checkout_reconciliation_retry_required');
        }
    }
}
