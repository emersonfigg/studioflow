<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AvailabilityService
{
    private const SLOT_INTERVAL_MINUTES = 30;

    private const MIN_LEAD_TIME_MINUTES = 30;

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
    ): array {
        if ($user->company_id !== $company->id || $service->company_id !== $company->id) {
            return [];
        }

        return $this->availableSlotsForDuration(
            $company,
            $user,
            (int) $service->duration_minutes,
            $date,
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
    ): array {
        if ($user->company_id !== $company->id || $durationMinutes < 1) {
            return [];
        }

        $day = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date);

        $today = CarbonImmutable::now($day->getTimezone())->startOfDay();

        if ($day->startOfDay()->lt($today)) {
            return [];
        }

        $intervals = $this->workingIntervalsForDate($company, $user, $day);

        if ($intervals->isEmpty()) {
            return [];
        }

        $overallStart = $intervals->min(fn (array $interval): CarbonImmutable => $interval['start']);
        $overallEnd = $intervals->max(fn (array $interval): CarbonImmutable => $interval['end']);

        $appointments = Appointment::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $overallEnd)
            ->where('end_time', '>', $overallStart)
            ->get(['start_time', 'end_time']);

        $safeEarliestTime = null;

        if ($day->isSameDay($today)) {
            $safeNow = CarbonImmutable::now($day->getTimezone())->addMinutes(self::MIN_LEAD_TIME_MINUTES);
            $safeEarliestTime = $this->roundUpToSlot($safeNow);
        }

        $slots = [];

        foreach ($intervals as $interval) {
            $intervalStart = $this->roundUpToSlot($interval['start']);
            $intervalEnd = $interval['end'];
            $latestStartTime = $intervalEnd->subMinutes($durationMinutes);

            if ($latestStartTime->lt($intervalStart)) {
                continue;
            }

            $earliestStartTime = $safeEarliestTime
                ? ($safeEarliestTime->gt($intervalStart) ? $safeEarliestTime : $intervalStart)
                : $intervalStart;

            if ($earliestStartTime->gt($latestStartTime)) {
                continue;
            }

            for ($slotStart = $earliestStartTime; $slotStart->lte($latestStartTime); $slotStart = $slotStart->addMinutes(self::SLOT_INTERVAL_MINUTES)) {
                $slotEnd = $slotStart->addMinutes($durationMinutes);

                if ($slotEnd->gt($intervalEnd)) {
                    continue;
                }

                $hasConflict = $appointments->contains(
                    fn (Appointment $appointment): bool => $slotStart->lt($appointment->end_time)
                        && $slotEnd->gt($appointment->start_time)
                );

                if (! $hasConflict) {
                    $slots[] = $slotStart->format('H:i');
                }
            }
        }

        return array_values(array_unique($slots));
    }

    /**
     * Resolve the professional working intervals for a given day.
     *
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function workingIntervalsForDate(Company $company, User $user, CarbonImmutable $day): Collection
    {
        $override = $user->dayOverrides()
            ->where('company_id', $company->id)
            ->whereDate('date', $day->toDateString())
            ->latest('id')
            ->first();

        if ($override?->is_day_off) {
            return collect();
        }

        if ($override && $override->start_time && $override->end_time) {
            return collect([$this->intervalForTimes($day, (string) $override->start_time, (string) $override->end_time)])
                ->filter();
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
        $rounded = $time->setSecond(0);
        $remainder = $rounded->minute % self::SLOT_INTERVAL_MINUTES;

        if ($remainder === 0) {
            return $rounded;
        }

        return $rounded->addMinutes(self::SLOT_INTERVAL_MINUTES - $remainder);
    }
}
