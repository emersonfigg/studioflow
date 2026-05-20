<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\ProfessionalDayOverrideInterval;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AvailabilityService
{
    private const SLOT_INTERVAL_MINUTES = 5;

    private const DEFAULT_MIN_LEAD_TIME_MINUTES = 10;

    private const DEFAULT_TIMEZONE = 'America/Bahia';

    private const SLOT_TOLERANCE_MINUTES = 5;

    private const MIN_FILLABLE_GAP_MINUTES = 30;

    /**
     * Get available start times for a professional, service and date.
     *
     * @return list<string>
     */
    public function availableSlots(
        Company $company,
        User $user,
        Service $service,
        CarbonInterface|string $date,
        bool $applyLeadTime = true,
        ?int $ignoreAppointmentId = null,
        ?int $clientId = null,
    ): array {
        if ($user->company_id !== $company->id || $service->company_id !== $company->id) {
            return [];
        }

        return $this->availableSlotsForDuration(
            $company,
            $user,
            (int) $service->duration_minutes,
            $date,
            $applyLeadTime,
            $ignoreAppointmentId,
            $clientId,
        );
    }

    /**
     * Get available start times for a professional and total duration on a date.
     *
     * @return list<string>
     */
    public function availableSlotsForDuration(
        Company $company,
        User $user,
        int $durationMinutes,
        CarbonInterface|string $date,
        bool $applyLeadTime = true,
        ?int $ignoreAppointmentId = null,
        ?int $clientId = null,
    ): array {
        return collect($this->slotOptionsForDuration(
            $company,
            $user,
            $durationMinutes,
            $date,
            $applyLeadTime,
            $ignoreAppointmentId,
            $clientId,
        ))
            ->where('available', true)
            ->sortBy(fn (array $slot): string => (string) $slot['datetime'])
            ->pluck('time')
            ->values()
            ->all();
    }

    /**
     * Get slot options for a professional and total duration on a date, including unavailable slots.
     *
     * @return list<array{time: string, datetime: string, available: bool, reason: ?string, score: int, classification: string, internal_label: ?string, public_label: ?string}>
     */
    public function slotOptionsForDuration(
        Company $company,
        User $user,
        int $durationMinutes,
        CarbonInterface|string $date,
        bool $applyLeadTime = true,
        ?int $ignoreAppointmentId = null,
        ?int $clientId = null,
    ): array {
        if ($user->company_id !== $company->id || $durationMinutes < 1) {
            return [];
        }

        $timezone = $this->timezone();
        $day = $this->normalizeDay($date, $timezone);
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if ($day->startOfDay()->lt($today)) {
            return [];
        }

        $intervals = $this->workingIntervalsForDate($company, $user, $day);

        if ($intervals->isEmpty()) {
            return [];
        }

        $overallStart = $intervals->min(fn (array $interval): CarbonImmutable => $interval['start']);
        $overallEnd = $intervals->max(fn (array $interval): CarbonImmutable => $interval['end']);
        $conflictWindowEnd = $overallEnd->addMinutes($durationMinutes);

        $appointments = Appointment::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->when($ignoreAppointmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->where('start_time', '<', $conflictWindowEnd)
            ->where('end_time', '>', $overallStart)
            ->get(['start_time', 'end_time'])
            ->map(fn (Appointment $appointment): array => $this->appointmentInterval($appointment));

        $clientAppointments = $clientId
            ? Appointment::clientScheduleConflictQuery(
                $company->id,
                $clientId,
                $overallStart,
                $conflictWindowEnd,
                $ignoreAppointmentId
            )->get(['id', 'start_time', 'end_time'])
            : collect();

        $safeEarliestTime = null;

        if ($day->isSameDay($today)) {
            $safeNow = CarbonImmutable::now($timezone);

            if ($applyLeadTime) {
                $safeNow = $safeNow->addMinutes($this->minimumLeadTimeMinutes());
            }

            $safeEarliestTime = $this->roundUpToSlot($safeNow);
        }

        $slotOptions = [];

        foreach ($intervals as $interval) {
            $intervalStart = $this->roundUpToSlot($interval['start']);
            $intervalEnd = $interval['end'];
            $latestStartTime = $this->roundUpToSlot($intervalEnd);

            if ($latestStartTime->lt($intervalStart)) {
                continue;
            }

            $slotStart = $intervalStart;

            while ($slotStart->lte($latestStartTime)) {
                $slotEnd = $slotStart->addMinutes($durationMinutes);
                $slotTime = $slotStart->format('H:i');

                $hasConflict = $appointments->contains(
                    fn (array $appointment): bool => $this->intervalsOverlap($slotStart, $slotEnd, $appointment['start'], $appointment['end'])
                );

                $hasClientConflict = $clientAppointments->contains(
                    fn (Appointment $appointment): bool => $slotStart->lt($appointment->end_time)
                        && $slotEnd->gt($appointment->start_time)
                );

                if ($safeEarliestTime && $slotStart->lt($safeEarliestTime)) {
                    $slotStart = $slotStart->addMinutes(self::SLOT_INTERVAL_MINUTES);

                    continue;
                }

                if ($hasClientConflict) {
                    $slotStart = $slotStart->addMinutes(self::SLOT_INTERVAL_MINUTES);

                    continue;
                }

                $available = ! $hasConflict;
                $reason = $hasConflict ? 'reserved' : null;

                $existing = $slotOptions[$slotTime] ?? null;

                if (! $existing || ($available && ! $existing['available'])) {
                    $slotOptions[$slotTime] = [
                        'time' => $slotTime,
                        'datetime' => $slotStart->format('Y-m-d H:i:s'),
                        'available' => $available,
                        'reason' => $reason,
                    ];
                }

                $slotStart = $slotStart->addMinutes(self::SLOT_INTERVAL_MINUTES);
            }
        }

        return $this->sortSmartSlots(
            $this->calculateAvailableSlots(array_values($slotOptions), $intervals, $appointments, $durationMinutes)
        );
    }

    /**
     * Add smart scheduling metadata to slot options without changing availability rules.
     *
     * @param  list<array{time: string, datetime: string, available: bool, reason: ?string}>  $slotOptions
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $intervals
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $appointments
     * @return list<array{time: string, datetime: string, available: bool, reason: ?string, score: int, classification: string, internal_label: ?string, public_label: ?string}>
     */
    public function calculateAvailableSlots(array $slotOptions, Collection $intervals, Collection $appointments, int $durationMinutes): array
    {
        return collect($slotOptions)
            ->map(function (array $slot) use ($intervals, $appointments, $durationMinutes): array {
                if (! ($slot['available'] ?? false)) {
                    return [
                        ...$slot,
                        'score' => 0,
                        'classification' => 'unavailable',
                        'internal_label' => null,
                        'public_label' => null,
                    ];
                }

                $slotStart = CarbonImmutable::parse($slot['datetime'], $this->timezone());
                $slotEnd = $slotStart->addMinutes($durationMinutes);
                $score = $this->scoreSlotEfficiency($slotStart, $slotEnd, $intervals, $appointments, $durationMinutes);
                $classification = $this->classifySlot($score);

                return [
                    ...$slot,
                    'score' => $score,
                    'classification' => $classification,
                    'internal_label' => $this->internalSlotLabel($classification),
                    'public_label' => $this->publicSlotLabel($classification),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Score how efficiently a slot uses the free space around existing appointments.
     */
    public function scoreSlotEfficiency(
        CarbonImmutable $slotStart,
        CarbonImmutable $slotEnd,
        Collection $intervals,
        Collection $appointments,
        int $durationMinutes,
    ): int {
        $bounds = $this->neighborBounds($slotStart, $slotEnd, $intervals, $appointments);

        if (! $bounds) {
            return 50;
        }

        $gapBefore = max(0, $bounds['previous']->diffInMinutes($slotStart));
        $gapAfter = max(0, $slotEnd->diffInMinutes($bounds['next']));
        $freeWindow = $bounds['previous']->diffInMinutes($bounds['next']);
        $minimumFillableGap = min(self::MIN_FILLABLE_GAP_MINUTES, max(self::SLOT_INTERVAL_MINUTES, $durationMinutes));
        $score = 50;

        if ($freeWindow === $durationMinutes) {
            $score += 42;
        }

        if ($gapBefore <= self::SLOT_TOLERANCE_MINUTES) {
            $score += 22;
        }

        if ($gapAfter <= self::SLOT_TOLERANCE_MINUTES) {
            $score += 22;
        }

        if ($gapBefore > 0 && $gapBefore < $minimumFillableGap) {
            $score -= 24;
        }

        if ($gapAfter > 0 && $gapAfter < $minimumFillableGap) {
            $score -= 32;
        }

        if ($gapBefore >= $minimumFillableGap && $gapAfter >= $minimumFillableGap) {
            $score -= 6;
        }

        if ($gapBefore === 0 || $gapAfter === 0) {
            $score += 8;
        }

        return (int) max(0, min(100, $score));
    }

    public function classifySlot(int $score): string
    {
        return match (true) {
            $score >= 85 => 'best_fit',
            $score >= 68 => 'good_fit',
            $score < 38 => 'inefficient_gap',
            default => 'normal',
        };
    }

    /**
     * Sort slots chronologically. Score is metadata only and must not affect public ordering.
     *
     * @param  list<array{time: string, datetime: string, available: bool, reason: ?string, score: int, classification: string, internal_label: ?string, public_label: ?string}>  $slotOptions
     * @return list<array{time: string, datetime: string, available: bool, reason: ?string, score: int, classification: string, internal_label: ?string, public_label: ?string}>
     */
    public function sortSmartSlots(array $slotOptions): array
    {
        usort($slotOptions, function (array $a, array $b): int {
            if (($a['available'] ?? false) !== ($b['available'] ?? false)) {
                return ($a['available'] ?? false) ? -1 : 1;
            }

            return strcmp((string) $a['datetime'], (string) $b['datetime']);
        });

        return array_values($slotOptions);
    }

    private function internalSlotLabel(string $classification): ?string
    {
        return match ($classification) {
            'best_fit' => 'Melhor encaixe',
            'good_fit' => 'Bom encaixe',
            'inefficient_gap' => 'Pode gerar intervalo ocioso',
            default => null,
        };
    }

    private function publicSlotLabel(string $classification): ?string
    {
        return match ($classification) {
            'best_fit' => 'Recomendado',
            default => null,
        };
    }

    /**
     * Normalize an appointment interval before slot validation.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function appointmentInterval(Appointment $appointment): array
    {
        return [
            'start' => CarbonImmutable::instance($appointment->start_time)->setTimezone($this->timezone()),
            'end' => CarbonImmutable::instance($appointment->end_time)->setTimezone($this->timezone()),
        ];
    }

    /**
     * True when two intervals overlap. Adjacent intervals are allowed.
     */
    private function intervalsOverlap(
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $existingStart,
        CarbonImmutable $existingEnd,
    ): bool {
        return $start->lt($existingEnd) && $end->gt($existingStart);
    }

    /**
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $intervals
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $appointments
     * @return array{previous: CarbonImmutable, next: CarbonImmutable}|null
     */
    private function neighborBounds(CarbonImmutable $slotStart, CarbonImmutable $slotEnd, Collection $intervals, Collection $appointments): ?array
    {
        $interval = $intervals->first(
            fn (array $interval): bool => $interval['start']->lte($slotStart) && $interval['end']->gte($slotEnd)
        );

        if (! $interval) {
            return null;
        }

        $previousAppointment = $appointments
            ->filter(fn (array $appointment): bool => $appointment['end']->lte($slotStart))
            ->sortByDesc(fn (array $appointment): string => $appointment['end']->toIso8601String())
            ->first();

        $nextAppointment = $appointments
            ->filter(fn (array $appointment): bool => $appointment['start']->gte($slotEnd))
            ->sortBy(fn (array $appointment): string => $appointment['start']->toIso8601String())
            ->first();

        $previous = $previousAppointment
            ? $previousAppointment['end']
            : $interval['start'];
        $next = $nextAppointment
            ? $nextAppointment['start']
            : $interval['end'];

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }

    /**
     * Resolve the professional working intervals for a given day.
     *
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function workingIntervalsForDate(Company $company, User $user, CarbonImmutable $day): Collection
    {
        $override = $user->dayOverrides()
            ->with('intervals')
            ->where('company_id', $company->id)
            ->whereDate('date', $day->toDateString())
            ->latest('id')
            ->first();

        if ($override?->is_day_off) {
            return collect();
        }

        if ($override) {
            $overrideIntervals = $override->intervals
                ->map(fn (ProfessionalDayOverrideInterval $interval): ?array => $this->intervalForTimes(
                    $day,
                    (string) $interval->start_time,
                    (string) $interval->end_time
                ))
                ->filter()
                ->values();

            if ($overrideIntervals->isNotEmpty()) {
                return $overrideIntervals;
            }

            if ($override->start_time && $override->end_time) {
                return collect([$this->intervalForTimes($day, (string) $override->start_time, (string) $override->end_time)])
                    ->filter();
            }
        }

        if (($user->schedule_type ?? 'fixed') === 'dynamic') {
            return collect();
        }

        return $user->workingHours()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where('weekday', $day->dayOfWeek)
            ->orderBy('start_time')
            ->get()
            ->map(fn (ProfessionalWorkingHour $workingHour): ?array => $this->intervalForTimes(
                $day,
                (string) $workingHour->start_time,
                (string) $workingHour->end_time
            ))
            ->filter()
            ->values();
    }

    /**
     * Build a working interval for a given day and time pair.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function intervalForTimes(CarbonImmutable $day, string $startTime, string $endTime): ?array
    {
        $start = $day->setTimeFromTimeString(substr($startTime, 0, 5));
        $end = $day->setTimeFromTimeString(substr($endTime, 0, 5));

        if ($end->lte($start)) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Round a candidate time up to the next slot boundary.
     */
    private function roundUpToSlot(CarbonImmutable $time): CarbonImmutable
    {
        $rounded = $time->setSecond(0)->setMicrosecond(0);
        $remainder = $rounded->minute % self::SLOT_INTERVAL_MINUTES;

        if ($remainder === 0) {
            return $rounded;
        }

        return $rounded->addMinutes(self::SLOT_INTERVAL_MINUTES - $remainder);
    }

    /**
     * Normalize a date input to the application timezone.
     */
    private function normalizeDay(CarbonInterface|string $date, string $timezone): CarbonImmutable
    {
        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::instance($date)->setTimezone($timezone);
        }

        return CarbonImmutable::parse($date, $timezone)->startOfDay();
    }

    /**
     * Resolve the timezone used for availability calculations.
     */
    private function timezone(): string
    {
        return (string) config('app.timezone', self::DEFAULT_TIMEZONE);
    }

    private function minimumLeadTimeMinutes(): int
    {
        return max(0, (int) config('studioflow.booking_min_lead_time_minutes', self::DEFAULT_MIN_LEAD_TIME_MINUTES));
    }
}
