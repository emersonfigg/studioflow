<?php

namespace App\Services;

use App\Models\CommissionSettlement;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class CommissionSettlementService
{
    public function __construct(
        private readonly CashRegisterService $cashRegisterService,
    ) {
    }

    /**
     * Get eligible commission payments for a professional and period.
     *
     * @return EloquentCollection<int, Payment>
     */
    public function eligiblePayments(User $professional, CarbonImmutable $startDate, CarbonImmutable $endDate): EloquentCollection
    {
        return Payment::query()
            ->with(['client', 'service', 'appointment'])
            ->where('company_id', $professional->company_id)
            ->where('user_id', $professional->id)
            ->whereBetween('paid_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->where('commission_amount', '>', 0)
            ->whereDoesntHave('commissionSettlements')
            ->orderBy('paid_at')
            ->get();
    }

    /**
     * Register a commission settlement for the professional and period.
     *
     * @param  array{payment_method:string,notes?:string|null}  $data
     */
    public function settle(User $professional, CarbonImmutable $startDate, CarbonImmutable $endDate, User $createdBy, array $data): CommissionSettlement
    {
        $payments = $this->eligiblePayments($professional, $startDate, $endDate);

        abort_if($payments->isEmpty(), 422, 'Nao ha comissao pendente para este profissional no periodo selecionado.');

        $grossAmount = round((float) $payments->sum(fn (Payment $payment) => (float) $payment->gross_amount), 2);
        $commissionAmount = round((float) $payments->sum(fn (Payment $payment) => (float) $payment->commission_amount), 2);

        return DB::transaction(function () use ($professional, $startDate, $endDate, $createdBy, $data, $payments, $grossAmount, $commissionAmount): CommissionSettlement {
            $lockedPayments = Payment::query()
                ->whereIn('id', $payments->pluck('id'))
                ->whereDoesntHave('commissionSettlements')
                ->lockForUpdate()
                ->get();

            abort_if($lockedPayments->count() !== $payments->count(), 422, 'Uma ou mais comissoes deste periodo ja foram acertadas.');

            $settlement = CommissionSettlement::create([
                'company_id' => $professional->company_id,
                'user_id' => $professional->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'gross_amount' => $grossAmount,
                'commission_amount' => $commissionAmount,
                'payment_method' => $data['payment_method'],
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy->id,
            ]);

            $settlement->payments()->attach($lockedPayments->pluck('id'));

            $this->cashRegisterService->recordCommissionSettlement($settlement->load('user'));

            return $settlement->load(['user', 'payments.client', 'payments.service']);
        });
    }
}
