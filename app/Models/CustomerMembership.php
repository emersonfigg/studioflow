<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerMembership extends Model
{
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
