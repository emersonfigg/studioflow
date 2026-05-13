<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\MediaStorage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'role',
        'global_role',
        'commission_type',
        'commission_value',
        'active',
        'schedule_type',
        'photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'commission_value' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /**
     * Get the company that owns the user.
     *
     * @return BelongsTo<Company, User>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Determine if the user can manage company resources.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasFinancialPrivileges(): bool
    {
        return $this->isAdmin() || $this->role === 'financial';
    }

    /**
     * Determine if the user is a global super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->global_role === 'super_admin';
    }

    /**
     * Get the appointments assigned to the user.
     *
     * @return HasMany<Appointment>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the payments assigned to the user.
     *
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get product sales linked to the professional/user.
     *
     * @return HasMany<ProductSale>
     */
    public function productSales(): HasMany
    {
        return $this->hasMany(ProductSale::class);
    }

    /**
     * Get product sale items credited to this user as a seller (for commission).
     *
     * @return HasMany<ProductSaleItem>
     */
    public function productSaleItemsAsSeller(): HasMany
    {
        return $this->hasMany(ProductSaleItem::class, 'seller_id');
    }

    /**
     * Get the commission settlements received by the professional.
     *
     * @return HasMany<CommissionSettlement>
     */
    public function commissionSettlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class);
    }

    /**
     * Get the commission settlements created by the user.
     *
     * @return HasMany<CommissionSettlement>
     */
    public function createdCommissionSettlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class, 'created_by');
    }

    /**
     * Get cash registers opened by the user.
     *
     * @return HasMany<CashRegister>
     */
    public function openedCashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class, 'opened_by');
    }

    /**
     * Get cash registers closed by the user.
     *
     * @return HasMany<CashRegister>
     */
    public function closedCashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class, 'closed_by');
    }

    /**
     * Get the weekly working hours configured for the professional.
     *
     * @return HasMany<ProfessionalWorkingHour>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(ProfessionalWorkingHour::class)->orderBy('weekday')->orderBy('start_time');
    }

    /**
     * Get the specific day overrides configured for the professional.
     *
     * @return HasMany<ProfessionalDayOverride>
     */
    public function dayOverrides(): HasMany
    {
        return $this->hasMany(ProfessionalDayOverride::class)->orderByDesc('date');
    }

    /**
     * Get the public URL for the professional photo.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $photoPath = $this->normalizedPhotoPath();

        return MediaStorage::url($photoPath);
    }

    /**
     * Get the fallback initial for the professional avatar.
     */
    public function getAvatarInitialAttribute(): string
    {
        return Str::upper(Str::substr(trim($this->name), 0, 1) ?: 'P');
    }

    /**
     * Normalize stored photo paths to a public-disk relative path.
     */
    public function normalizedPhotoPath(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return MediaStorage::normalizePath($this->photo_path);
    }
}
