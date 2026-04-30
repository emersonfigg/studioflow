<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        if ($request->user()->isAdmin() && $request->user()->company && ! $request->user()->company->onboardingCompleted()) {
            return redirect()->route('company.onboarding');
        }

        $companyId = $request->user()->company_id;
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $appointmentsToday = 0;
        $upcomingAttendances = 0;
        $clientsCount = 0;
        $servicesCount = 0;
        $revenueToday = 0;
        $commissionsToday = 0;
        $netToday = 0;
        $completedToday = 0;
        $todayAppointments = new Collection();
        $publicBookingUrl = null;

        if ($companyId !== null) {
            $publicBookingUrl = route('public-bookings.create', $companyId);
            $paymentsQuery = Payment::query()
                ->where('company_id', $companyId)
                ->whereBetween('paid_at', [$todayStart, $todayEnd]);

            if (! $request->user()->isAdmin()) {
                $paymentsQuery->where('user_id', $request->user()->id);
            }

            $appointmentsToday = Appointment::query()
                ->where('company_id', $companyId)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('start_time', [$todayStart, $todayEnd])
                ->count();

            $upcomingAttendances = Appointment::query()
                ->where('company_id', $companyId)
                ->where('status', '!=', 'cancelled')
                ->where('start_time', '>=', $now)
                ->count();

            $clientsCount = Client::query()
                ->where('company_id', $companyId)
                ->count();

            $servicesCount = Service::query()
                ->where('company_id', $companyId)
                ->count();

            $todayAppointments = Appointment::query()
                ->with(['client', 'service', 'user'])
                ->where('company_id', $companyId)
                ->whereBetween('start_time', [$todayStart, $todayEnd])
                ->orderByDesc('start_time')
                ->limit(8)
                ->get()
                ->values();

            $revenueToday = (float) (clone $paymentsQuery)->sum('gross_amount');
            $commissionsToday = (float) (clone $paymentsQuery)->sum('commission_amount');
            $netToday = (float) (clone $paymentsQuery)->sum('net_amount');
            $completedToday = (clone $paymentsQuery)->count();
        }

        return view('dashboard', [
            'appointmentsToday' => $appointmentsToday,
            'upcomingAttendances' => $upcomingAttendances,
            'clientsCount' => $clientsCount,
            'servicesCount' => $servicesCount,
            'revenueToday' => $revenueToday,
            'commissionsToday' => $commissionsToday,
            'netToday' => $netToday,
            'completedToday' => $completedToday,
            'todayAppointments' => $todayAppointments,
            'publicBookingUrl' => $publicBookingUrl,
        ]);
    }
}
