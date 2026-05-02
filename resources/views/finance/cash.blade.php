<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Financeiro</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Caixa diário</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Abra, acompanhe e feche o caixa do dia com saldo esperado e movimentos reais.</p>
            </div>

            <form method="GET" action="{{ route('finance.cash') }}" class="flex flex-wrap items-center gap-3">
                <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="sf-input min-w-[170px]">
                <button class="sf-button-ghost">Ver dia</button>
            </form>
        </div>
    </x-slot>

    @include('finance.partials.nav', ['page' => $page])

    <div class="space-y-6">
        @if (session('status') === 'cash-opened')
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                Caixa aberto com sucesso.
            </div>
        @elseif (session('status') === 'cash-closed')
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                Caixa fechado com sucesso.
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Status</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ $register ? ($register->closed_at ? 'Fechado' : 'Aberto') : 'Não aberto' }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Abertura</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format((float) ($register?->opening_amount ?? 0), 2, ',', '.') }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Entradas - saídas</p>
                <p class="mt-3 text-3xl font-semibold text-white">
                    R$ {{ number_format($register ? ($register->inflowsTotal() - $register->outflowsTotal()) : 0, 2, ',', '.') }}
                </p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Saldo esperado</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($register?->expectedBalance() ?? 0, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
            <aside class="sf-card p-5">
                @if (! $register)
                    <h3 class="text-lg font-semibold text-white">Abrir caixa</h3>
                    <form method="POST" action="{{ route('finance.cash.open') }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                        <div>
                            <x-input-label for="opening_amount" value="Valor de abertura" />
                            <x-text-input id="opening_amount" name="opening_amount" type="text" inputmode="decimal" placeholder="R$ 0,00" class="mt-2 block w-full" :value="old('opening_amount', '0,00')" required />
                        </div>
                        <div>
                            <x-input-label for="notes" value="Observações" />
                            <textarea id="notes" name="notes" rows="4" class="sf-input mt-2 block w-full">{{ old('notes') }}</textarea>
                        </div>
                        <button class="sf-button-primary w-full">Abrir caixa</button>
                    </form>
                @else
                    <h3 class="text-lg font-semibold text-white">Resumo operacional</h3>
                    <dl class="mt-5 space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Aberto em</dt>
                            <dd class="text-sm font-semibold text-white">{{ $register->opened_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Entradas</dt>
                            <dd class="text-sm font-semibold text-emerald-200">R$ {{ number_format($register->inflowsTotal(), 2, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Saídas</dt>
                            <dd class="text-sm font-semibold text-rose-200">R$ {{ number_format($register->outflowsTotal(), 2, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Fechado em</dt>
                            <dd class="text-sm font-semibold text-white">{{ $register->closed_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                        </div>
                    </dl>

                    @if (! $register->closed_at && auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('finance.cash.close') }}" class="mt-6 space-y-4">
                            @csrf
                            <input type="hidden" name="cash_register_id" value="{{ $register->id }}">
                            <div>
                                <x-input-label for="closing_amount" value="Saldo final conferido" />
                                <x-text-input id="closing_amount" name="closing_amount" type="text" inputmode="decimal" placeholder="R$ 0,00" class="mt-2 block w-full" :value="old('closing_amount', \App\Support\BrazilianCurrency::input($register->expectedBalance()))" />
                            </div>
                            <div>
                                <x-input-label for="closing_notes" value="Observações do fechamento" />
                                <textarea id="closing_notes" name="notes" rows="4" class="sf-input mt-2 block w-full">{{ old('notes') }}</textarea>
                            </div>
                            <button class="sf-button-primary w-full">Fechar caixa</button>
                        </form>
                    @endif
                @endif
            </aside>

            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-base font-semibold text-white">Movimentos do dia</h3>
                    <p class="mt-1 text-sm text-[#c7d2e3]">Pagamentos de serviços, vendas de produtos e acertos saem daqui.</p>
                </div>
                @if (! $register || $register->movements->isEmpty())
                    <div class="px-6 py-10 text-sm text-[#c7d2e3]">Nenhuma movimentação registrada neste dia.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[#132746]">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Horário</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Descrição</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Método</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/8 bg-[#223d69]">
                                @foreach ($register->movements->sortByDesc('occurred_at') as $movement)
                                    <tr class="transition hover:bg-white/[0.03]">
                                        <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $movement->occurred_at->format('H:i') }}</td>
                                        <td class="px-6 py-4 text-sm text-white">{{ $movement->description }}</td>
                                        <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $movement->payment_method ? ucfirst($movement->payment_method) : '-' }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] {{ $movement->type === \App\Models\CashMovement::TYPE_INFLOW ? 'bg-emerald-500/10 text-emerald-100 ring-1 ring-emerald-400/20' : 'bg-rose-500/10 text-rose-100 ring-1 ring-rose-400/20' }}">
                                                {{ $movement->type === \App\Models\CashMovement::TYPE_INFLOW ? 'Entrada' : 'Saida' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-semibold {{ $movement->type === \App\Models\CashMovement::TYPE_INFLOW ? 'text-emerald-200' : 'text-rose-200' }}">
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
