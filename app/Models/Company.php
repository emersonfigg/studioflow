<?php

namespace App\Models;

use App\Support\MediaStorage;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'phone',
        'address',
        'cnpj',
        'instagram',
        'description',
        'logo',
        'active',
        'onboarding_completed_at',
        'auto_print_receipt',
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
            'auto_print_receipt' => 'boolean',
            'client_code_counter' => 'integer',
            'onboarding_completed_at' => 'datetime',
        ];
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

    /**
     * Determine if the company finished the first setup.
     */
    public function onboardingCompleted(): bool
    {
        return $this->onboarding_completed_at !== null;
    }
}
