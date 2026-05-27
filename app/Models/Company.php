<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Support\MediaStorage;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'phone',
        'address',
        'cnpj',
        'instagram',
        'description',
        'logo',
        'favicon_path',
        'cover_image_path',
        'primary_color',
        'secondary_color',
        'accent_color',
        'public_headline',
        'public_subheadline',
        'welcome_message',
        'custom_footer_text',
        'receipt_message',
        'birthday_congratulations_message',
        'brand_enabled',
        'active',
        'is_internal',
        'onboarding_completed_at',
        'auto_print_receipt',
        'online_booking_payment_enabled',
        'booking_payment_requirement',
        'booking_payment_mode',
        'booking_deposit_type',
        'booking_deposit_value',
        'booking_payment_expiration_minutes',
        'booking_auto_cancel_unpaid',
        'client_code_counter',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_internal' => 'boolean',
            'brand_enabled' => 'boolean',
            'auto_print_receipt' => 'boolean',
            'online_booking_payment_enabled' => 'boolean',
            'booking_payment_requirement' => 'string',
            'booking_deposit_value' => 'decimal:2',
            'booking_payment_expiration_minutes' => 'integer',
            'booking_auto_cancel_unpaid' => 'boolean',
            'client_code_counter' => 'integer',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            if (! filled($company->slug)) {
                $company->slug = static::uniqueSlugForName((string) $company->name);
            }
        });

        static::saving(function (Company $company): void {
            if (filled($company->slug)) {
                $company->slug = Str::slug((string) $company->slug);
            }
        });
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $query = $this->newQuery();

        if ($field !== null) {
            return $query->where($field, $value)->first();
        }

        $query->where('slug', $value);

        if (ctype_digit((string) $value)) {
            $query->orWhere($this->getKeyName(), (int) $value);
        }

        return $query->first();
    }

    public static function uniqueSlugForName(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'empresa';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Get the users for the company.
     *
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the clients for the company.
     *
     * @return HasMany<Client>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Get the services for the company.
     *
     * @return HasMany<Service>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Get the products for the company.
     *
     * @return HasMany<Product>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the appointments for the company.
     *
     * @return HasMany<Appointment>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<MembershipPlan>
     */
    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    /**
     * @return HasMany<CompanyPaymentIntegration>
     */
    public function paymentIntegrations(): HasMany
    {
        return $this->hasMany(CompanyPaymentIntegration::class);
    }

    public function bookingPayments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function publicMedia(): HasMany
    {
        return $this->hasMany(CompanyPublicMedia::class);
    }

    public function bookingCoverImages(): HasMany
    {
        return $this->publicMedia()
            ->where('type', 'booking_cover')
            ->where('is_active', true)
            ->orderBy('position');
    }

    /**
     * @return HasMany<AppointmentReview>
     */
    public function appointmentReviews(): HasMany
    {
        return $this->hasMany(AppointmentReview::class);
    }

    public function scopeCustomer($query)
    {
        return $query
            ->where('is_internal', false)
            ->whereDoesntHave('users', fn ($userQuery) => $userQuery->where('global_role', 'super_admin'));
    }

    public function isInternal(): bool
    {
        if ((bool) $this->is_internal) {
            return true;
        }

        if ($this->relationLoaded('users')) {
            return $this->users->contains(fn (User $user): bool => $user->isSuperAdmin());
        }

        return $this->users()
            ->where('global_role', 'super_admin')
            ->exists();
    }

    /**
     * Get the payments for the company.
     *
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the product sales for the company.
     *
     * @return HasMany<ProductSale>
     */
    public function productSales(): HasMany
    {
        return $this->hasMany(ProductSale::class);
    }

    /**
     * Get the commission settlements for the company.
     *
     * @return HasMany<CommissionSettlement>
     */
    public function commissionSettlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class);
    }

    /**
     * Get cash registers for the company.
     *
     * @return HasMany<CashRegister>
     */
    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    /**
     * Get cash movements for the company.
     *
     * @return HasMany<CashMovement>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * Get the working hours for professionals in the company.
     *
     * @return HasMany<ProfessionalWorkingHour>
     */
    public function professionalWorkingHours(): HasMany
    {
        return $this->hasMany(ProfessionalWorkingHour::class);
    }

    /**
     * Get the day overrides for professionals in the company.
     *
     * @return HasMany<ProfessionalDayOverride>
     */
    public function professionalDayOverrides(): HasMany
    {
        return $this->hasMany(ProfessionalDayOverride::class);
    }

    /**
     * Get the public URL for the company logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        $logoPath = $this->normalizedLogoPath();

        return MediaStorage::url($logoPath);
    }

    /**
     * Normalize stored logo paths to a public-disk relative path.
     */
    public function normalizedLogoPath(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return MediaStorage::normalizePath($this->logo);
    }

    public function normalizedFaviconPath(): ?string
    {
        if (! $this->favicon_path) {
            return null;
        }

        return MediaStorage::normalizePath($this->favicon_path);
    }

    public function normalizedCoverImagePath(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return MediaStorage::normalizePath($this->cover_image_path);
    }

    /**
     * Public booking / branding headline with safe fallback.
     */
    public function publicDisplayHeadline(): string
    {
        $h = $this->safeDisplayText($this->public_headline);

        return $h ?? trim((string) $this->name);
    }

    /**
     * Public booking subheadline; falls back to short description when empty.
     */
    public function publicDisplaySubheadline(): ?string
    {
        $s = $this->safeDisplayText($this->public_subheadline);
        if ($s !== null) {
            return $s;
        }

        return $this->safeDescription();
    }

    /**
     * Safe company description for layouts and branding surfaces.
     */
    public function safeDescription(): ?string
    {
        return $this->safeDisplayText($this->description);
    }

    /**
     * Determine if the company finished the first setup.
     */
    public function onboardingCompleted(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function onlineBookingPaymentEnabled(): bool
    {
        return (bool) $this->online_booking_payment_enabled
            && in_array((string) $this->booking_payment_mode, ['deposit', 'full'], true);
    }

    public function bookingPaymentDisabled(): bool
    {
        return ! $this->onlineBookingPaymentEnabled()
            || (string) ($this->booking_payment_requirement ?: 'disabled') === 'disabled';
    }

    public function bookingPaymentOptional(): bool
    {
        return $this->onlineBookingPaymentEnabled()
            && (string) ($this->booking_payment_requirement ?: 'disabled') === 'optional';
    }

    public function bookingPaymentRequired(): bool
    {
        return $this->onlineBookingPaymentEnabled()
            && (string) ($this->booking_payment_requirement ?: 'required') === 'required';
    }

    public function activeMercadoPagoIntegration(): ?CompanyPaymentIntegration
    {
        /** @var ?CompanyPaymentIntegration $integration */
        $integration = $this->paymentIntegrations()
            ->where('provider', PaymentProvider::MercadoPago)
            ->where('active', true)
            ->orderByDesc('id')
            ->first();

        return $integration?->isConnected() ? $integration : null;
    }

    public function canOfferOnlineBookingPayment(float|int|null $amount = null): bool
    {
        if ($this->bookingPaymentDisabled()) {
            return false;
        }

        if (! $this->activeMercadoPagoIntegration()) {
            return false;
        }

        if ($amount !== null) {
            $amount = round(max(0, (float) $amount), 2);

            if ($amount <= 0) {
                return false;
            }
        }

        return match ((string) $this->booking_payment_mode) {
            'full' => $amount === null ? true : $amount > 0,
            'deposit' => match ((string) ($this->booking_deposit_type ?: 'fixed')) {
                'percentage' => (float) ($this->booking_deposit_value ?: 0) > 0,
                default => (float) ($this->booking_deposit_value ?: 0) > 0,
            },
            default => false,
        };
    }

    public function shouldRequireOnlineBookingPayment(float|int|null $amount = null): bool
    {
        if (! $this->bookingPaymentRequired()) {
            return false;
        }

        return $this->canOfferOnlineBookingPayment($amount);
    }

    /**
     * Normalize display text and guard against leaked DOM object strings.
     */
    public function safeDisplayText(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $text = trim((string) $value);

        if (
            $text === ''
            || preg_match('/^\[object\s+HTML[\w-]*Element\]$/i', $text) === 1
            || preg_match('/^\[object\s+[\w-]+\]$/i', $text) === 1
        ) {
            return null;
        }

        return $text;
    }
}
