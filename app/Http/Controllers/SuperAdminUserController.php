<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class SuperAdminUserController extends Controller
{
    /**
     * Display a global listing of users.
     */
    public function index(): View
    {
        $users = User::query()
            ->with('company')
            ->orderByDesc('global_role')
            ->orderBy('name')
            ->paginate(20);

        return view('super-admin.users.index', [
            'users' => $users,
        ]);
    }
}
