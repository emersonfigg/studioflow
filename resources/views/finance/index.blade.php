<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Relatórios</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Painel de faturamento
                </h2>
            </div>

            <form method="GET" action="{{ route('finance.index') }}" class="flex flex-wrap items-center gap-3">
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="sf-input min-w-[170px]">
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="sf-input min-w-[170px]">
                @if ($canFilterProfessionals)
                    <select name="user_id" class="sf-select min-w-[220px]">
                        <option value="">Todos os profissionais</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($selectedUserId === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="sf-button-ghost">Filtrar</button>
            </form>
        </div>
    </x-slot>

    @include('finance.partials.nav', ['page' => $page])

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium text-[#c7d2e3]">Receita bruta</p>
            <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format($grossAmount, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm text-[#c7d2e3]">Total recebido no período.</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium text-[#c7d2e3]">Comissões</p>
            <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format($commissionAmount, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm text-[#c7d2e3]">Repasse aos profissionais.</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium text-[#c7d2e3]">Liquido da empresa</p>
            <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format($netAmount, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm text-[#c7d2e3]">Margem apos comissões.</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium text-[#c7d2e3]">Ticket medio</p>
            <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm text-[#c7d2e3]">{{ $appointmentsCount }} atendimentos pagos no período.</p>
        </article>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
        <aside class="sf-card p-5">
            <h3 class="text-base font-semibold text-white">Faturamento por forma</h3>
            <div class="mt-5 space-y-3">
                @forelse ($paymentMethodTotals as $method => $amount)
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm font-medium text-white">{{ ucfirst($method) }}</p>
                            <p class="text-sm font-semibold text-[#d4af37]">R$ {{ number_format($amount, 2, ',', '.') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-4 text-sm text-[#c7d2e3]">
                        Nenhum pagamento encontrado no período.
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-white">Últimos recebimentos</h3>
                <p class="mt-1 text-sm text-[#c7d2e3]">Movimentacoes mais recentes registradas em caixa.</p>
            </div>

            @if ($recentPayments->isEmpty())
                <div class="px-6 py-10 text-sm text-[#c7d2e3]">
                    Nenhum pagamento encontrado no período selecionado.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[#132746]">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Data</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Barbeiro</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Método</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Bruto</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Liquido</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8 bg-[#223d69]">
                            @foreach ($recentPayments as $payment)
                                <tr class="transition hover:bg-white/[0.03]">
                                    <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $payment->paid_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-white">{{ $payment->client->name }}</td>
                                    <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $payment->user->name }}</td>
                                    <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ ucfirst($payment->payment_method) }}</td>
                                    <td class="px-6 py-4 text-sm text-[#c7d2e3]">R$ {{ number_format((float) $payment->gross_amount, 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-sm text-[#d4af37]">R$ {{ number_format((float) $payment->net_amount, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
