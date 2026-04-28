<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        unset($data['photo']);

        if ($data['commission_type'] === 'none') {
            $data['commission_value'] = null;
        }

        if ($photo) {
            $data['photo_path'] = $photo->store('professionals', 'public');
        }

        $user = User::create([
            ...$data,
            'company_id' => $request->user()->company_id,
        ]);

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
        unset($data['photo']);

        if (($data['commission_type'] ?? 'none') === 'none') {
            $data['commission_value'] = null;
        }

        if (($data['password'] ?? null) === null || $data['password'] === '') {
            unset($data['password']);
        }

        if ($photo) {
            $newPath = $photo->store('professionals', 'public');

            if ($team->photo_path) {
                Storage::disk('public')->delete($team->photo_path);
            }

            $data['photo_path'] = $newPath;
        }

        $team->update($data);

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
}
