<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Relatórios</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                    Produção por barbeiro
                </h2>
            </div>

            <form method="GET" action="{{ route('finance.production') }}" class="flex flex-wrap items-center gap-3">
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

    <section class="sf-card overflow-hidden">
        <div class="border-b border-white/10 px-6 py-5">
            <h3 class="text-base font-semibold text-[var(--text-main)]">Resumo por profissional</h3>
            <p class="mt-1 text-sm sf-text-muted">
                Periodo de {{ $from->format('d/m/Y') }} até {{ $to->format('d/m/Y') }}.
            </p>
        </div>

        @if ($rows->isEmpty())
            <div class="px-6 py-10 text-sm sf-text-muted">
                Nenhum pagamento encontrado no período selecionado.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10">
                    <thead class="bg-[var(--input-bg)]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Profissional</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Atendimentos concluídos</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Receita bruta</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Comissão</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Liquido da empresa</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Ticket medio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                        @foreach ($rows as $row)
                            <tr class="transition hover:bg-white/[0.03]">
                                <td class="px-6 py-4 text-sm font-semibold text-[var(--text-main)]">{{ $row['user']->name }}</td>
                                <td class="px-6 py-4 text-sm sf-text-muted">{{ $row['completed_appointments'] }}</td>
                                <td class="px-6 py-4 text-sm sf-text-muted">R$ {{ number_format($row['gross_amount'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm sf-text-muted">R$ {{ number_format($row['commission_amount'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm sf-text-muted">R$ {{ number_format($row['net_amount'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm brand-text">R$ {{ number_format($row['average_ticket'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-app-layout>
