<x-app-layout>
    @php
        $openingAmount = (float) ($register?->opening_amount ?? 0);
        $inflowsTotal = $register ? $register->inflowsTotal() : 0;
        $outflowsTotal = $register ? $register->outflowsTotal() : 0;
        $netMovements = $inflowsTotal - $outflowsTotal;
        $expectedBalance = $register?->expectedBalance() ?? 0;
    @endphp

    @include('finance.partials.nav', ['page' => $page])

    <div class="cash-daily-shell space-y-4">
        <div class="cash-page-toolbar">
            <div class="min-w-0">
                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] brand-text">Relatorios</p>
                <h2 class="mt-0.5 text-xl font-semibold tracking-tight text-[var(--text-main)]">Caixa diario</h2>
            </div>

            <form method="GET" action="{{ route('finance.cash') }}" class="cash-date-form flex items-center gap-2">
                <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="sf-input h-9 min-w-[145px] px-3 py-1.5 text-sm">
                <button class="sf-button-ghost h-9 min-w-[76px] whitespace-nowrap px-3 py-1.5">Ver dia</button>
            </form>
        </div>

        @if (session('status') === 'cash-opened')
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                Caixa aberto com sucesso.
            </div>
        @elseif (session('status') === 'cash-closed')
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                Caixa fechado com sucesso.
            </div>
        @elseif (session('status') === 'cash-outflow-registered')
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                Saida registrada no caixa.
            </div>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card cash-summary-card">
                <p class="cash-summary-label">Status</p>
                <p class="cash-summary-value text-xl">{{ $register ? ($register->closed_at ? 'Fechado' : 'Aberto') : 'Nao aberto' }}</p>
            </article>
            <article class="sf-card cash-summary-card">
                <p class="cash-summary-label">Abertura</p>
                <p class="cash-summary-value">R$ {{ number_format($openingAmount, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card cash-summary-card">
                <p class="cash-summary-label">Entradas - saidas</p>
                <p class="cash-summary-value {{ $netMovements < 0 ? 'text-rose-100' : 'text-[var(--text-main)]' }}">R$ {{ number_format($netMovements, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card cash-summary-card">
                <p class="cash-summary-label">Saldo esperado</p>
                <p class="cash-summary-value {{ $expectedBalance < 0 ? 'text-rose-100' : 'text-[var(--text-main)]' }}">R$ {{ number_format($expectedBalance, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="cash-workspace grid gap-4 lg:grid-cols-[minmax(300px,320px)_minmax(0,1fr)]">
            <aside class="sf-card cash-sidebar">
                @if (! $register)
                    <div class="cash-sidebar-body">
                        <h3 class="text-base font-semibold text-[var(--text-main)]">Abrir caixa</h3>
                        <form method="POST" action="{{ route('finance.cash.open') }}" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                            <div>
                                <x-input-label for="opening_amount" value="Valor de abertura" />
                                <x-text-input id="opening_amount" name="opening_amount" type="text" inputmode="decimal" placeholder="R$ 0,00" class="cash-control mt-1 block w-full" :value="old('opening_amount', '0,00')" required />
                            </div>
                            <div>
                                <x-input-label for="notes" value="Observacoes" />
                                <textarea id="notes" name="notes" rows="3" class="sf-input cash-control mt-1 block w-full">{{ old('notes') }}</textarea>
                            </div>
                            <button class="sf-button-primary cash-sticky-button w-full">Abrir caixa</button>
                        </form>
                    </div>
                @else
                    <div class="cash-sidebar-body">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-base font-semibold text-[var(--text-main)]">Resumo operacional</h3>
                            <span class="rounded-full border border-white/10 bg-[var(--input-bg)] px-2.5 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.12em] sf-text-muted">
                                {{ $date->format('d/m') }}
                            </span>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-2">
                            <div class="cash-mini-stat">
                                <dt>Aberto</dt>
                                <dd>{{ $register->opened_at?->format('H:i') ?? '-' }}</dd>
                            </div>
                            <div class="cash-mini-stat">
                                <dt>Fechado</dt>
                                <dd>{{ $register->closed_at?->format('H:i') ?? '-' }}</dd>
                            </div>
                            <div class="cash-mini-stat">
                                <dt>Entradas</dt>
                                <dd class="text-emerald-200">R$ {{ number_format($inflowsTotal, 2, ',', '.') }}</dd>
                            </div>
                            <div class="cash-mini-stat">
                                <dt>Saidas</dt>
                                <dd class="text-rose-200">R$ {{ number_format($outflowsTotal, 2, ',', '.') }}</dd>
                            </div>
                        </dl>

                        @if (! $register->closed_at && auth()->user()->hasFinancialPrivileges())
                            <div class="mt-4 rounded-xl border border-white/10 bg-[var(--input-bg)] p-3">
                                <h4 class="text-sm font-semibold text-[var(--text-main)]">Registrar saida manual</h4>
                                <form method="POST" action="{{ route('finance.cash.outflow') }}" class="mt-3 space-y-2.5">
                                    @csrf
                                    <input type="hidden" name="cash_register_id" value="{{ $register->id }}">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <x-input-label for="out_amount" value="Valor" />
                                            <x-text-input id="out_amount" name="amount" type="text" inputmode="decimal" class="cash-control mt-1 block w-full" :value="old('amount')" required />
                                        </div>
                                        <div>
                                            <x-input-label for="out_payment_method" value="Metodo" />
                                            <select id="out_payment_method" name="payment_method" class="sf-select cash-control mt-1 block w-full">
                                                <option value="">-</option>
                                                @foreach (\App\Models\Payment::paymentMethodOptions() as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label for="out_category" value="Categoria / motivo" />
                                        <x-text-input id="out_category" name="category" type="text" class="cash-control mt-1 block w-full" :value="old('category')" required />
                                    </div>
                                    <div>
                                        <x-input-label for="out_description" value="Descricao" />
                                        <textarea id="out_description" name="description" rows="2" class="sf-input cash-control mt-1 block w-full">{{ old('description') }}</textarea>
                                    </div>
                                    <button type="submit" class="sf-button-secondary h-10 w-full py-2">Registrar saida</button>
                                </form>
                                @error('amount')
                                    <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                @enderror
                                @error('cash_register_id')
                                    <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if (! $register->closed_at && auth()->user()->isAdmin())
                            <form id="cash-close-form" method="POST" action="{{ route('finance.cash.close') }}" class="cash-close-form cash-close-card mt-4">
                                @csrf
                                <input type="hidden" name="cash_register_id" value="{{ $register->id }}">
                                <div class="space-y-2.5">
                                    <div>
                                        <x-input-label for="closing_amount" value="Saldo final conferido" />
                                        <x-text-input id="closing_amount" name="closing_amount" type="text" inputmode="decimal" placeholder="R$ 0,00" class="cash-control mt-1 block w-full" :value="old('closing_amount', \App\Support\BrazilianCurrency::input($expectedBalance))" />
                                        <x-input-error :messages="$errors->get('closing_amount')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="closing_notes" value="Observacoes do fechamento" />
                                        <textarea id="closing_notes" name="notes" rows="4" class="sf-input cash-control cash-closing-notes mt-1 block w-full">{{ old('notes') }}</textarea>
                                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                                    </div>
                                    <x-input-error :messages="$errors->get('cash_register_id')" class="mt-2" />
                                </div>
                            </form>
                        @endif
                    </div>

                    @if (! $register->closed_at && auth()->user()->isAdmin())
                        <div class="cash-close-actions">
                            <button form="cash-close-form" class="sf-button-primary h-11 w-full py-2">Fechar caixa</button>
                        </div>
                    @endif
                @endif
            </aside>

            <section class="sf-card cash-movements-panel">
                <div class="cash-table-header">
                    <div>
                        <h3 class="text-base font-semibold text-[var(--text-main)]">Movimentos do dia</h3>
                        <p class="mt-0.5 text-xs sf-text-muted">Pagamentos, vendas, despesas e acertos registrados no caixa.</p>
                    </div>
                    <span class="rounded-full border border-white/10 bg-[var(--input-bg)] px-3 py-1 text-xs font-semibold sf-text-muted">
                        {{ $register ? $register->movements->count() : 0 }} movimentos
                    </span>
                </div>
                @if (! $register || $register->movements->isEmpty())
                    <div class="px-4 py-8 text-sm sf-text-muted">Nenhuma movimentacao registrada neste dia.</div>
                @else
                    <div class="cash-table-scroll">
                        <table class="cash-movements-table">
                            <thead>
                                <tr>
                                    <th class="w-[72px]">Horario</th>
                                    <th>Descricao</th>
                                    <th class="w-[140px]">Metodo</th>
                                    <th class="w-[110px]">Tipo</th>
                                    <th class="w-[124px] text-right">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($register->movements->sortByDesc('occurred_at') as $movement)
                                    <tr>
                                        <td class="sf-text-muted">{{ $movement->occurred_at->format('H:i') }}</td>
                                        <td class="font-medium text-[var(--text-main)]">{{ $movement->description }}</td>
                                        <td class="sf-text-muted">{{ $movement->payment_method ? \App\Models\Payment::labelForPaymentMethod($movement->payment_method) : '-' }}</td>
                                        <td>
                                            <span class="cash-type-badge {{ $movement->type === \App\Models\CashMovement::TYPE_INFLOW ? 'cash-type-inflow' : 'cash-type-outflow' }}">
                                                {{ $movement->type === \App\Models\CashMovement::TYPE_INFLOW ? 'Entrada' : 'Saida' }}
                                            </span>
                                        </td>
                                        <td class="text-right font-semibold {{ $movement->type === \App\Models\CashMovement::TYPE_INFLOW ? 'text-emerald-200' : 'text-rose-200' }}">
                                            R$ {{ number_format((float) $movement->amount, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </section>
    </div>
</x-app-layout>
