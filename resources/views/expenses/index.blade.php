<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="sf-page-eyebrow">Financeiro</p>
                <h2 class="sf-page-title mt-2">Despesas</h2>
                <p class="sf-page-subtitle mt-2">Controle despesas avulsas e recorrentes da empresa.</p>
            </div>
            <a href="{{ route('finance.index') }}" class="sf-button-ghost">Voltar</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-3 sm:grid-cols-4">
            @foreach (['month' => 'Despesas do mes', 'paid' => 'Pagas', 'pending' => 'Pendentes', 'overdue' => 'Atrasadas'] as $key => $label)
                <div class="sf-card p-4">
                    <p class="sf-page-eyebrow">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-black brand-text">R$ {{ number_format((float) $summary[$key], 2, ',', '.') }}</p>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('expenses.store') }}" class="sf-card grid gap-4 p-5 lg:grid-cols-6">
            @csrf
            <div class="lg:col-span-2">
                <label class="sf-label">Descricao</label>
                <input name="description" class="sf-input mt-1 w-full" required>
            </div>
            <div>
                <label class="sf-label">Valor</label>
                <input name="amount" type="number" min="0.01" step="0.01" class="sf-input mt-1 w-full" required>
            </div>
            <div>
                <label class="sf-label">Vencimento</label>
                <input name="due_date" type="date" class="sf-input mt-1 w-full" required>
            </div>
            <div>
                <label class="sf-label">Categoria</label>
                <input name="category_name" list="expense-categories" class="sf-input mt-1 w-full" placeholder="Aluguel, internet...">
                <datalist id="expense-categories">
                    @foreach ($categories as $category)
                        <option value="{{ $category->name }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div>
                <label class="sf-label">Recorrencia</label>
                <select name="recurrence" class="sf-select mt-1 w-full">
                    <option value="none">Nao recorrente</option>
                    <option value="monthly">Mensal</option>
                </select>
            </div>
            <div>
                <label class="sf-label">Forma pag.</label>
                <select name="payment_method" class="sf-select mt-1 w-full">
                    <option value="">Pendente</option>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="sf-label">Pago em</label>
                <input name="paid_at" type="datetime-local" class="sf-input mt-1 w-full">
            </div>
            <div class="lg:col-span-4">
                <label class="sf-label">Observacoes</label>
                <input name="notes" class="sf-input mt-1 w-full">
            </div>
            <div class="lg:col-span-6">
                <button class="sf-button-primary">Lancar despesa</button>
            </div>
        </form>

        <div class="sf-card overflow-x-auto p-0">
            <table class="min-w-full text-sm">
                <thead class="border-b border-white/10 text-left sf-muted">
                    <tr>
                        <th class="p-3">Descricao</th>
                        <th class="p-3">Categoria</th>
                        <th class="p-3">Vencimento</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Valor</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expenses as $expense)
                        <tr class="border-b border-white/5">
                            <td class="p-3">{{ $expense->description }}</td>
                            <td class="p-3">{{ $expense->category?->name ?? '-' }}</td>
                            <td class="p-3">{{ $expense->due_date?->format('d/m/Y') }}</td>
                            <td class="p-3">{{ \App\Models\Expense::statusLabel($expense->status) }}</td>
                            <td class="p-3 text-right">R$ {{ number_format((float) $expense->amount, 2, ',', '.') }}</td>
                            <td class="p-3 text-right">
                                @if ($expense->status !== \App\Models\Expense::STATUS_PAID)
                                    <form method="POST" action="{{ route('expenses.mark-paid', $expense) }}" class="inline-flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="payment_method" class="sf-select !py-1 text-xs" required>
                                            @foreach ($paymentMethods as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="sf-button-secondary !py-1 text-xs">Marcar paga</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-4">{{ $expenses->links() }}</div>
        </div>
    </div>
</x-app-layout>
