<?php

namespace App\Models;

use Database\Factories\ClientCommercialHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCommercialHistory extends Model
{
    /** @use HasFactory<ClientCommercialHistoryFactory> */
    use HasFactory;

    public const ITEM_TYPE_PRODUCT = 'product';
    public const ITEM_TYPE_SERVICE = 'service';

    public const SOURCE_PDV = 'pdv';
    public const SOURCE_APPOINTMENT = 'appointment';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SYSTEM = 'system';

    public const ITEM_TYPES = [
        self::ITEM_TYPE_PRODUCT,
        self::ITEM_TYPE_SERVICE,
    ];

    public const SOURCES = [
        self::SOURCE_PDV,
        self::SOURCE_APPOINTMENT,
        self::SOURCE_MANUAL,
        self::SOURCE_SYSTEM,
    ];

    protected $fillable = [
        'company_id',
        'client_id',
        'item_type',
        'item_id',
        'item_name_snapshot',
        'quantity',
        'unit_price_snapshot',
        'total_amount_snapshot',
        'professional_id',
        'sale_id',
        'sale_item_id',
        'appointment_id',
        'occurred_at',
        'recommendation_days',
        'next_recommendation_date',
        'source',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'total_amount_snapshot' => 'decimal:2',
            'occurred_at' => 'datetime',
            'recommendation_days' => 'integer',
            'next_recommendation_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<Product, ClientCommercialHistory>|null
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'item_id');
    }

    /**
     * @return BelongsTo<Service, ClientCommercialHistory>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'item_id');
    }

    public function isCanceled(): bool
    {
        $metadata = $this->metadata;

        if (! is_array($metadata)) {
            return false;
        }

        $status = $metadata['status'] ?? null;

        return $status === 'canceled' || $status === 'cancelled';
    }
}
