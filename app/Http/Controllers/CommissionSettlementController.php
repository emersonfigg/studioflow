<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommissionSettlementRequest;
use App\Models\CommissionSettlement;
use App\Models\User;
use App\Services\CommissionSettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommissionSettlementController extends Controller
{
    /**
     * Show the commission settlement confirmation screen.
     */
    public function create(Request $request, CommissionSettlementService $settlementService): View|RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $professional = User::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($request->integer('user_id'));

        $startDate = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from'))->startOfDay()
            : CarbonImmutable::today()->startOfMonth();
        $endDate = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to'))->endOfDay()
            : CarbonImmutable::today()->endOfDay();

        $payments = $settlementService->eligiblePayments($professional, $startDate, $endDate);

        if ($payments->isEmpty()) {
            return redirect()
                ->route('finance.commissions', [
                    'from' => $startDate->format('Y-m-d'),
                    'to' => $endDate->format('Y-m-d'),
                    'user_id' => $professional->id,
                ])
                ->with('status', 'commission-settlement-empty');
        }

        return view('finance.commission-settlements.create', [
            'professional' => $professional,
            'payments' => $payments,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'grossAmount' => (float) $payments->sum(fn ($payment) => (float) $payment->gross_amount),
            'commissionAmount' => (float) $payments->sum(fn ($payment) => (float) $payment->commission_amount),
            'paymentMethods' => [
                'cash' => 'Dinheiro',
                'pix' => 'Pix',
                'bank_transfer' => 'Transferencia bancaria',
            ],
        ]);
    }

    /**
     * Store the commission settlement.
     */
    public function store(StoreCommissionSettlementRequest $request, CommissionSettlementService $settlementService): RedirectResponse
    {
        $professional = User::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($request->integer('user_id'));

        $settlementService->settle(
            $professional,
            CarbonImmutable::parse($request->string('start_date'))->startOfDay(),
            CarbonImmutable::parse($request->string('end_date'))->endOfDay(),
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('finance.commissions', [
                'from' => CarbonImmutable::parse($request->string('start_date'))->format('Y-m-d'),
                'to' => CarbonImmutable::parse($request->string('end_date'))->format('Y-m-d'),
                'user_id' => $professional->id,
            ])
            ->with('status', 'commission-settlement-created');
    }
}
