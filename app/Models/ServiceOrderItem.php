<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderItem extends Model
{
    use HasFactory;

    public const TYPE_SERVICE = 'service';

    public const TYPE_PRODUCT = 'product';

    public const TYPE_MEMBERSHIP = 'membership';

    protected $fillable = [
        'service_order_id',
        'type',
        'service_id',
        'product_id',
        'professional_id',
        'seller_id',
        'description',
        'quantity',
        'unit_price',
        'original_unit_price',
        'price_adjustment_amount',
        'price_adjustment_reason',
        'price_adjusted_by',
        'price_adjusted_at',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'original_unit_price' => 'decimal:2',
            'price_adjustment_amount' => 'decimal:2',
            'price_adjusted_at' => 'datetime',
            'total_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function priceAdjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_adjusted_by');
    }
}
