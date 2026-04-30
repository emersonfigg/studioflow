<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfessionalAvailabilityRequest;
use App\Models\ProfessionalDayOverride;
use App\Models\ProfessionalDayOverrideInterval;
use App\Models\ProfessionalWorkingHour;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfessionalAvailabilityController extends Controller
{
    private const WEEKDAY_LABELS = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sabado',
        0 => 'Domingo',
    ];

    /**
     * Show the authenticated professional availability page.
     */
    public function editOwn(Request $request): View
    {
        return $this->renderForm($request, $request->user(), false);
    }

    /**
     * Update the authenticated professional day availability.
     */
    public function updateOwn(UpdateProfessionalAvailabilityRequest $request): RedirectResponse
    {
        $targetUser = $request->user();
        $date = $this->persistDayConfiguration($request, $targetUser);

        return redirect()
            ->route('schedule.edit', [
                'month' => substr($date, 0, 7),
                'date' => $date,
            ])
            ->with('status', 'availability-updated');
    }

    /**
     * Clear a saved day-specific configuration for the authenticated professional.
     */
    public function clearOwn(Request $request): RedirectResponse
    {
        $date = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ])['date'];

        $this->clearDayConfiguration($request->user(), $date);

        return redirect()
            ->route('schedule.edit', [
                'month' => substr($date, 0, 7),
                'date' => $date,
            ])
            ->with('status', 'availability-cleared');
    }

    /**
     * Show the availability page for a company professional.
     */
    public function editTeam(Request $request, User $team): View
    {
        $this->ensureAdminAccessToProfessional($request, $team);

        return $this->renderForm($request, $team, true);
    }

    /**
     * Update the day availability page for a company professional.
     */
    public function updateTeam(UpdateProfessionalAvailabilityRequest $request, User $team): RedirectResponse
    {
        $this->ensureAdminAccessToProfessional($request, $team);

        $date = $this->persistDayConfiguration($request, $team);

        return redirect()
            ->route('team.availability.edit', [
                'team' => $team,
                'month' => substr($date, 0, 7),
                'date' => $date,
            ])
            ->with('status', 'availability-updated');
    }

    /**
     * Clear a saved day-specific configuration for a company professional.
     */
    public function clearTeam(Request $request, User $team): RedirectResponse
    {
        $this->ensureAdminAccessToProfessional($request, $team);

        $date = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ])['date'];

        $this->clearDayConfiguration($team, $date);

        return redirect()
            ->route('team.availability.edit', [
                'team' => $team,
                'month' => substr($date, 0, 7),
                'date' => $date,
            ])
            ->with('status', 'availability-cleared');
    }

    /**
     * Render the calendar-driven availability form.
     */
    private function renderForm(Request $request, User $targetUser, bool $isAdminView): View
    {
        abort_unless($targetUser->company_id === $request->user()->company_id, 404);

        $selectedMonth = $this->resolveMonth((string) $request->input('month', ''));
        $selectedDate = $this->resolveSelectedDate((string) $request->input('date', ''), $selectedMonth);

        $targetUser->load([
            'workingHours' => fn ($query) => $query->orderBy('weekday')->orderBy('start_time'),
            'dayOverrides' => fn ($query) => $query
                ->with('intervals')
                ->whereBetween('date', [
                    $selectedMonth->startOfMonth()->toDateString(),
                    $selectedMonth->endOfMonth()->toDateString(),
                ])
                ->orderBy('date'),
        ]);

        $overridesByDate = $targetUser->dayOverrides->keyBy(
            fn (ProfessionalDayOverride $override): string => $override->date->toDateString()
        );
        $selectedOverride = $overridesByDate->get($selectedDate->toDateString());
        $weeklyFallbackBlocks = $targetUser->workingHours
            ->where('weekday', $selectedDate->dayOfWeek)
            ->map(fn (ProfessionalWorkingHour $workingHour): array => [
                'start_time' => substr((string) $workingHour->start_time, 0, 5),
                'end_time' => substr((string) $workingHour->end_time, 0, 5),
            ])
            ->values();

        $intervals = old('intervals', $this->resolveInitialIntervals($selectedOverride, $weeklyFallbackBlocks));
        $worksThisDay = (bool) old(
            'works_this_day',
            $selectedOverride
                ? ! $selectedOverride->is_day_off
                : $weeklyFallbackBlocks->isNotEmpty()
        );
        $notes = (string) old('notes', $selectedOverride?->notes ?? '');

        return view('schedule.edit', [
            'targetUser' => $targetUser,
            'isAdminView' => $isAdminView,
            'selectedMonth' => $selectedMonth->format('Y-m'),
            'selectedMonthLabel' => ucfirst($selectedMonth->translatedFormat('F \d\e Y')),
            'selectedDate' => $selectedDate->toDateString(),
            'selectedDateLabel' => $selectedDate->format('d/m/Y'),
            'calendarWeeks' => $this->calendarWeeks($selectedMonth, $overridesByDate, $selectedDate),
            'selectedDayState' => [
                'works_this_day' => $worksThisDay,
                'intervals' => $intervals,
                'notes' => $notes,
            ],
            'selectedOverride' => $selectedOverride,
            'weeklyFallbackBlocks' => $weeklyFallbackBlocks,
            'hasSpecificConfiguration' => $selectedOverride !== null,
            'previousMonth' => $selectedMonth->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $selectedMonth->addMonthNoOverflow()->format('Y-m'),
            'configuredDaysCount' => $targetUser->dayOverrides->where('is_day_off', false)->count(),
            'dayOffCount' => $targetUser->dayOverrides->where('is_day_off', true)->count(),
        ]);
    }

    /**
     * Save a day-specific configuration for the professional.
     */
    private function persistDayConfiguration(UpdateProfessionalAvailabilityRequest $request, User $targetUser): string
    {
        $validated = $request->validated();
        $date = $validated['date'];
        $worksThisDay = filter_var($validated['works_this_day'], FILTER_VALIDATE_BOOLEAN);
        $notes = ($validated['notes'] ?? null) ?: null;
        $intervalRows = collect($validated['intervals'] ?? [])
            ->map(fn (array $interval): array => [
                'start_time' => $interval['start_time'] ?? null,
                'end_time' => $interval['end_time'] ?? null,
            ])
            ->filter(fn (array $interval): bool => filled($interval['start_time']) && filled($interval['end_time']))
            ->sortBy('start_time')
            ->values();

        DB::transaction(function () use ($targetUser, $date, $worksThisDay, $notes, $intervalRows): void {
            $override = ProfessionalDayOverride::query()
                ->where('company_id', $targetUser->company_id)
                ->where('user_id', $targetUser->id)
                ->whereDate('date', $date)
                ->first();

            if (! $override) {
                $override = new ProfessionalDayOverride([
                    'company_id' => $targetUser->company_id,
                    'user_id' => $targetUser->id,
                    'date' => $date,
                ]);
            }

            $override->fill([
                'is_day_off' => ! $worksThisDay,
                'start_time' => null,
                'end_time' => null,
                'notes' => $notes,
            ]);
            $override->save();

            $override->intervals()->delete();

            if ($worksThisDay && $intervalRows->isNotEmpty()) {
                $override->intervals()->createMany(
                    $intervalRows->map(fn (array $interval): array => [
                        'start_time' => $interval['start_time'],
                        'end_time' => $interval['end_time'],
                    ])->all()
                );
            }
        });

        return $date;
    }

    /**
     * Remove a date-specific override so the weekly fallback is used again.
     */
    private function clearDayConfiguration(User $targetUser, string $date): void
    {
        ProfessionalDayOverride::query()
            ->where('company_id', $targetUser->company_id)
            ->where('user_id', $targetUser->id)
            ->whereDate('date', $date)
            ->delete();
    }

    /**
     * Ensure the authenticated user can manage the target professional availability.
     */
    private function ensureAdminAccessToProfessional(Request $request, User $targetUser): void
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($targetUser->company_id === $request->user()->company_id, 404);
    }

    /**
     * Resolve the active month for the calendar.
     */
    private function resolveMonth(string $month): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            try {
                return CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                return CarbonImmutable::today()->startOfMonth();
            }
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    /**
     * Resolve the selected date within the chosen month.
     */
    private function resolveSelectedDate(string $date, CarbonImmutable $month): CarbonImmutable
    {
        if ($date !== '') {
            try {
                $candidate = CarbonImmutable::parse($date);
            } catch (\Throwable) {
                $candidate = null;
            }

            if ($candidate?->isSameMonth($month)) {
                return $candidate;
            }
        }

        if (CarbonImmutable::today()->isSameMonth($month)) {
            return CarbonImmutable::today();
        }

        return $month->startOfMonth();
    }

    /**
     * Prepare up to two time intervals for the selected day form.
     *
     * @param  Collection<int, array{start_time: string, end_time: string}>  $weeklyFallbackBlocks
     * @return list<array{start_time: string, end_time: string}>
     */
    private function resolveInitialIntervals(?ProfessionalDayOverride $selectedOverride, Collection $weeklyFallbackBlocks): array
    {
        $intervals = collect();

        if ($selectedOverride) {
            $intervals = $selectedOverride->intervals
                ->take(2)
                ->map(fn (ProfessionalDayOverrideInterval $interval): array => [
                    'start_time' => substr((string) $interval->start_time, 0, 5),
                    'end_time' => substr((string) $interval->end_time, 0, 5),
                ])
                ->values();

            if ($intervals->isEmpty() && ! $selectedOverride->is_day_off && $selectedOverride->start_time && $selectedOverride->end_time) {
                $intervals = collect([[
                    'start_time' => substr((string) $selectedOverride->start_time, 0, 5),
                    'end_time' => substr((string) $selectedOverride->end_time, 0, 5),
                ]]);
            }
        }

        if ($intervals->isEmpty()) {
            $intervals = $weeklyFallbackBlocks->take(2)->values();
        }

        while ($intervals->count() < 2) {
            $intervals->push([
                'start_time' => '',
                'end_time' => '',
            ]);
        }

        return $intervals->take(2)->all();
    }

    /**
     * Build the monthly calendar matrix.
     *
     * @param  Collection<string, ProfessionalDayOverride>  $overridesByDate
     * @return list<list<array<string, mixed>>>
     */
    private function calendarWeeks(
        CarbonImmutable $month,
        Collection $overridesByDate,
        CarbonImmutable $selectedDate,
    ): array {
        $start = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);
        $weeks = [];
        $week = [];

        for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDay()) {
            $override = $overridesByDate->get($cursor->toDateString());
            $status = null;

            if ($override?->is_day_off) {
                $status = 'day_off';
            } elseif ($override) {
                $status = 'configured';
            }

            $week[] = [
                'date' => $cursor->toDateString(),
                'day_number' => $cursor->day,
                'is_current_month' => $cursor->isSameMonth($month),
                'is_selected' => $cursor->isSameDay($selectedDate),
                'is_today' => $cursor->isSameDay(CarbonImmutable::today()),
                'status' => $status,
                'label' => self::WEEKDAY_LABELS[$cursor->dayOfWeek] ?? 'Dia',
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }
}
