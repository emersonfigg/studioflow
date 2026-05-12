<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Relatórios</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Relatório de serviços</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Faturamento por serviço em comandas pagas (sem produtos, sem canceladas).</p>
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
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Quantidade vendida</p>
            <p class="mt-3 text-3xl font-semibold text-white">{{ number_format($totalQuantity, 0, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Faturamento bruto</p>
            <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($totalGross, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Ticket médio (serviço)</p>
            <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Linhas no período</p>
            <p class="mt-3 text-3xl font-semibold text-white">{{ $rows->count() }}</p>
        </article>
    </section>

    <section class="sf-card mt-6 overflow-hidden">
        <div class="border-b border-white/10 px-6 py-5">
            <h3 class="text-base font-semibold text-white">Detalhamento</h3>
            <p class="mt-1 text-sm text-[#c7d2e3]">Somente itens de serviço em comandas com status “paga”.</p>
        </div>
        @if ($rows->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-[#c7d2e3]">
                Nenhum serviço vendido neste período com os filtros selecionados.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10">
                    <thead class="bg-[#132746]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Serviço</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Qtd</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Faturamento</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Ticket médio</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">% do total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/8 bg-[#223d69]">
                        @foreach ($rows as $row)
                            <tr class="transition hover:bg-white/[0.03]">
                                <td class="px-6 py-4 text-sm font-medium text-white">{{ $row['name'] }}</td>
                                <td class="px-6 py-4 text-right text-sm tabular-nums text-[#c7d2e3]">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-emerald-200">R$ {{ number_format($row['gross'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-right text-sm tabular-nums text-[#c7d2e3]">R$ {{ number_format($row['ticket'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-right text-sm tabular-nums text-[#d4af37]">{{ number_format($row['pct'], 2, ',', '.') }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-app-layout>
