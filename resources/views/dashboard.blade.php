<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">
                    {{ auth()->user()->company?->name ?? 'Nenhuma empresa vinculada' }}
                </p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Painel
                </h2>
            </div>

            <p class="max-w-xl text-sm leading-6 text-[#c7d2e3]">
                Visão executiva da operação, dos atendimentos do dia e da produção financeira da empresa.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="sf-card overflow-hidden px-6 py-6 sm:px-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center rounded-full border border-[#d4af37]/20 bg-[#d4af37]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-[#d4af37]">
                        StudioFlow
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        Controle a agenda, a produção e a margem com clareza.
                    </h1>
                    <p class="mt-3 max-w-xl text-sm leading-7 text-[#c7d2e3]">
                        Um painel direto para acompanhar operação, receita, comissões e relacionamento com clientes em tempo real.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('appointments.create') }}" class="sf-button-primary min-w-[210px]">
                        <svg class="mr-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 4a.75.75 0 01.75.75v4.5h4.5a.75.75 0 010 1.5h-4.5v4.5a.75.75 0 01-1.5 0v-4.5h-4.5a.75.75 0 010-1.5h4.5v-4.5A.75.75 0 0110 4z"/>
                        </svg>
                        Novo agendamento
                    </a>
                    <a href="{{ route('clients.create') }}" class="sf-button-secondary min-w-[210px]">
                        <svg class="mr-2 h-4 w-4 text-[#d4af37]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM5 15.25A3.25 3.25 0 018.25 12h3.5A3.25 3.25 0 0115 15.25V16a.75.75 0 01-.75.75h-8.5A.75.75 0 015 16v-.75z"/>
                        </svg>
                        Novo cliente
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card-soft relative overflow-hidden p-5">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#d4af37] to-transparent"></div>
                <p class="text-sm font-medium text-[#c7d2e3]">Agendamentos Hoje</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">{{ $appointmentsToday }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Total do dia, excluindo cancelados.</p>
            </article>

            <article class="sf-card-soft relative overflow-hidden p-5">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#d4af37] to-transparent"></div>
                <p class="text-sm font-medium text-[#c7d2e3]">Próximos Atendimentos</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">{{ $upcomingAttendances }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Compromissos futuros já confirmados.</p>
            </article>

            <article class="sf-card-soft relative overflow-hidden p-5">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#d4af37] to-transparent"></div>
                <p class="text-sm font-medium text-[#c7d2e3]">Clientes</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">{{ $clientsCount }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Base cadastrada da empresa.</p>
            </article>

            <article class="sf-card-soft relative overflow-hidden p-5">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#d4af37] to-transparent"></div>
                <p class="text-sm font-medium text-[#c7d2e3]">Serviços</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">{{ $servicesCount }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Serviços ativos e operacionais.</p>
            </article>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Receita hoje</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format($revenueToday, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Total bruto recebido no dia.</p>
            </article>

            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Comissões hoje</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format($commissionsToday, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Repasse dos profissionais no dia.</p>
            </article>

            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Líquido hoje</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format($netToday, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Margem líquida da empresa hoje.</p>
            </article>

            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Atendimentos concluídos</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">{{ $completedToday }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Pagamentos registrados no dia.</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
            <aside class="sf-card p-5">
                <h3 class="text-base font-semibold text-white">Link público de agendamento</h3>
                <p class="mt-1 text-sm leading-6 text-[#c7d2e3]">
                    Compartilhe este acesso com clientes para receber pedidos sem login.
                </p>

                @if ($publicBookingUrl)
                    <div class="mt-5 rounded-2xl border border-[#d4af37]/20 bg-[#132746] p-5" x-data="{ copied: false }">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#d4af37]">Link da empresa</p>
                        <p class="mt-3 break-all text-sm leading-6 text-white">{{ $publicBookingUrl }}</p>

                        <button
                            type="button"
                            class="sf-button-primary mt-5 w-full"
                            x-on:click="
                                navigator.clipboard.writeText('{{ $publicBookingUrl }}');
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            "
                        >
                            <span x-show="! copied">Copiar link</span>
                            <span x-show="copied">Link copiado</span>
                        </button>
                    </div>
                @else
                    <div class="mt-5 rounded-2xl border border-dashed border-white/10 bg-[#132746] p-4 text-sm text-[#c7d2e3]">
                        Nenhuma empresa vinculada para gerar o link público.
                    </div>
                @endif

                <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <a href="{{ route('appointments.index') }}" class="sf-button-secondary">
                        Ver agenda
                    </a>
                    <a href="{{ route('production.index') }}" class="sf-button-ghost">
                        Ver produção
                    </a>
                </div>
            </aside>

            <section class="sf-card overflow-hidden">
                <div class="flex flex-col gap-2 border-b border-white/10 px-6 py-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-white">Últimos agendamentos do dia</h3>
                        <p class="mt-1 text-sm text-[#c7d2e3]">Acompanhe a movimentação mais recente da agenda de hoje.</p>
                    </div>
                    <p class="text-sm text-[#c7d2e3]">{{ now()->format('d/m/Y') }}</p>
                </div>

                @if ($todayAppointments->isEmpty())
                    <div class="px-6 py-10 text-sm text-[#c7d2e3]">
                        Nenhum agendamento registrado hoje.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[#132746]">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Horário</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Cliente</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Serviço</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Profissional</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/8 bg-[#223d69]">
                                @foreach ($todayAppointments as $appointment)
                                    <tr class="transition hover:bg-white/[0.03]">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-white">
                                            {{ $appointment->start_time->format('H:i') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $appointment->client->name }}</td>
                                        <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $appointment->service->name }}</td>
                                        <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $appointment->user->name }}</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                                {{ $appointment->statusLabel() }}
                                            </span>
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
