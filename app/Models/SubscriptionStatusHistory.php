<?php

namespace App\Models;

use App\Models\Concerns\ValidatesFinancialCompany;
use Illuminate\Database\Eloquent\Model;

class SubscriptionStatusHistory extends Model
{
    use ValidatesFinancialCompany;

    protected $table = 'subscription_status_history';

    protected $fillable = ['company_id', 'customer_membership_id', 'payment_webhook_event_id', 'membership_invoice_id', 'actor_id', 'original_actor_id', 'previous_status', 'new_status', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    protected function financialReferences(): array
    {
        return ['customer_membership_id' => CustomerMembership::class, 'payment_webhook_event_id' => PaymentWebhookEvent::class, 'membership_invoice_id' => MembershipInvoice::class];
    }
}
