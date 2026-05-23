<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id',
        'product_id',
        'expected_quantity',
        'counted_quantity',
        'difference_quantity',
        'unit_cost',
        'difference_value',
        'adjustment_movement_id',
        'adjusted_at',
        'adjusted_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
            'difference_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'difference_value' => 'decimal:2',
            'adjusted_at' => 'datetime',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function adjustmentMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'adjustment_movement_id');
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
