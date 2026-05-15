<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentReview extends Model
{
    protected $fillable = [
        'company_id',
        'appointment_id',
        'client_id',
        'professional_id',
        'rating',
        'comment',
        'service_quality_rating',
        'punctuality_rating',
        'environment_rating',
        'private_feedback',
        'token',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'private_feedback' => 'boolean',
            'submitted_at' => 'datetime',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function isPending(): bool
    {
        return $this->submitted_at === null;
    }
}
