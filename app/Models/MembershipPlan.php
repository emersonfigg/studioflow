<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    public const BILLING_MONTHLY = 'monthly';

    public const BILLING_QUARTERLY = 'quarterly';

    public const BILLING_SEMIANNUAL = 'semiannual';

    public const BILLING_ANNUAL = 'annual';

    public const BILLING_CYCLES = [
        self::BILLING_MONTHLY,
        self::BILLING_QUARTERLY,
        self::BILLING_SEMIANNUAL,
        self::BILLING_ANNUAL,
    ];

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'price',
        'billing_cycle',
        'duration_days',
        'active',
        'auto_renew',
        'max_services_per_cycle',
        'max_product_discount_percent',
        'max_service_discount_percent',
        'terms_text',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'active' => 'boolean',
            'auto_renew' => 'boolean',
            'max_services_per_cycle' => 'integer',
            'max_product_discount_percent' => 'decimal:2',
            'max_service_discount_percent' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsToMany<Service, $this, MembershipPlanService>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'membership_plan_services')
            ->using(MembershipPlanService::class)
            ->withPivot([
                'id',
                'company_id',
                'quantity_per_cycle',
                'discount_percent',
                'included',
                'special_duration_minutes',
            ])
            ->withTimestamps();
    }

    /**
     * @return HasMany<CustomerMembership, $this>
     */
    public function customerMemberships(): HasMany
    {
        return $this->hasMany(CustomerMembership::class);
    }

    public static function defaultCycleDays(string $billingCycle): int
    {
        return match ($billingCycle) {
            self::BILLING_MONTHLY => 30,
            self::BILLING_QUARTERLY => 90,
            self::BILLING_SEMIANNUAL => 182,
            self::BILLING_ANNUAL => 365,
            default => 30,
        };
    }

    /**
     * Human-readable billing cycle label for UI (stored value stays English).
     */
    public static function billingCycleLabel(?string $cycle): string
    {
        return match ($cycle) {
            self::BILLING_MONTHLY => 'Mensal',
            self::BILLING_QUARTERLY => 'Trimestral',
            self::BILLING_SEMIANNUAL => 'Semestral',
            self::BILLING_ANNUAL => 'Anual',
            default => filled($cycle) ? (string) $cycle : '',
        };
    }

    public function getBillingCycleLabelAttribute(): string
    {
        return self::billingCycleLabel($this->billing_cycle);
    }

    public function resolvedCycleDays(): int
    {
        if ($this->duration_days !== null) {
            return max(1, (int) $this->duration_days);
        }

        return self::defaultCycleDays((string) $this->billing_cycle);
    }
}
