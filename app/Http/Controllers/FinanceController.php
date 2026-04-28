<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FinanceController extends Controller
{
    /**
     * Display the finance dashboard.
     */
    public function index(Request $request): View
    {
        [$from, $to, $selectedUserId, $users, $payments] = $this->baseData($request);

        $grossAmount = (float) $payments->sum(fn (Payment $payment) => (float) $payment->gross_amount);
        $commissionAmount = (float) $payments->sum(fn (Payment $payment) => (float) $payment->commission_amount);
        $netAmount = (float) $payments->sum(fn (Payment $payment) => (float) $payment->net_amount);
        $appointmentsCount = $payments->count();
        $averageTicket = $appointmentsCount > 0 ? round($grossAmount / $appointmentsCount, 2) : 0.0;
        $paymentMethodTotals = $payments
            ->groupBy('payment_method')
            ->map(fn (Collection $group): float => (float) $group->sum(fn (Payment $payment) => (float) $payment->gross_amount));

        $recentPayments = $payments
            ->sortByDesc(fn (Payment $payment) => $payment->paid_at)
            ->take(10)
            ->values();

        return view('finance.index', [
            'from' => $from,
            'to' => $to,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'grossAmount' => $grossAmount,
            'commissionAmount' => $commissionAmount,
            'netAmount' => $netAmount,
            'appointmentsCount' => $appointmentsCount,
            'averageTicket' => $averageTicket,
            'paymentMethodTotals' => $paymentMethodTotals,
            'recentPayments' => $recentPayments,
            'page' => 'dashboard',
        ]);
    }

    /**
     * Display production by professional.
     */
    public function production(Request $request): View
    {
        [$from, $to, $selectedUserId, $users, $payments] = $this->baseData($request);

        $rows = $payments
            ->groupBy('user_id')
            ->map(function (Collection $group) {
                /** @var Payment $first */
                $first = $group->first();
                $gross = (float) $group->sum(fn (Payment $payment) => (float) $payment->gross_amount);
                $commission = (float) $group->sum(fn (Payment $payment) => (float) $payment->commission_amount);
                $net = (float) $group->sum(fn (Payment $payment) => (float) $payment->net_amount);

                return [
                    'user' => $first->user,
                    'completed_appointments' => $group->count(),
                    'gross_amount' => $gross,
                    'commission_amount' => $commission,
                    'net_amount' => $net,
                    'average_ticket' => $group->count() > 0 ? round($gross / $group->count(), 2) : 0.0,
                ];
            })
            ->sortBy(fn (array $row) => $row['user']->name)
            ->values();

        return view('finance.production', [
            'rows' => $rows,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'from' => $from,
            'to' => $to,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'page' => 'production',
        ]);
    }

    /**
     * Display commissions by professional.
     */
    public function commissions(Request $request): View
    {
        [$from, $to, $selectedUserId, $users, $payments] = $this->baseData($request);

        $rows = $payments
            ->groupBy('user_id')
            ->map(function (Collection $group) {
                /** @var Payment $first */
                $first = $group->first();
                $gross = (float) $group->sum(fn (Payment $payment) => (float) $payment->gross_amount);
                $commission = (float) $group->sum(fn (Payment $payment) => (float) $payment->commission_amount);

                return [
                    'user' => $first->user,
                    'commission_type' => $first->commission_type,
                    'commission_rate' => $first->commission_rate,
                    'services_count' => $group->count(),
                    'gross_amount' => $gross,
                    'commission_amount' => $commission,
                    'effective_rate' => $gross > 0 ? round(($commission / $gross) * 100, 2) : 0.0,
                ];
            })
            ->sortBy(fn (array $row) => $row['user']->name)
            ->values();

        $recentCommissionPayments = $payments
            ->filter(fn (Payment $payment) => (float) $payment->commission_amount > 0)
            ->sortByDesc(fn (Payment $payment) => $payment->paid_at)
            ->take(12)
            ->values();

        return view('finance.commissions', [
            'rows' => $rows,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'from' => $from,
            'to' => $to,
            'canFilterProfessionals' => $request->user()->isAdmin(),
            'recentCommissionPayments' => $recentCommissionPayments,
            'page' => 'commissions',
        ]);
    }

    /**
     * Build the shared finance dataset with filters and company isolation.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null, 3: \Illuminate\Database\Eloquent\Collection<int, User>, 4: Collection<int, Payment>}
     */
    private function baseData(Request $request): array
    {
        $companyId = $request->user()->company_id;
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from'))->startOfDay()
            : CarbonImmutable::today()->startOfMonth();
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to'))->endOfDay()
            : CarbonImmutable::today()->endOfDay();

        $users = User::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $selectedUserId = $request->user()->isAdmin()
            ? ($request->integer('user_id') ?: null)
            : $request->user()->id;

        $paymentsQuery = Payment::query()
            ->with(['user', 'client', 'service', 'appointment'])
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [$from, $to]);

        if ($selectedUserId !== null) {
            $paymentsQuery->where('user_id', $selectedUserId);
        }

        $payments = $paymentsQuery
            ->orderBy('paid_at')
            ->get();

        return [$from, $to, $selectedUserId, $users, $payments];
    }
}
