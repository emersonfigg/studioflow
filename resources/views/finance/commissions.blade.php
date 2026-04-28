<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Financeiro</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Comissoes
                </h2>
            </div>

            <form method="GET" action="{{ route('finance.commissions') }}" class="flex flex-wrap items-center gap-3">
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

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-white">Resumo de comissoes por profissional</h3>
                <p class="mt-1 text-sm text-[#c7d2e3]">
                    Analise por regra configurada, comissao efetiva e valor total repassado.
                </p>
            </div>

            @if ($rows->isEmpty())
                <div class="px-6 py-10 text-sm text-[#c7d2e3]">
                    Nenhuma comissao encontrada no periodo selecionado.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[#132746]">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Profissional</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Regra</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Servicos pagos</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Receita bruta</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Comissao</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Taxa efetiva</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8 bg-[#223d69]">
                            @foreach ($rows as $row)
                                <tr class="transition hover:bg-white/[0.03]">
                                    <td class="px-6 py-4 text-sm font-semibold text-white">{{ $row['user']->name }}</td>
                                    <td class="px-6 py-4 text-sm text-[#c7d2e3]">
                                        @if ($row['commission_type'] === 'percent')
                                            {{ number_format((float) $row['commission_rate'], 2, ',', '.') }}%
                                        @elseif ($row['commission_type'] === 'fixed')
                                            Valor fixo
                                        @else
                                            Sem comissao
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $row['services_count'] }}</td>
                                    <td class="px-6 py-4 text-sm text-[#c7d2e3]">R$ {{ number_format($row['gross_amount'], 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-sm text-[#d4af37]">R$ {{ number_format($row['commission_amount'], 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ number_format($row['effective_rate'], 2, ',', '.') }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <aside class="sf-card p-5">
            <h3 class="text-base font-semibold text-white">Ultimos repasses</h3>
            <div class="mt-5 space-y-3">
                @forelse ($recentCommissionPayments as $payment)
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $payment->user->name }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">{{ $payment->client->name }} • {{ $payment->service->name }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">{{ $payment->paid_at->format('d/m/Y H:i') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-[#d4af37]">R$ {{ number_format((float) $payment->commission_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs text-[#c7d2e3]">{{ ucfirst($payment->payment_method) }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-4 text-sm text-[#c7d2e3]">
                        Nenhum repasse encontrado no periodo.
                    </div>
                @endforelse
            </div>
        </aside>
    </div>
</x-app-layout>
