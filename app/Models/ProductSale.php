<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSale extends Model
{
    use HasFactory, SoftDeletes;

    public const PAYMENT_METHODS = Payment::PAYMENT_METHODS;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'client_id',
        'appointment_id',
        'service_order_id',
        'status',
        'user_id',
        'gross_amount',
        'payment_method',
        'sold_at',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'sold_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * @return BelongsTo<Company, ProductSale>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Client, ProductSale>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Appointment, ProductSale>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * @return BelongsTo<User, ProductSale>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<ProductSaleItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductSaleItem::class);
    }

    /**
     * Get cash movements generated from this sale.
     *
     * @return HasMany<CashMovement>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'source_id')
            ->where('source_type', self::class);
    }
}
