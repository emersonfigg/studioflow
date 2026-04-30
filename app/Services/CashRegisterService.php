<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CommissionSettlement;
use App\Models\Payment;
use App\Models\ProductSale;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CashRegisterService
{
    /**
     * Get the cash register for a company and date.
     */
    public function registerForDate(int $companyId, CarbonInterface $date): ?CashRegister
    {
        return CashRegister::query()
            ->with('movements')
            ->where('company_id', $companyId)
            ->whereDate('date', $date->toDateString())
            ->first();
    }

    /**
     * Open the cash register for a given date.
     */
    public function open(User $user, float $openingAmount, ?string $notes = null, ?CarbonInterface $date = null): CashRegister
    {
        $date ??= now();

        return DB::transaction(function () use ($user, $openingAmount, $notes, $date): CashRegister {
            $register = CashRegister::query()
                ->where('company_id', $user->company_id)
                ->whereDate('date', $date->toDateString())
                ->lockForUpdate()
                ->first();

            if ($register) {
                return $register->load('movements');
            }

            return CashRegister::create([
                'company_id' => $user->company_id,
                'date' => $date->toDateString(),
                'opening_amount' => round($openingAmount, 2),
                'opened_by' => $user->id,
                'opened_at' => now(),
                'notes' => $notes,
            ])->load('movements');
        });
    }

    /**
     * Close the cash register.
     */
    public function close(CashRegister $register, User $user, ?float $closingAmount = null, ?string $notes = null): CashRegister
    {
        $register->update([
            'closing_amount' => $closingAmount !== null ? round($closingAmount, 2) : $register->expectedBalance(),
            'closed_by' => $user->id,
            'closed_at' => now(),
            'notes' => $notes ?: $register->notes,
        ]);

        return $register->fresh('movements');
    }

    /**
     * Ensure there is a register for the given day before recording movement.
     */
    public function ensureRegister(int $companyId, CarbonInterface $occurredAt, ?int $userId = null): CashRegister
    {
        $existing = CashRegister::query()
            ->where('company_id', $companyId)
            ->whereDate('date', $occurredAt->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        return CashRegister::create([
            'company_id' => $companyId,
            'date' => $occurredAt->toDateString(),
            'opening_amount' => 0,
            'opened_by' => $userId,
            'opened_at' => $occurredAt,
            'notes' => 'Caixa aberto automaticamente a partir da primeira movimentacao do dia.',
        ]);
    }

    /**
     * Record a movement inside the register of that day.
     */
    public function recordMovement(
        int $companyId,
        CarbonInterface $occurredAt,
        string $type,
        float $amount,
        string $description,
        ?string $paymentMethod = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?int $userId = null,
    ): CashMovement {
        $register = $this->ensureRegister($companyId, $occurredAt, $userId);

        return CashMovement::create([
            'company_id' => $companyId,
            'cash_register_id' => $register->id,
            'type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'payment_method' => $paymentMethod,
            'amount' => round($amount, 2),
            'occurred_at' => $occurredAt,
            'description' => $description,
        ]);
    }

    /**
     * Register service payment as cash inflow.
     */
    public function recordPayment(Payment $payment): CashMovement
    {
        return $this->recordMovement(
            $payment->company_id,
            $payment->paid_at,
            CashMovement::TYPE_INFLOW,
            (float) $payment->gross_amount,
            'Recebimento de servico - ' . ($payment->client->name ?? 'Cliente'),
            $payment->payment_method,
            Payment::class,
            $payment->id,
            $payment->user_id,
        );
    }

    /**
     * Register product sale as cash inflow.
     */
    public function recordProductSale(ProductSale $sale): CashMovement
    {
        return $this->recordMovement(
            $sale->company_id,
            $sale->sold_at,
            CashMovement::TYPE_INFLOW,
            (float) $sale->gross_amount,
            'Venda de produto - ' . ($sale->client->name ?? 'Cliente'),
            $sale->payment_method,
            ProductSale::class,
            $sale->id,
            $sale->user_id,
        );
    }

    /**
     * Register commission settlement as cash outflow.
     */
    public function recordCommissionSettlement(CommissionSettlement $settlement): CashMovement
    {
        return $this->recordMovement(
            $settlement->company_id,
            $settlement->paid_at,
            CashMovement::TYPE_OUTFLOW,
            (float) $settlement->commission_amount,
            'Acerto de comissao - ' . ($settlement->user->name ?? 'Profissional'),
            $settlement->payment_method,
            CommissionSettlement::class,
            $settlement->id,
            $settlement->created_by,
        );
    }
}
