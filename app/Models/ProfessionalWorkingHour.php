<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalWorkingHour extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'weekday',
        'start_time',
        'end_time',
        'active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Get the company that owns the working hour.
     *
     * @return BelongsTo<Company, ProfessionalWorkingHour>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the professional that owns the working hour.
     *
     * @return BelongsTo<User, ProfessionalWorkingHour>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
