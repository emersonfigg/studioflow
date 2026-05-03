<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public const CLIENT_CONFLICT_ACTIVE_STATUSES = [
        'scheduled',
        'confirmed',
        'in_progress',
        'pending',
        'agendado',
        'confirmado',
        'em_atendimento',
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
     * Get all services attached to the appointment.
     *
     * @return BelongsToMany<Service>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'appointment_services')
            ->withPivot(['price_snapshot', 'duration_snapshot', 'order'])
            ->orderBy('appointment_services.order');
    }

    /**
     * Get the payment recorded for the appointment.
     *
     * @return HasOne<Payment>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function serviceOrder(): HasOne
    {
        return $this->hasOne(ServiceOrder::class);
    }

    /**
     * Get product sales registered during this appointment conclusion.
     *
     * @return HasMany<ProductSale>
     */
    public function productSales(): HasMany
    {
        return $this->hasMany(ProductSale::class);
    }

    /**
     * Get the translated label for the appointment status.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'scheduled' => 'Agendado',
            'confirmed' => 'Confirmado',
            'in_progress' => 'Em atendimento',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
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

    /**
     * Query active appointments for the same client that overlap a candidate interval.
     *
     * @return Builder<Appointment>
     */
    public static function clientScheduleConflictQuery(
        int $companyId,
        int $clientId,
        CarbonInterface|string $startTime,
        CarbonInterface|string $endTime,
        ?int $ignoreAppointmentId = null,
    ): Builder {
        return self::query()
            ->with(['service', 'services'])
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereIn('status', self::CLIENT_CONFLICT_ACTIVE_STATUSES)
            ->when($ignoreAppointmentId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreAppointmentId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }

    public static function findClientScheduleConflict(
        int $companyId,
        int $clientId,
        CarbonInterface|string $startTime,
        CarbonInterface|string $endTime,
        ?int $ignoreAppointmentId = null,
    ): ?self {
        return self::clientScheduleConflictQuery($companyId, $clientId, $startTime, $endTime, $ignoreAppointmentId)
            ->orderBy('start_time')
            ->first();
    }

    /**
     * Get the service line items for the appointment.
     *
     * @return Collection<int, Service>
     */
    public function bookedServices(): Collection
    {
        $services = $this->relationLoaded('services')
            ? $this->services
            : $this->services()->get();

        if ($services->isNotEmpty()) {
            return $services;
        }

        $primaryService = $this->relationLoaded('service')
            ? $this->service
            : $this->service()->first();

        return $primaryService ? new Collection([$primaryService]) : new Collection;
    }

    /**
     * Get the total duration for the appointment in minutes.
     */
    public function totalDurationMinutes(): int
    {
        $services = $this->bookedServices();

        if ($services->isEmpty()) {
            return 0;
        }

        return (int) $services->sum(
            fn (Service $service): int => (int) ($service->pivot->duration_snapshot ?? $service->duration_minutes)
        );
    }

    /**
     * Get the total price amount for the appointment.
     */
    public function totalPriceAmount(): float
    {
        $services = $this->bookedServices();

        if ($services->isEmpty()) {
            return 0;
        }

        return (float) $services->sum(
            fn (Service $service): float => (float) ($service->pivot->price_snapshot ?? $service->price)
        );
    }
}
