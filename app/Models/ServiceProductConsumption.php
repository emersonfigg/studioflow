<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceProductConsumption extends Model
{
    protected $fillable = [
        'company_id',
        'service_id',
        'product_id',
        'quantity',
        'unit',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, ServiceProductConsumption>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Service, ServiceProductConsumption>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Product, ServiceProductConsumption>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
