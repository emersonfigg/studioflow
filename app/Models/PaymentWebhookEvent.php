<?php

namespace App\Models;

use App\Models\Concerns\ValidatesFinancialCompany;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use ValidatesFinancialCompany;

    protected $table = 'payment_webhook_events';

    protected $fillable = ['company_id', 'payment_account_id', 'external_event_id', 'event_type', 'resource_type', 'external_resource_id', 'occurred_at', 'received_at', 'processed_at', 'payload_hash', 'safe_payload', 'processing_status', 'attempts', 'error_code', 'processing_token', 'lease_until'];

    protected function casts(): array
    {
        return ['safe_payload' => 'array', 'occurred_at' => 'datetime', 'received_at' => 'datetime', 'processed_at' => 'datetime', 'lease_until' => 'datetime', 'attempts' => 'integer'];
    }

    protected function financialReferences(): array
    {
        return ['payment_account_id' => CompanyPaymentIntegration::class];
    }
}
