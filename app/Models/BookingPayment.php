<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPayment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUNDED = 'refunded';

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_FULL = 'full';

    protected $fillable = [
        'company_id',
        'appointment_id',
        'gateway',
        'status',
        'payment_type',
        'amount',
        'external_reference',
        'preference_id',
        'external_payment_id',
        'checkout_url',
        'init_point',
        'sandbox_init_point',
        'paid_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Aguardando pagamento',
            self::STATUS_PAID => 'Pago',
            self::STATUS_FAILED => 'Falhou',
            self::STATUS_EXPIRED => 'Expirado',
            self::STATUS_REFUNDED => 'Estornado',
            default => ucfirst((string) $this->status),
        };
    }
}
