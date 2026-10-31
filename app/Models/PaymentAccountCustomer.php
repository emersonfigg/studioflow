<?php

namespace App\Models;

use App\Models\Concerns\ValidatesFinancialCompany;
use Illuminate\Database\Eloquent\Model;

class PaymentAccountCustomer extends Model
{
    use ValidatesFinancialCompany;

    protected $table = 'payment_account_customers';

    protected $fillable = ['company_id', 'payment_account_id', 'client_id', 'external_customer_id'];

    protected function casts(): array
    {
        return [];
    }

    protected function financialReferences(): array
    {
        return ['payment_account_id' => CompanyPaymentIntegration::class, 'client_id' => Client::class];
    }
}
