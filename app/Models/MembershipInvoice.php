<?php

namespace App\Models;

use App\Models\Concerns\ValidatesFinancialCompany;
use Illuminate\Database\Eloquent\Model;

class MembershipInvoice extends Model
{
    use ValidatesFinancialCompany;

    protected $table = 'membership_invoices';

    protected $fillable = ['company_id', 'payment_account_id', 'customer_membership_id', 'external_invoice_id', 'currency', 'amount', 'net_amount', 'fee_amount', 'status', 'due_date', 'coverage_starts_at', 'coverage_ends_at', 'confirmed_at', 'received_at', 'gateway_observed_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'net_amount' => 'decimal:2', 'fee_amount' => 'decimal:2', 'due_date' => 'date', 'coverage_starts_at' => 'datetime', 'coverage_ends_at' => 'datetime', 'confirmed_at' => 'datetime', 'gateway_observed_at' => 'datetime', 'received_at' => 'datetime'];
    }

    protected function financialReferences(): array
    {
        return ['payment_account_id' => CompanyPaymentIntegration::class, 'customer_membership_id' => CustomerMembership::class];
    }
}
