<?php

namespace App\Jobs;

use App\Models\CustomerMembership;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\SubscriptionFinancialStateService;

class RecalculateFinancialState extends FinancialJob
{
    public function __construct(public readonly FinancialAccountContext $context, public readonly int $membershipId)
    {
        parent::__construct();
    }

    public function handle(FinancialGatewayRegistry $gateways, SubscriptionFinancialStateService $service): void
    {
        $gateways->forContext($this->context);
        CustomerMembership::query()->where('company_id', $this->context->companyId)->where('payment_account_id', $this->context->integrationId)->findOrFail($this->membershipId);
        $service->recalculate($this->membershipId);
    }
}
