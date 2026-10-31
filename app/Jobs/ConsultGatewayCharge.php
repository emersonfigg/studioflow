<?php

namespace App\Jobs;

use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\SubscriptionReconciliationService;

class ConsultGatewayCharge extends FinancialJob
{
    public function __construct(public readonly FinancialAccountContext $context, public readonly string $chargeId, public readonly int $membershipId)
    {
        parent::__construct();
    }

    public function handle(FinancialGatewayRegistry $gateways, SubscriptionReconciliationService $service): void
    {
        try {
            $resource = $gateways->forContext($this->context)->getInvoice($this->context, $this->chargeId);
            if ($resource->id !== $this->chargeId) {
                throw new \DomainException('resource_mismatch');
            }
            $service->applyInvoice($this->context, $resource, expectedMembershipId: $this->membershipId);
        } catch (\Throwable) {
            throw new \RuntimeException('financial_charge_retry_required');
        }
    }
}
