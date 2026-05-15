<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Relatórios</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Relatório de serviços</h2>
                <p class="mt-2 text-sm sf-text-muted">Faturamento por serviço em comandas pagas (sem produtos, sem canceladas).</p>
            </div>

            <form method="GET" action="{{ route('finance.service-report') }}" class="flex flex-wrap items-center gap-3">
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

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Quantidade vendida</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ number_format($totalQuantity, 0, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Faturamento bruto</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($totalGross, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Ticket médio (serviço)</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Linhas no período</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $rows->count() }}</p>
        </article>
    </section>

    <section class="sf-card mt-6 overflow-hidden">
        <div class="border-b border-white/10 px-6 py-5">
            <h3 class="text-base font-semibold text-[var(--text-main)]">Detalhamento</h3>
            <p class="mt-1 text-sm sf-text-muted">Somente itens de serviço em comandas com status “paga”.</p>
        </div>
        @if ($rows->isEmpty())
            <div class="px-6 py-12 text-center text-sm sf-text-muted">
                Nenhum serviço vendido neste período com os filtros selecionados.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10">
                    <thead class="bg-[var(--input-bg)]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Serviço</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Qtd</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Faturamento</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Ticket médio</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">% do total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                        @foreach ($rows as $row)
                            <tr class="transition hover:bg-white/[0.03]">
                                <td class="px-6 py-4 text-sm font-medium text-[var(--text-main)]">{{ $row['name'] }}</td>
                                <td class="px-6 py-4 text-right text-sm tabular-nums sf-text-muted">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-emerald-200">R$ {{ number_format($row['gross'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-right text-sm tabular-nums sf-text-muted">R$ {{ number_format($row['ticket'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-right text-sm tabular-nums brand-text">{{ number_format($row['pct'], 2, ',', '.') }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-app-layout>
