<?php

namespace App\Models;

use App\Models\Concerns\ValidatesFinancialCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerMembership extends Model
{
    use ValidatesFinancialCompany;

    protected function financialReferences(): array
    {
        return $this->payment_account_id ? ['payment_account_id' => CompanyPaymentIntegration::class, 'client_id' => Client::class, 'membership_plan_id' => MembershipPlan::class] : [];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_CANCELED,
        self::STATUS_EXPIRED,
        self::STATUS_OVERDUE,
    ];

    protected $fillable = [
        'suspension_reason', 'gateway_subscription_status', 'payment_account_id', 'external_subscription_id', 'external_checkout_id', 'billing_mode', 'origin', 'legacy_classification', 'legacy_classified_at', 'legacy_evidence', 'commercial_snapshot', 'terms_version', 'financial_status', 'paid_through', 'grace_until', 'suspended_at', 'cancel_requested_at', 'effective_ends_at',
        'company_id',
        'client_id',
        'membership_plan_id',
        'service_order_id',
        'status',
        'starts_at',
        'ends_at',
        'renews_at',
        'current_cycle_starts_at',
        'current_cycle_ends_at',
        'auto_renew',
        'accepted_terms_at',
        'accepted_terms_ip',
        'accepted_terms_user_agent',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'legacy_classified_at' => 'datetime', 'legacy_evidence' => 'array', 'commercial_snapshot' => 'array', 'paid_through' => 'datetime', 'grace_until' => 'datetime', 'suspended_at' => 'datetime', 'cancel_requested_at' => 'datetime', 'effective_ends_at' => 'datetime',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'renews_at' => 'date',
            'current_cycle_starts_at' => 'date',
            'current_cycle_ends_at' => 'date',
            'auto_renew' => 'boolean',
            'accepted_terms_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * @return HasMany<MembershipUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MembershipUsage::class);
    }

    /**
     * @return HasMany<MembershipPayment, $this>
     */
    public function membershipPayments(): HasMany
    {
        return $this->hasMany(MembershipPayment::class, 'customer_membership_id');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Human-readable status for UI (stored value stays English).
     */
    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Aguardando pagamento',
            self::STATUS_ACTIVE => 'Ativa',
            self::STATUS_PAUSED => 'Pausada',
            self::STATUS_CANCELED => 'Cancelada',
            self::STATUS_EXPIRED => 'Expirada',
            self::STATUS_OVERDUE => 'Em atraso',
            default => filled($status) ? (string) $status : '',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status);
    }
}
