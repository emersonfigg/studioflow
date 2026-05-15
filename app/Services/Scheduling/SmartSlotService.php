<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SmartSlotService
{
    private const SLOT_TOLERANCE_MINUTES = 5;

    public function __construct(
        private readonly AvailabilityService $availabilityService,
    ) {}

    /**
     * @param  list<int>  $serviceIds
     * @return list<array{time: string, end_time: string, score: float, label: ?string, warnings: list<string>}>
     */
    public function rankedSlots(
        Company $company,
        User $user,
        CarbonInterface|string $date,
        int $durationMinutes,
        array $serviceIds,
        ?int $ignoreAppointmentId = null,
    ): array {
        if ($user->company_id !== $company->id || $durationMinutes < 1) {
            return [];
        }

        $timezone = (string) config('app.timezone', 'America/Bahia');
        $day = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $rangeEnd = $day->endOfDay();

        $minServiceMinutes = $this->minimumActiveServiceDurationMinutes($company->id, $serviceIds, $durationMinutes);

        $times = $this->availabilityService->availableSlotsForDuration(
            $company,
            $user,
            $durationMinutes,
            $day,
            true,
            $ignoreAppointmentId,
            null,
        );

        /** @var Collection<int, Appointment> $dayAppointments */
        $dayAppointments = Appointment::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($ignoreAppointmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->where('start_time', '<', $rangeEnd)
            ->where('end_time', '>', $day)
            ->orderBy('start_time')
            ->get();

        $ranked = [];

        foreach ($times as $time) {
            $slotStart = CarbonImmutable::parse($day->format('Y-m-d').' '.substr((string) $time, 0, 5), $timezone);
            $slotEnd = $slotStart->addMinutes($durationMinutes);

            $prev = $dayAppointments
                ->filter(fn (Appointment $a): bool => CarbonImmutable::parse($a->end_time)->lte($slotStart))
                ->sortByDesc(fn (Appointment $a): string => $a->end_time->toIso8601String())
                ->first();

            $next = $dayAppointments
                ->filter(fn (Appointment $a): bool => CarbonImmutable::parse($a->start_time)->gte($slotEnd))
                ->sortBy(fn (Appointment $a): string => $a->start_time->toIso8601String())
                ->first();

            $gapBefore = $prev
                ? max(0, CarbonImmutable::parse($prev->end_time)->diffInMinutes($slotStart))
                : self::SLOT_TOLERANCE_MINUTES + 1;

            $gapAfter = $next
                ? max(0, $slotEnd->diffInMinutes(CarbonImmutable::parse($next->start_time)))
                : self::SLOT_TOLERANCE_MINUTES + 1;

            $warnings = [];
            $score = 50.0;
            $label = null;

            if ($gapBefore <= self::SLOT_TOLERANCE_MINUTES) {
                $score += 35;
            }

            if ($gapAfter <= self::SLOT_TOLERANCE_MINUTES) {
                $score += 35;
            }

            if ($gapAfter > 0 && $gapAfter < $minServiceMinutes) {
                $score -= 40;
                $warnings[] = 'Pode gerar intervalo ocioso';
            }

            if ($gapBefore > 0 && $gapBefore < $minServiceMinutes && $prev !== null) {
                $score -= 15;
                if (! in_array('Pode gerar intervalo ocioso', $warnings, true)) {
                    $warnings[] = 'Pode gerar intervalo ocioso';
                }
            }

            if ($gapBefore <= self::SLOT_TOLERANCE_MINUTES && $gapAfter <= self::SLOT_TOLERANCE_MINUTES) {
                $label = 'Melhor encaixe';
                $score += 25;
            }

            $ranked[] = [
                'time' => (string) $time,
                'end_time' => $slotEnd->toIso8601String(),
                'score' => round($score, 2),
                'label' => $label,
                'warnings' => $warnings,
            ];
        }

        usort($ranked, function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return strcmp($a['time'], $b['time']);
            }

            return $a['score'] < $b['score'] ? 1 : -1;
        });

        return $ranked;
    }

    /**
     * @param  list<int>  $serviceIds
     */
    private function minimumActiveServiceDurationMinutes(int $companyId, array $serviceIds, int $fallback): int
    {
        $ids = array_values(array_unique(array_filter($serviceIds)));

        if ($ids === []) {
            return max(1, $fallback);
        }

        $min = Service::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->whereIn('id', $ids)
            ->min('duration_minutes');

        return max(1, (int) ($min ?? $fallback));
    }
}
