<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_OVERDUE,
        self::STATUS_CANCELED,
    ];

    public const RECURRENCE_NONE = 'none';
    public const RECURRENCE_MONTHLY = 'monthly';

    public const RECURRENCES = [
        self::RECURRENCE_NONE,
        self::RECURRENCE_MONTHLY,
    ];

    protected $fillable = [
        'company_id',
        'expense_category_id',
        'description',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'recurrence',
        'payment_method',
        'notes',
        'created_by',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_PAID => 'Paga',
            self::STATUS_OVERDUE => 'Atrasada',
            self::STATUS_CANCELED => 'Cancelada',
            default => filled($status) ? (string) $status : '',
        };
    }
}
