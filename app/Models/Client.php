<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'birthday',
        'notes',
        'last_visit_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'last_visit_at' => 'datetime',
        ];
    }

    /**
     * Get the company that owns the client.
     *
     * @return BelongsTo<Company, Client>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
