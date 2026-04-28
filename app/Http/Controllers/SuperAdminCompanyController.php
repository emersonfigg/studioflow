<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SuperAdminCompanyController extends Controller
{
    /**
     * Display a listing of companies.
     */
    public function index(): View
    {
        $companies = Company::query()
            ->withCount(['users', 'appointments'])
            ->withSum('payments', 'gross_amount')
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(12);

        return view('super-admin.companies.index', [
            'companies' => $companies,
        ]);
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company): View
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $company->loadCount(['users', 'appointments']);
        $company->loadSum('payments', 'gross_amount');

        return view('super-admin.companies.show', [
            'company' => $company,
            'appointmentsThisMonth' => Appointment::query()
                ->where('company_id', $company->id)
                ->whereBetween('start_time', [$monthStart, $monthEnd])
                ->count(),
            'revenueThisMonth' => (float) Payment::query()
                ->where('company_id', $company->id)
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->sum('gross_amount'),
            'totalRevenue' => (float) Payment::query()
                ->where('company_id', $company->id)
                ->sum('gross_amount'),
            'latestUsers' => $company->users()->latest('id')->limit(8)->get(),
            'latestAppointments' => $company->appointments()
                ->with(['client', 'user'])
                ->latest('start_time')
                ->limit(8)
                ->get(),
            'latestPayments' => $company->payments()
                ->with(['client', 'user', 'service'])
                ->latest('paid_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * Toggle the active status of the specified company.
     */
    public function toggleActive(Company $company): RedirectResponse
    {
        $company->update([
            'active' => ! $company->active,
        ]);

        return back()->with('status', $company->active ? 'company-activated' : 'company-deactivated');
    }
}
