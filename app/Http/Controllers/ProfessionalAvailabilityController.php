<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfessionalAvailabilityRequest;
use App\Models\ProfessionalDayOverride;
use App\Models\ProfessionalWorkingHour;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfessionalAvailabilityController extends Controller
{
    private const WEEKDAYS = [
        ['value' => 0, 'label' => 'Domingo'],
        ['value' => 1, 'label' => 'Segunda-feira'],
        ['value' => 2, 'label' => 'Terca-feira'],
        ['value' => 3, 'label' => 'Quarta-feira'],
        ['value' => 4, 'label' => 'Quinta-feira'],
        ['value' => 5, 'label' => 'Sexta-feira'],
        ['value' => 6, 'label' => 'Sabado'],
    ];

    /**
     * Show the authenticated professional availability page.
     */
    public function editOwn(Request $request): View
    {
        return $this->renderForm($request, $request->user(), false);
    }

    /**
     * Update the authenticated professional availability.
     */
    public function updateOwn(UpdateProfessionalAvailabilityRequest $request): RedirectResponse
    {
        $user = $request->user();

        $this->persistAvailability($request, $user);

        return redirect()
            ->route('schedule.edit')
            ->with('status', 'availability-updated');
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
     * Update the availability page for a company professional.
     */
    public function updateTeam(UpdateProfessionalAvailabilityRequest $request, User $team): RedirectResponse
    {
        $this->ensureAdminAccessToProfessional($request, $team);

        $this->persistAvailability($request, $team);

        return redirect()
            ->route('team.availability.edit', $team)
            ->with('status', 'availability-updated');
    }

    /**
     * Render the availability form.
     */
    private function renderForm(Request $request, User $targetUser, bool $isAdminView): View
    {
        abort_unless($targetUser->company_id === $request->user()->company_id, 404);

        $targetUser->load([
            'workingHours' => fn ($query) => $query->where('active', true)->orderBy('weekday')->orderBy('start_time'),
            'dayOverrides' => fn ($query) => $query->orderBy('date'),
        ]);

        $workingHours = old('working_hours', $targetUser->workingHours->map(fn (ProfessionalWorkingHour $workingHour): array => [
            'weekday' => $workingHour->weekday,
            'start_time' => substr((string) $workingHour->start_time, 0, 5),
            'end_time' => substr((string) $workingHour->end_time, 0, 5),
            'active' => true,
        ])->values()->all());

        $overrides = old('overrides', $targetUser->dayOverrides->map(fn (ProfessionalDayOverride $override): array => [
            'date' => $override->date?->format('Y-m-d'),
            'is_day_off' => $override->is_day_off,
            'start_time' => $override->start_time ? substr((string) $override->start_time, 0, 5) : null,
            'end_time' => $override->end_time ? substr((string) $override->end_time, 0, 5) : null,
            'notes' => $override->notes,
        ])->values()->all());

        return view('schedule.edit', [
            'targetUser' => $targetUser,
            'isAdminView' => $isAdminView,
            'weekdayOptions' => self::WEEKDAYS,
            'workingHours' => $workingHours,
            'overrides' => $overrides,
        ]);
    }

    /**
     * Save the complete availability configuration for the professional.
     */
    private function persistAvailability(UpdateProfessionalAvailabilityRequest $request, User $targetUser): void
    {
        $companyId = $targetUser->company_id;
        $workingHours = collect($request->validated('working_hours', []))
            ->map(fn (array $workingHour): array => [
                'company_id' => $companyId,
                'user_id' => $targetUser->id,
                'weekday' => (int) $workingHour['weekday'],
                'start_time' => $workingHour['start_time'],
                'end_time' => $workingHour['end_time'],
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->sortBy(['weekday', 'start_time'])
            ->values();

        $overrides = collect($request->validated('overrides', []))
            ->map(fn (array $override): array => [
                'company_id' => $companyId,
                'user_id' => $targetUser->id,
                'date' => $override['date'],
                'is_day_off' => filter_var($override['is_day_off'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'start_time' => $override['start_time'] ?: null,
                'end_time' => $override['end_time'] ?: null,
                'notes' => $override['notes'] ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->sortBy('date')
            ->values();

        DB::transaction(function () use ($targetUser, $workingHours, $overrides): void {
            $targetUser->workingHours()->delete();
            $targetUser->dayOverrides()->delete();

            if ($workingHours->isNotEmpty()) {
                ProfessionalWorkingHour::query()->insert($workingHours->all());
            }

            if ($overrides->isNotEmpty()) {
                ProfessionalDayOverride::query()->insert($overrides->all());
            }
        });
    }

    /**
     * Ensure the authenticated user can manage the target professional availability.
     */
    private function ensureAdminAccessToProfessional(Request $request, User $targetUser): void
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($targetUser->company_id === $request->user()->company_id, 404);
    }
}
