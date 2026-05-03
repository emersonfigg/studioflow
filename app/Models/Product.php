<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'sku',
        'description',
        'image_path',
        'price',
        'stock_quantity',
        'active',
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
            'stock_quantity' => 'integer',
            'active' => 'boolean',
        ];
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
     * Get the public URL for the product image.
     */
    public function getImageUrlAttribute(): ?string
    {
        $imagePath = $this->normalizedImagePath();

        if (! $imagePath || ! Storage::disk('public')->exists($imagePath)) {
            return null;
        }

        return Storage::url($imagePath);
    }

    /**
     * Normalize stored image path to a public-disk relative path.
     */
    public function normalizedImagePath(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return ltrim(Str::replaceFirst('storage/', '', str_replace('\\', '/', $this->image_path)), '/');
    }
}
