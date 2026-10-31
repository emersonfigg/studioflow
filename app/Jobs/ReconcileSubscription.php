<?php

namespace App\Jobs;

use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\SubscriptionReconciliationService;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ReconcileSubscription extends FinancialJob
{
    public function __construct(public readonly FinancialAccountContext $context, public readonly int $membershipId)
    {
        parent::__construct();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('financial-subscription:'.$this->context->integrationId.':'.$this->membershipId))->shared()->releaseAfter(15)->expireAfter(180)];
    }

    public function handle(SubscriptionReconciliationService $service): void
    {
        try {
            $service->reconcile($this->context, $this->membershipId);
        } catch (\Throwable) {
            throw new \RuntimeException('financial_reconciliation_retry_required');
        }
    }
}
