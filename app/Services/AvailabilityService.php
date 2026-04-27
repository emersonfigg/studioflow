<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AvailabilityService
{
    private const OPENING_TIME = '08:00';

    private const CLOSING_TIME = '18:00';

    private const SLOT_INTERVAL_MINUTES = 30;

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

        $day = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date);

        $openingTime = $day->setTimeFromTimeString(self::OPENING_TIME);
        $closingTime = $day->setTimeFromTimeString(self::CLOSING_TIME);
        $latestStartTime = $closingTime->subMinutes($service->duration_minutes);

        if ($latestStartTime->lt($openingTime)) {
            return [];
        }

        $appointments = Appointment::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $closingTime)
            ->where('end_time', '>', $openingTime)
            ->get(['start_time', 'end_time']);

        $slots = [];

        for ($slotStart = $openingTime; $slotStart->lte($latestStartTime); $slotStart = $slotStart->addMinutes(self::SLOT_INTERVAL_MINUTES)) {
            $slotEnd = $slotStart->addMinutes($service->duration_minutes);

            $hasConflict = $appointments->contains(
                fn (Appointment $appointment): bool => $slotStart->lt($appointment->end_time)
                    && $slotEnd->gt($appointment->start_time)
            );

            if (! $hasConflict) {
                $slots[] = $slotStart->format('H:i');
            }
        }

        return $slots;
    }
}
