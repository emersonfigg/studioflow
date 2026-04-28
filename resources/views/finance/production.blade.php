<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Financeiro</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Producao por barbeiro
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
            <h3 class="text-base font-semibold text-white">Resumo por profissional</h3>
            <p class="mt-1 text-sm text-[#c7d2e3]">
                Periodo de {{ $from->format('d/m/Y') }} ate {{ $to->format('d/m/Y') }}.
            </p>
        </div>

        @if ($rows->isEmpty())
            <div class="px-6 py-10 text-sm text-[#c7d2e3]">
                Nenhum pagamento encontrado no periodo selecionado.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10">
                    <thead class="bg-[#132746]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Profissional</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Atendimentos concluidos</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Receita bruta</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Comissao</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Liquido da empresa</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Ticket medio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/8 bg-[#223d69]">
                        @foreach ($rows as $row)
                            <tr class="transition hover:bg-white/[0.03]">
                                <td class="px-6 py-4 text-sm font-semibold text-white">{{ $row['user']->name }}</td>
                                <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $row['completed_appointments'] }}</td>
                                <td class="px-6 py-4 text-sm text-[#c7d2e3]">R$ {{ number_format($row['gross_amount'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm text-[#c7d2e3]">R$ {{ number_format($row['commission_amount'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm text-[#c7d2e3]">R$ {{ number_format($row['net_amount'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm text-[#d4af37]">R$ {{ number_format($row['average_ticket'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-app-layout>
