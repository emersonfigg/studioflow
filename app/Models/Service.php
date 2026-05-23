<?php

namespace App\Models;

use App\Support\MediaStorage;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    public const PRICE_MODE_FIXED = 'fixed';

    public const PRICE_MODE_FROM = 'from';

    public const PRICE_MODES = [
        self::PRICE_MODE_FIXED,
        self::PRICE_MODE_FROM,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'duration_minutes',
        'price',
        'price_mode',
        'allow_pdv_price_edit',
        'active',
        'image_path',
        'recommended_return_days',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'allow_pdv_price_edit' => 'boolean',
            'active' => 'boolean',
            'recommended_return_days' => 'integer',
        ];
    }

    /**
     * Get the company that owns the service.
     *
     * @return BelongsTo<Company, Service>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the appointments for the service.
     *
     * @return HasMany<Appointment>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the appointment items for the service.
     *
     * @return BelongsToMany<Appointment>
     */
    public function bookedAppointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_services')
            ->withPivot(['price_snapshot', 'duration_snapshot', 'order']);
    }

    /**
     * Get the payments for the service.
     *
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function serviceOrderItems(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    /**
     * Products consumed when this service is performed.
     *
     * @return HasMany<ServiceProductConsumption>
     */
    public function productConsumptions(): HasMany
    {
        return $this->hasMany(ServiceProductConsumption::class);
    }

    /**
     * Get the public URL for the service image.
     */
    public function getImageUrlAttribute(): ?string
    {
        $imagePath = $this->normalizedImagePath();

        return MediaStorage::url($imagePath);
    }

    /**
     * Normalize stored image paths to a public-disk relative path.
     */
    public function normalizedImagePath(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return MediaStorage::normalizePath($this->image_path);
    }

    public function allowsPdvPriceEdit(): bool
    {
        return (bool) $this->allow_pdv_price_edit || $this->price_mode === self::PRICE_MODE_FROM;
    }

    public function publicPriceLabel(): string
    {
        $price = 'R$ '.number_format((float) $this->price, 2, ',', '.');

        return $this->price_mode === self::PRICE_MODE_FROM ? 'A partir de '.$price : $price;
    }
}
