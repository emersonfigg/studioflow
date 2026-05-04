<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Models\ProfessionalWorkingHour;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    /**
     * Display a listing of professionals for the authenticated admin's company.
     */
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $users = User::query()
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(12);

        return view('team.index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new professional.
     */
    public function create(Request $request): View
    {
        $this->ensureAdmin($request);

        return view('team.create');
    }

    /**
     * Store a newly created professional.
     */
    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['active'] = $request->boolean('active');
        $photo = $request->file('photo') ?? ($data['photo'] ?? null);
        $fixedWeekdays = $data['fixed_weekdays'] ?? [];
        $fixedIntervals = $data['fixed_intervals'] ?? [];
        unset($data['fixed_weekdays'], $data['fixed_intervals']);
        unset($data['photo']);

        if ($data['commission_type'] === 'none') {
            $data['commission_value'] = null;
        }

        if ($photo) {
            $data['photo_path'] = MediaStorage::putFile('professionals', $photo);
        }

        $user = User::create([
            ...$data,
            'company_id' => $request->user()->company_id,
        ]);

        $this->syncFixedSchedule($user, $fixedWeekdays, $fixedIntervals);

        return redirect()
            ->route('team.index')
            ->with('status', 'team-member-created')
            ->with('highlight_user_id', $user->id);
    }

    /**
     * Show the form for editing the specified professional.
     */
    public function edit(Request $request, User $team): View
    {
        $this->ensureAdmin($request);
        $this->ensureUserBelongsToAdminCompany($request, $team);

        return view('team.edit', [
            'member' => $team,
        ]);
    }

    /**
     * Update the specified professional.
     */
    public function update(UpdateTeamMemberRequest $request, User $team): RedirectResponse
    {
        $this->ensureUserBelongsToAdminCompany($request, $team);

        $data = $request->validated();
        $data['active'] = $request->boolean('active');
        $photo = $request->file('photo') ?? ($data['photo'] ?? null);
        $fixedWeekdays = $data['fixed_weekdays'] ?? [];
        $fixedIntervals = $data['fixed_intervals'] ?? [];
        unset($data['fixed_weekdays'], $data['fixed_intervals']);
        unset($data['photo']);

        if (($data['commission_type'] ?? 'none') === 'none') {
            $data['commission_value'] = null;
        }

        if (($data['password'] ?? null) === null || $data['password'] === '') {
            unset($data['password']);
        }

        if ($photo) {
            $newPath = MediaStorage::putFile('professionals', $photo);

            if ($team->photo_path) {
                MediaStorage::delete($team->normalizedPhotoPath() ?? $team->photo_path);
            }

            $data['photo_path'] = $newPath;
        }

        $team->update($data);
        $this->syncFixedSchedule($team, $fixedWeekdays, $fixedIntervals);

        return redirect()
            ->route('team.index')
            ->with('status', 'team-member-updated')
            ->with('highlight_user_id', $team->id);
    }

    /**
     * Toggle the active state of the specified professional.
     */
    public function toggleActive(Request $request, User $team): RedirectResponse
    {
        $this->ensureAdmin($request);
        $this->ensureUserBelongsToAdminCompany($request, $team);

        $team->update([
            'active' => ! $team->active,
        ]);

        return redirect()
            ->route('team.index')
            ->with('status', $team->active ? 'team-member-activated' : 'team-member-deactivated')
            ->with('highlight_user_id', $team->id);
    }

    /**
     * Ensure the current user is an admin with a company.
     */
    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin() && $request->user()->company_id !== null, 403);
    }

    /**
     * Ensure the given user belongs to the authenticated admin's company.
     */
    private function ensureUserBelongsToAdminCompany(Request $request, User $user): void
    {
        abort_unless($user->company_id === $request->user()->company_id, 404);
    }

    /**
     * Sync weekly fixed working hours for a professional.
     *
     * @param  array<int, int|string>  $weekdays
     * @param  array<int, array{start_time?: string|null, end_time?: string|null}>  $intervals
     */
    private function syncFixedSchedule(User $user, array $weekdays, array $intervals): void
    {
        $user->workingHours()->delete();

        if ($user->schedule_type !== 'fixed') {
            return;
        }

        $normalizedWeekdays = collect($weekdays)
            ->map(fn ($weekday): int => (int) $weekday)
            ->filter(fn (int $weekday): bool => $weekday >= 0 && $weekday <= 6)
            ->unique()
            ->values();

        $normalizedIntervals = collect($intervals)
            ->map(function (array $interval): ?array {
                $start = $interval['start_time'] ?? null;
                $end = $interval['end_time'] ?? null;

                if (! $start || ! $end || $end <= $start) {
                    return null;
                }

                return [
                    'start_time' => $start,
                    'end_time' => $end,
                ];
            })
            ->filter()
            ->values();

        foreach ($normalizedWeekdays as $weekday) {
            foreach ($normalizedIntervals as $interval) {
                ProfessionalWorkingHour::create([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'weekday' => $weekday,
                    'start_time' => $interval['start_time'],
                    'end_time' => $interval['end_time'],
                    'active' => true,
                ]);
            }
        }
    }
}
