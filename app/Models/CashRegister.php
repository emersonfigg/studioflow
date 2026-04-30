<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'date',
        'opening_amount',
        'opened_by',
        'opened_at',
        'closing_amount',
        'closed_by',
        'closed_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'opening_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closing_amount' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, CashRegister>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, CashRegister>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, CashRegister>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<CashMovement>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * Sum inflows for the register.
     */
    public function inflowsTotal(): float
    {
        return round((float) $this->movements
            ->where('type', CashMovement::TYPE_INFLOW)
            ->sum('amount'), 2);
    }

    /**
     * Sum outflows for the register.
     */
    public function outflowsTotal(): float
    {
        return round((float) $this->movements
            ->where('type', CashMovement::TYPE_OUTFLOW)
            ->sum('amount'), 2);
    }

    /**
     * Calculate expected balance for the register.
     */
    public function expectedBalance(): float
    {
        return round((float) $this->opening_amount + $this->inflowsTotal() - $this->outflowsTotal(), 2);
    }
}
