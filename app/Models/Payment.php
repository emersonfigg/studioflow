<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const PAYMENT_METHODS = [
        'cash',
        'pix',
        'card',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'appointment_id',
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
}
