<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBlock extends Model
{
    public const TYPE_NO_SHOW = 'no_show';

    public const TYPE_MANUAL = 'manual';

    public const TYPE_ABUSE = 'abuse';

    public const TYPES = [
        self::TYPE_NO_SHOW,
        self::TYPE_MANUAL,
        self::TYPE_ABUSE,
    ];

    protected $fillable = [
        'company_id',
        'client_id',
        'type',
        'reason',
        'starts_at',
        'ends_at',
        'active',
        'created_by',
        'removed_by',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
            'removed_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }
}
