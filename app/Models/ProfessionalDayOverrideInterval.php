<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalDayOverrideInterval extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'professional_day_override_id',
        'start_time',
        'end_time',
    ];

    /**
     * Get the override that owns the interval.
     *
     * @return BelongsTo<ProfessionalDayOverride, ProfessionalDayOverrideInterval>
     */
    public function override(): BelongsTo
    {
        return $this->belongsTo(ProfessionalDayOverride::class, 'professional_day_override_id');
    }
}
