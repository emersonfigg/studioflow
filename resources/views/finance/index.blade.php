<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Relatórios</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
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
            <p class="text-sm font-medium sf-text-muted">Receita bruta</p>
            <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">R$ {{ number_format($grossAmount, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm sf-text-muted">Total recebido no período.</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium sf-text-muted">Comissões</p>
            <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">R$ {{ number_format($commissionAmount, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm sf-text-muted">Repasse aos profissionais.</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium sf-text-muted">Liquido da empresa</p>
            <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">R$ {{ number_format($netAmount, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm sf-text-muted">Margem apos comissões.</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium sf-text-muted">Ticket medio</p>
            <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm sf-text-muted">{{ $appointmentsCount }} atendimentos pagos no período.</p>
        </article>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
        <aside class="sf-card p-5">
            <h3 class="text-base font-semibold text-[var(--text-main)]">Faturamento por forma</h3>
            <div class="mt-5 space-y-3">
                @forelse ($paymentMethodTotals as $method => $amount)
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm font-medium text-[var(--text-main)]">{{ ucfirst($method) }}</p>
                            <p class="text-sm font-semibold brand-text">R$ {{ number_format($amount, 2, ',', '.') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 bg-[var(--input-bg)] px-4 py-4 text-sm sf-text-muted">
                        Nenhum pagamento encontrado no período.
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Últimos recebimentos</h3>
                <p class="mt-1 text-sm sf-text-muted">Movimentacoes mais recentes registradas em caixa.</p>
            </div>

            @if ($recentPayments->isEmpty())
                <div class="px-6 py-10 text-sm sf-text-muted">
                    Nenhum pagamento encontrado no período selecionado.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Data</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Profissional</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Método</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Bruto</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Liquido</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                            @foreach ($recentPayments as $payment)
                                <tr class="transition hover:bg-white/[0.03]">
                                    <td class="px-6 py-4 text-sm sf-text-muted">{{ $payment->paid_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-[var(--text-main)]">{{ $payment->client->name }}</td>
                                    <td class="px-6 py-4 text-sm sf-text-muted">{{ $payment->user->name }}</td>
                                    <td class="px-6 py-4 text-sm sf-text-muted">{{ ucfirst($payment->payment_method) }}</td>
                                    <td class="px-6 py-4 text-sm sf-text-muted">R$ {{ number_format((float) $payment->gross_amount, 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-sm brand-text">R$ {{ number_format((float) $payment->net_amount, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
