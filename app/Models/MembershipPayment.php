<?php

namespace App\Models;

use App\Enums\MembershipPaymentBillingType;
use App\Enums\MembershipPaymentStatus;
use App\Enums\PaymentProvider;
use App\Models\Concerns\ValidatesFinancialCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPayment extends Model
{
    use ValidatesFinancialCompany;

    protected function financialReferences(): array
    {
        return $this->payment_account_id ? ['payment_account_id' => CompanyPaymentIntegration::class, 'customer_membership_id' => CustomerMembership::class, 'client_id' => Client::class, 'membership_invoice_id' => MembershipInvoice::class] : [];
    }

    protected $fillable = [
        'payment_account_id', 'membership_invoice_id', 'external_transaction_id', 'origin', 'confirmed_at', 'received_at', 'refunded_amount',
        'company_id',
        'customer_membership_id',
        'client_id',
        'provider',
        'provider_payment_id',
        'provider_subscription_id',
        'amount',
        'status',
        'billing_type',
        'due_date',
        'cycle_starts_at',
        'cycle_ends_at',
        'paid_at',
        'invoice_url',
        'pix_qr_code',
        'pix_copy_paste',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime', 'received_at' => 'datetime', 'refunded_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'cycle_starts_at' => 'date',
            'cycle_ends_at' => 'date',
            'paid_at' => 'datetime',
            'raw_payload' => 'array',
            'provider' => PaymentProvider::class,
            'status' => MembershipPaymentStatus::class,
            'billing_type' => MembershipPaymentBillingType::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(CustomerMembership::class, 'customer_membership_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
