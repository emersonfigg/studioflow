<?php

namespace App\Http\Controllers;

use App\Services\DailyDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DailyDashboardController extends Controller
{
    public function __invoke(Request $request, DailyDashboardService $dailyDashboardService): View
    {
        $user = $request->user();
        $selectedUserId = $user->isAdmin()
            ? ($request->integer('user_id') ?: null)
            : (int) $user->id;
        $selectedStatus = trim((string) $request->input('status', '')) ?: null;
        $selectedPaymentMethod = trim((string) $request->input('payment_method', '')) ?: null;

        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->string('date')->value())
            : CarbonImmutable::today();

        $dashboard = $dailyDashboardService->build((int) $user->company_id, [
            'date' => $date,
            'user_id' => $selectedUserId,
            'status' => $selectedStatus,
            'payment_method' => $selectedPaymentMethod,
        ]);

        return view('daily-dashboard.index', [
            'dashboard' => $dashboard,
            'date' => $date,
            'selectedUserId' => $selectedUserId,
            'selectedStatus' => $selectedStatus,
            'selectedPaymentMethod' => $selectedPaymentMethod,
            'canFilterProfessionals' => $user->isAdmin(),
        ]);
    }
}
