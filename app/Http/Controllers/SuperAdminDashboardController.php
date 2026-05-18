<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\View\View;

class SuperAdminDashboardController extends Controller
{
    /**
     * Display the global super admin dashboard.
     */
    public function __invoke(): View
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return view('super-admin.dashboard', [
            'totalCompanies' => Company::query()->customer()->count(),
            'activeCompanies' => Company::query()->customer()->where('active', true)->count(),
            'totalUsers' => User::query()->count(),
            'appointmentsThisMonth' => Appointment::query()
                ->whereHas('company', fn ($query) => $query->customer())
                ->whereBetween('start_time', [$monthStart, $monthEnd])
                ->count(),
            'revenueThisMonth' => (float) Payment::query()
                ->whereHas('company', fn ($query) => $query->customer())
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->sum('gross_amount'),
            'latestCompanies' => Company::query()
                ->customer()
                ->withCount(['users', 'appointments'])
                ->withSum('payments', 'gross_amount')
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }
}
