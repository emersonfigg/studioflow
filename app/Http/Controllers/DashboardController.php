<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
        $upcomingAttendances = 0;
        $clientsCount = 0;
        $servicesCount = 0;
        $todayAppointments = new Collection();
        $publicBookingUrl = null;

        if ($companyId !== null) {
            $publicBookingUrl = route('public-bookings.create', $companyId);

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
        }

        return view('dashboard', [
            'appointmentsToday' => $appointmentsToday,
            'upcomingAttendances' => $upcomingAttendances,
            'clientsCount' => $clientsCount,
            'servicesCount' => $servicesCount,
            'todayAppointments' => $todayAppointments,
            'publicBookingUrl' => $publicBookingUrl,
        ]);
    }
}
