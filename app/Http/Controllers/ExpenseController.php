<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Services\CashRegisterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $expenses = Expense::query()
            ->where('company_id', $companyId)
            ->with('category')
            ->latest('due_date')
            ->paginate(20);

        $summaryBase = Expense::query()
            ->where('company_id', $companyId)
            ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        return view('expenses.index', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'paymentMethods' => Payment::paymentMethodOptions(),
            'summary' => [
                'month' => (clone $summaryBase)->sum('amount'),
                'paid' => (clone $summaryBase)->where('status', Expense::STATUS_PAID)->sum('amount'),
                'pending' => (clone $summaryBase)->where('status', Expense::STATUS_PENDING)->sum('amount'),
                'overdue' => (clone $summaryBase)->where('status', Expense::STATUS_PENDING)->whereDate('due_date', '<', now()->toDateString())->sum('amount'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $data = $request->validate([
            'category_name' => ['nullable', 'string', 'max:120'],
            'expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'recurrence' => ['required', Rule::in(Expense::RECURRENCES)],
            'payment_method' => ['nullable', Rule::in(Payment::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string'],
        ]);

        $categoryId = $data['expense_category_id'] ?? null;
        if (! $categoryId && filled($data['category_name'] ?? null)) {
            $categoryId = ExpenseCategory::query()->firstOrCreate(
                ['company_id' => $companyId, 'name' => trim((string) $data['category_name'])],
                ['active' => true],
            )->id;
        }

        $expense = Expense::query()->create([
            'company_id' => $companyId,
            'expense_category_id' => $categoryId,
            'description' => $data['description'],
            'amount' => round((float) $data['amount'], 2),
            'due_date' => $data['due_date'],
            'paid_at' => $data['paid_at'] ?? null,
            'status' => filled($data['paid_at'] ?? null) ? Expense::STATUS_PAID : Expense::STATUS_PENDING,
            'recurrence' => $data['recurrence'],
            'payment_method' => $data['payment_method'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
            'paid_by' => filled($data['paid_at'] ?? null) ? $request->user()->id : null,
        ]);

        if ($expense->status === Expense::STATUS_PAID) {
            $this->recordExpenseMovement($expense, $request->user()->id, app(CashRegisterService::class));
        }

        return back()->with('status', 'expense-created');
    }

    public function markPaid(Request $request, Expense $expense, CashRegisterService $cashRegisterService): RedirectResponse
    {
        abort_unless($expense->company_id === $request->user()->company_id, 404);

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'paid_at' => ['nullable', 'date'],
        ]);

        if ($expense->status !== Expense::STATUS_PAID) {
            $expense->update([
                'status' => Expense::STATUS_PAID,
                'paid_at' => $data['paid_at'] ?? now(),
                'payment_method' => $data['payment_method'],
                'paid_by' => $request->user()->id,
            ]);
        }

        $this->recordExpenseMovement($expense->fresh(), (int) $request->user()->id, $cashRegisterService);

        return back()->with('status', 'expense-paid');
    }

    private function recordExpenseMovement(Expense $expense, int $userId, CashRegisterService $cashRegisterService): void
    {
        $exists = CashMovement::query()
            ->where('company_id', $expense->company_id)
            ->where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->exists();

        if ($exists) {
            return;
        }

        $cashRegisterService->recordMovement(
            (int) $expense->company_id,
            $expense->paid_at ?? now(),
            CashMovement::TYPE_OUTFLOW,
            (float) $expense->amount,
            'Despesa - '.$expense->description,
            $expense->payment_method,
            Expense::class,
            $expense->id,
            $userId,
        );
    }
}
