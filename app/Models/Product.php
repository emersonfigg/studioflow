<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const COMMISSION_TYPE_FIXED = 'fixed';

    public const COMMISSION_TYPE_PERCENTAGE = 'percentage';

    /**
     * Allowed commission type values.
     *
     * @var list<string>
     */
    public const COMMISSION_TYPES = [
        self::COMMISSION_TYPE_FIXED,
        self::COMMISSION_TYPE_PERCENTAGE,
    ];

    protected $fillable = [
        'company_id',
        'name',
        'sku',
        'description',
        'image_path',
        'price',
        'stock_quantity',
        'minimum_stock',
        'cost_price',
        'unit',
        'track_stock',
        'low_stock_alert',
        'active',
        'commission_type',
        'commission_value',
        'recommended_repurchase_days',
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
            'stock_quantity' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'track_stock' => 'boolean',
            'low_stock_alert' => 'boolean',
            'active' => 'boolean',
            'commission_value' => 'decimal:2',
            'recommended_repurchase_days' => 'integer',
        ];
    }

    public function tracksStock(): bool
    {
        return (bool) $this->track_stock;
    }

    public function isLowStock(): bool
    {
        if (! $this->low_stock_alert || ! $this->tracksStock()) {
            return false;
        }

        if ($this->minimum_stock === null) {
            return false;
        }

        return (float) $this->stock_quantity <= (float) $this->minimum_stock + 1e-6;
    }

    /**
     * Determine whether the product is configured to generate commissions for sellers.
     */
    public function hasCommission(): bool
    {
        if (! in_array($this->commission_type, self::COMMISSION_TYPES, true)) {
            return false;
        }

        return $this->commission_value !== null && (float) $this->commission_value > 0;
    }

    /**
     * Get the company that owns the product.
     *
     * @return BelongsTo<Company, Product>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get product sale items linked to the product.
     *
     * @return HasMany<ProductSaleItem>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(ProductSaleItem::class);
    }

    public function serviceOrderItems(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    /**
     * @return HasMany<StockMovement>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('occurred_at');
    }

    /**
     * Get the public URL for the product image.
     */
    public function getImageUrlAttribute(): ?string
    {
        $imagePath = $this->normalizedImagePath();

        return MediaStorage::url($imagePath);
    }

    /**
     * Normalize stored image path to a public-disk relative path.
     */
    public function normalizedImagePath(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return MediaStorage::normalizePath($this->image_path);
    }
}
