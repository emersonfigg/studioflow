<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionSettlement extends Model
{
    use HasFactory;

    public const PAYMENT_METHODS = [
        'cash',
        'pix',
        'bank_transfer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'start_date',
        'end_date',
        'gross_amount',
        'commission_amount',
        'payment_method',
        'paid_at',
        'notes',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'gross_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the company that owns the settlement.
     *
     * @return BelongsTo<Company, CommissionSettlement>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the professional that received the settlement.
     *
     * @return BelongsTo<User, CommissionSettlement>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that created the settlement.
     *
     * @return BelongsTo<User, CommissionSettlement>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the payments linked to the settlement.
     *
     * @return BelongsToMany<Payment>
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'commission_settlement_payment');
    }

    /**
     * Get cash movements linked to the settlement.
     *
     * @return HasMany<CashMovement>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'source_id')
            ->where('source_type', self::class);
    }
}
