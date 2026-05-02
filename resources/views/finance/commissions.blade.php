<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Financeiro</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Comissões
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

    @if (session('status') === 'commission-settlement-created')
        <div class="mb-6 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
            Acerto registrado com sucesso.
        </div>
    @elseif (session('status') === 'commission-settlement-empty')
        <div class="mb-6 rounded-2xl border border-amber-400/20 bg-amber-400/10 px-5 py-4 text-sm text-amber-50">
            Não há comissão pendente para o período selecionado.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-white">Resumo de comissões por profissional</h3>
                <p class="mt-1 text-sm text-[#c7d2e3]">
                    Veja pendências, repasses já pagos e total produzido no período filtrado.
                </p>
            </div>

            @if ($rows->isEmpty())
                <div class="px-6 py-10 text-sm text-[#c7d2e3]">
                    Nenhuma comissão encontrada no período selecionado.
                </div>
            @else
                <div class="divide-y divide-white/10 bg-[#223d69]">
                    @foreach ($rows as $row)
                        <article class="grid gap-4 px-6 py-5 transition hover:bg-white/[0.03] lg:grid-cols-[minmax(0,1.2fr)_repeat(4,minmax(0,0.7fr))_auto] lg:items-center">
                            <div>
                                <p class="text-base font-semibold text-white">{{ $row['user']->name }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">
                                    @if ($row['commission_type'] === 'percent')
                                        Regra percentual de {{ number_format((float) $row['commission_raté'], 2, ',', '.') }}%
                                    @elseif ($row['commission_type'] === 'fixed')
                                        Regra fixa por atendimento
                                    @else
                                        Sem comissão configurada
                                    @endif
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Pendente</p>
                                <p class="mt-2 text-sm font-semibold text-[#d4af37]">R$ {{ number_format($row['pending_commission_amount'], 2, ',', '.') }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Já pago</p>
                                <p class="mt-2 text-sm font-semibold text-white">R$ {{ number_format($row['paid_commission_amount'], 2, ',', '.') }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Produzido</p>
                                <p class="mt-2 text-sm font-semibold text-white">R$ {{ number_format($row['gross_amount'], 2, ',', '.') }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Serviços</p>
                                <p class="mt-2 text-sm font-semibold text-white">{{ $row['services_count'] }}</p>
                            </div>

                            <div class="lg:text-right">
                                @if ($canFilterProfessionals && $row['can_settle'])
                                    <a
                                        href="{{ route('finance.commissions.settlements.create', ['user_id' => $row['user']->id, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}"
                                        class="sf-button-primary"
                                    >
                                        Fazer acerto
                                    </a>
                                @elseif ($row['pending_commission_amount'] <= 0)
                                    <span class="inline-flex rounded-full border border-white/10 bg-[#132746] px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">
                                        Em dia
                                    </span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="sf-card p-5">
            <h3 class="text-base font-semibold text-white">Últimos repasses</h3>
            <div class="mt-5 space-y-3">
                @forelse ($recentSettlements as $settlement)
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $settlement->user->name }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">
                                    {{ $settlement->start_date->format('d/m/Y') }} até {{ $settlement->end_date->format('d/m/Y') }}
                                </p>
                                <p class="mt-1 text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">{{ $settlement->paid_at->format('d/m/Y H:i') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-[#d4af37]">R$ {{ number_format((float) $settlement->commission_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs text-[#c7d2e3]">
                                    {{ match ($settlement->payment_method) {
                                        'cash' => 'Dinheiro',
                                        'pix' => 'Pix',
                                        'bank_transfer' => 'Transferência',
                                        default => ucfirst($settlement->payment_method),
                                    } }}
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-4 text-sm text-[#c7d2e3]">
                        Nenhum repasse encontrado no período.
                    </div>
                @endforelse
            </div>
        </aside>
    </div>
</x-app-layout>
