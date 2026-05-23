<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyStockCheckItem extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_DIVERGENT = 'divergent';

    protected $fillable = [
        'daily_stock_check_id',
        'product_id',
        'sold_quantity',
        'sale_stock_quantity',
        'other_output_quantity',
        'input_quantity',
        'adjustment_quantity',
        'expected_quantity',
        'counted_quantity',
        'difference_quantity',
        'unit_cost',
        'difference_value',
        'status',
        'adjustment_movement_id',
        'adjusted_at',
        'adjusted_by',
    ];

    protected function casts(): array
    {
        return [
            'sold_quantity' => 'decimal:2',
            'sale_stock_quantity' => 'decimal:2',
            'other_output_quantity' => 'decimal:2',
            'input_quantity' => 'decimal:2',
            'adjustment_quantity' => 'decimal:2',
            'expected_quantity' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
            'difference_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'difference_value' => 'decimal:2',
            'adjusted_at' => 'datetime',
        ];
    }

    public function dailyStockCheck(): BelongsTo
    {
        return $this->belongsTo(DailyStockCheck::class);
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
