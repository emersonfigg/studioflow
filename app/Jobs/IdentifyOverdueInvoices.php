<?php

namespace App\Jobs;

use App\Models\CustomerMembership;
use App\Models\MembershipInvoice;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\V2\FinancialAccountContext;
use App\Services\SubscriptionFinancialStateService;
use Illuminate\Support\Facades\DB;

class IdentifyOverdueInvoices extends FinancialJob
{
    public function __construct(public readonly FinancialAccountContext $context, public readonly int $membershipId)
    {
        parent::__construct();
    }

    public function handle(FinancialGatewayRegistry $gateways, SubscriptionFinancialStateService $states): void
    {
        $gateways->forContext($this->context);
        $ids = DB::transaction(function () use ($states) {
            $membership = CustomerMembership::query()->where('company_id', $this->context->companyId)->where('payment_account_id', $this->context->integrationId)
                ->where('billing_mode', 'recurring_v2')->whereKey($this->membershipId)->lockForUpdate()->firstOrFail();
            $invoices = MembershipInvoice::query()->where('payment_account_id', $this->context->integrationId)->where('customer_membership_id', $membership->id)
                ->where('status', 'pending')->whereNull('confirmed_at')->whereDate('due_date', '<', today())->get();
            foreach ($invoices as $invoice) {
                $invoice->update(['status' => 'overdue']);
            }
            $states->recalculate($membership->id);

            return $invoices->pluck('external_invoice_id');
        });
        foreach ($ids as $id) {
            ConsultGatewayCharge::dispatch($this->context, $id, $this->membershipId);
        }
    }
}
