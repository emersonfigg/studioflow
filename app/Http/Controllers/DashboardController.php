<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard.
     */
    public function __invoke(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $appointmentsToday = 0;
        $clientsCount = 0;
        $servicesCount = 0;
        $monthlyRevenue = 0;
        $upcomingAppointments = collect();

        if ($companyId !== null) {
            $appointmentsToday = Appointment::query()
                ->where('company_id', $companyId)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('start_time', [$todayStart, $todayEnd])
                ->count();

            $clientsCount = Client::query()
                ->where('company_id', $companyId)
                ->count();

            $servicesCount = Service::query()
                ->where('company_id', $companyId)
                ->count();

            $monthlyRevenue = Appointment::query()
                ->with('service')
                ->where('company_id', $companyId)
                ->where('status', 'completed')
                ->whereBetween('start_time', [$monthStart, $monthEnd])
                ->get()
                ->sum(fn (Appointment $appointment): float => (float) ($appointment->service?->price ?? 0));

            $upcomingAppointments = Appointment::query()
                ->with(['client', 'service', 'user'])
                ->where('company_id', $companyId)
                ->where('status', '!=', 'cancelled')
                ->where('start_time', '>=', $now)
                ->orderBy('start_time')
                ->limit(8)
                ->get();
        }

        return view('dashboard', [
            'appointmentsToday' => $appointmentsToday,
            'clientsCount' => $clientsCount,
            'servicesCount' => $servicesCount,
            'monthlyRevenue' => $monthlyRevenue,
            'upcomingAppointments' => $upcomingAppointments,
        ]);
    }
}
