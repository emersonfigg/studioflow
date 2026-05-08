<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const PAYMENT_METHODS = [
        'cash',
        'pix',
        'card',
        'card_debit',
        'card_credit',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'appointment_id',
        'service_order_id',
        'user_id',
        'client_id',
        'service_id',
        'gross_amount',
        'payment_method',
        'commission_type',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'paid_at',
        'notes',
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
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the company that owns the payment.
     *
     * @return BelongsTo<Company, Payment>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the appointment that owns the payment.
     *
     * @return BelongsTo<Appointment, Payment>
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
     * Get the professional for the payment.
     *
     * @return BelongsTo<User, Payment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the client for the payment.
     *
     * @return BelongsTo<Client, Payment>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the service for the payment.
     *
     * @return BelongsTo<Service, Payment>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the commission settlements linked to the payment.
     *
     * @return BelongsToMany<CommissionSettlement>
     */
    public function commissionSettlements(): BelongsToMany
    {
        return $this->belongsToMany(CommissionSettlement::class, 'commission_settlement_payment');
    }

    /**
     * Get cash movements generated from the payment.
     *
     * @return HasMany<CashMovement>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'source_id')
            ->where('source_type', self::class);
    }

    /**
     * Determine the human label for the payment method.
     */
    public function paymentMethodLabel(): string
    {
        return self::labelForPaymentMethod((string) $this->payment_method);
    }

    public static function labelForPaymentMethod(string $method): string
    {
        return match ($method) {
            'cash' => 'Dinheiro',
            'pix' => 'Pix',
            'card' => 'Cartão',
            'card_debit' => 'Cartão débito',
            'card_credit' => 'Cartão crédito',
            default => ucfirst($method),
        };
    }

    /** @return array<string, string> value => pt-BR label */
    public static function paymentMethodOptions(): array
    {
        return collect(self::PAYMENT_METHODS)
            ->mapWithKeys(fn (string $m): array => [$m => self::labelForPaymentMethod($m)])
            ->all();
    }
}
