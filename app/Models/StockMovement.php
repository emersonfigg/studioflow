<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_SALE = 'sale';

    public const TYPE_SERVICE_CONSUMPTION = 'service_consumption';

    public const TYPE_INITIAL_BALANCE = 'initial_balance';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SALE_REVERSAL = 'sale_reversal';

    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const TYPE_BLIND_COUNT_ADJUSTMENT = 'blind_count_adjustment';

    public const TYPE_AUDIT_ADJUSTMENT = 'audit_adjustment';

    public const TYPE_BLIND_COUNT_ADJUSTMENT_APPLIED = 'blind_count_adjustment_applied';

    public const TYPE_LOSS = 'loss';

    public const TYPE_INTERNAL_USE = 'internal_use';

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_IN,
        self::TYPE_OUT,
        self::TYPE_ADJUSTMENT,
        self::TYPE_SALE,
        self::TYPE_SERVICE_CONSUMPTION,
        self::TYPE_INITIAL_BALANCE,
        self::TYPE_PURCHASE,
        self::TYPE_SALE_REVERSAL,
        self::TYPE_MANUAL_ADJUSTMENT,
        self::TYPE_BLIND_COUNT_ADJUSTMENT,
        self::TYPE_AUDIT_ADJUSTMENT,
        self::TYPE_BLIND_COUNT_ADJUSTMENT_APPLIED,
        self::TYPE_LOSS,
        self::TYPE_INTERNAL_USE,
    ];

    protected $fillable = [
        'company_id',
        'product_id',
        'user_id',
        'type',
        'direction',
        'quantity',
        'balance_before',
        'balance_after',
        'previous_quantity',
        'new_quantity',
        'unit_cost',
        'total_cost',
        'reason',
        'notes',
        'source_type',
        'source_id',
        'reference_type',
        'reference_id',
        'occurred_at',
        'movement_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'previous_quantity' => 'decimal:2',
            'new_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
            'movement_date' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, StockMovement>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Product, StockMovement>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, StockMovement>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
