<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    public const STATUSES = [
        'scheduled',
        'confirmed',
        'in_progress',
        'completed',
        'cancelled',
    ];

    public const SOURCES = [
        'internal',
        'public_booking',
        'whatsapp',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'client_id',
        'user_id',
        'service_id',
        'start_time',
        'end_time',
        'status',
        'source',
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
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    /**
     * Get the company that owns the appointment.
     *
     * @return BelongsTo<Company, Appointment>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the client for the appointment.
     *
     * @return BelongsTo<Client, Appointment>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the staff user for the appointment.
     *
     * @return BelongsTo<User, Appointment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the service for the appointment.
     *
     * @return BelongsTo<Service, Appointment>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the translated label for the appointment status.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'scheduled' => __('Scheduled'),
            'confirmed' => __('Confirmed'),
            'in_progress' => __('In Progress'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            default => $this->status,
        };
    }

    /**
     * Get the badge classes for the appointment status.
     */
    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'scheduled' => 'bg-sky-100 text-sky-700 ring-sky-600/20',
            'confirmed' => 'bg-indigo-100 text-indigo-700 ring-indigo-600/20',
            'in_progress' => 'bg-amber-100 text-amber-700 ring-amber-600/20',
            'completed' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20',
            'cancelled' => 'bg-rose-100 text-rose-700 ring-rose-600/20',
            default => 'bg-gray-100 text-gray-700 ring-gray-600/20',
        };
    }
}
