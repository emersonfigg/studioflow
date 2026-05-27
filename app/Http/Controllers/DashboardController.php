<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Payment;
use App\Models\ProductSaleItem;
use App\Models\Service;
use App\Services\BirthdayService;
use App\Services\StockService;
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
        $todayAppointments = new Collection;
        $publicBookingUrl = null;
        $sellerRanking = new Collection;
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $lowStockProducts = new Collection;
        $todayBirthdayClients = new Collection;
        $birthdayCongratulationsMessage = null;
        $company = $request->user()->company;

        if ($companyId !== null) {
            $birthdayService = app(BirthdayService::class);
            $todayBirthdayClients = $birthdayService->clientsWithBirthday((int) $companyId, 'day');
            $birthdayCongratulationsMessage = $company
                ? $birthdayService->messageTemplate($company)
                : BirthdayService::DEFAULT_MESSAGE;
            $lowStockProducts = app(StockService::class)->getLowStockProducts((int) $companyId);
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
            $cashInflowsToday = CashMovement::query()
                ->where('company_id', $companyId)
                ->where('type', CashMovement::TYPE_INFLOW)
                ->whereBetween('occurred_at', [$todayStart, $todayEnd]);

            $revenueToday = (float) (clone $cashInflowsToday)->sum('amount');
            $commissionsToday = (float) (clone $paymentsQuery)->sum('commission_amount');
            $netToday = max(0, round($revenueToday - $commissionsToday, 2));
            $completedToday = (clone $cashInflowsToday)->count();

            $rankingQuery = ProductSaleItem::query()
                ->join('product_sales', 'product_sales.id', '=', 'product_sale_items.product_sale_id')
                ->join('users', 'users.id', '=', 'product_sale_items.seller_id')
                ->where('product_sales.company_id', $companyId)
                ->whereBetween('product_sales.sold_at', [$monthStart, $monthEnd])
                ->where('product_sale_items.commission_amount', '>', 0);

            if (! $request->user()->isAdmin()) {
                $rankingQuery->where('product_sale_items.seller_id', $request->user()->id);
            }

            $sellerRanking = $rankingQuery
                ->selectRaw('users.id as user_id, users.name as user_name')
                ->selectRaw('SUM(product_sale_items.total_price) as gross_total')
                ->selectRaw('SUM(product_sale_items.commission_amount) as commission_total')
                ->selectRaw('SUM(product_sale_items.quantity) as items_total')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('commission_total')
                ->limit(5)
                ->get();
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
            'sellerRanking' => $sellerRanking,
            'rankingMonthLabel' => $monthStart->format('m/Y'),
            'lowStockProducts' => $lowStockProducts,
            'todayBirthdayClients' => $todayBirthdayClients,
            'birthdayCongratulationsMessage' => $birthdayCongratulationsMessage,
            'company' => $company,
        ]);
    }
}
