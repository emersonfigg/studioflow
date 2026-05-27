<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">
                    {{ auth()->user()->company?->name ?? 'Nenhuma empresa vinculada' }}
                </p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                    Painel
                </h2>
            </div>

            <p class="max-w-xl text-sm leading-6 sf-text-muted">
                Visão executiva da operação, dos atendimentos do dia e da produção financeira da empresa.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="sf-card overflow-hidden px-6 py-6 sm:px-8">
            <div class="flex flex-col gap-6 2xl:flex-row 2xl:items-end 2xl:justify-between">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center rounded-full border border-[color-mix(in_srgb,var(--brand-primary)_20%,transparent)] bg-[var(--brand-primary)]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] brand-text">
                        StudioFlow
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-[var(--text-main)] sm:text-4xl">
                        Controle a agenda, a produção e a margem com clareza.
                    </h1>
                    <p class="mt-3 max-w-xl text-sm leading-7 sf-text-muted">
                        Um painel direto para acompanhar operação, receita, comissões e relacionamento com clientes em tempo real.
                    </p>
                </div>

                <div class="flex w-full flex-col gap-3 sm:flex-row 2xl:w-auto 2xl:shrink-0">
                    <a href="{{ route('appointments.create') }}" class="sf-button-primary w-full whitespace-nowrap sm:w-auto sm:min-w-[210px]">
                        <svg class="mr-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 4a.75.75 0 01.75.75v4.5h4.5a.75.75 0 010 1.5h-4.5v4.5a.75.75 0 01-1.5 0v-4.5h-4.5a.75.75 0 010-1.5h4.5v-4.5A.75.75 0 0110 4z"/>
                        </svg>
                        Novo agendamento
                    </a>
                    <a href="{{ route('clients.create') }}" class="sf-button-secondary w-full whitespace-nowrap sm:w-auto sm:min-w-[210px]">
                        <svg class="mr-2 h-4 w-4 brand-text" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM5 15.25A3.25 3.25 0 018.25 12h3.5A3.25 3.25 0 0115 15.25V16a.75.75 0 01-.75.75h-8.5A.75.75 0 015 16v-.75z"/>
                        </svg>
                        Novo cliente
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card-soft relative overflow-hidden p-5">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--brand-primary)] to-transparent"></div>
                <p class="text-sm font-medium sf-text-muted">Agendamentos Hoje</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">{{ $appointmentsToday }}</p>
                <p class="mt-2 text-sm sf-text-muted">Total do dia, excluindo cancelados.</p>
            </article>

            <article class="sf-card-soft relative overflow-hidden p-5">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--brand-primary)] to-transparent"></div>
                <p class="text-sm font-medium sf-text-muted">Próximos Atendimentos</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">{{ $upcomingAttendances }}</p>
                <p class="mt-2 text-sm sf-text-muted">Compromissos futuros já confirmados.</p>
            </article>

            <article class="sf-card-soft relative overflow-hidden p-5">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--brand-primary)] to-transparent"></div>
                <p class="text-sm font-medium sf-text-muted">Clientes</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">{{ $clientsCount }}</p>
                <p class="mt-2 text-sm sf-text-muted">Base cadastrada da empresa.</p>
            </article>

            <article class="sf-card-soft relative overflow-hidden p-5">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--brand-primary)] to-transparent"></div>
                <p class="text-sm font-medium sf-text-muted">Serviços</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">{{ $servicesCount }}</p>
                <p class="mt-2 text-sm sf-text-muted">Serviços ativos e operacionais.</p>
            </article>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium sf-text-muted">Receita hoje</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">R$ {{ number_format($revenueToday, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm sf-text-muted">Total bruto recebido no dia.</p>
            </article>

            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium sf-text-muted">Comissões hoje</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">R$ {{ number_format($commissionsToday, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm sf-text-muted">Repasse dos profissionais no dia.</p>
            </article>

            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium sf-text-muted">Líquido hoje</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">R$ {{ number_format($netToday, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm sf-text-muted">Margem líquida da empresa hoje.</p>
            </article>

            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium sf-text-muted">Atendimentos concluídos</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">{{ $completedToday }}</p>
                <p class="mt-2 text-sm sf-text-muted">Pagamentos registrados no dia.</p>
            </article>
        </section>

        @if ($todayBirthdayClients->isNotEmpty() && $company)
            <x-birthday-congratulations
                :clients="$todayBirthdayClients"
                :company="$company"
                :message-template="$birthdayCongratulationsMessage"
                :save-url="auth()->user()->isAdmin() ? route('company.birthday-message.update') : null"
                :can-save="auth()->user()->isAdmin()"
            />
        @endif

        @if ($lowStockProducts->isNotEmpty())
            <section class="sf-card border border-amber-500/30 p-5 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-200">Estoque</p>
                        <h3 class="mt-2 text-lg font-semibold text-[var(--text-main)]">Produtos com estoque baixo</h3>
                        <p class="mt-1 text-sm sf-text-muted">Itens em ou abaixo do mínimo configurado e com alerta ativo.</p>
                    </div>
                    <a href="{{ route('products.index') }}" class="sf-button-ghost shrink-0 text-sm">Ver catálogo</a>
                </div>
                <ul class="mt-4 divide-y divide-white/10 rounded-xl border border-white/10">
                    @foreach ($lowStockProducts as $product)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                            <div>
                                <p class="font-semibold text-[var(--text-main)]">{{ $product->name }}</p>
                                <p class="text-xs sf-text-muted">Atual: {{ $product->stock_quantity }} · Mínimo: {{ $product->minimum_stock }}</p>
                            </div>
                            <a href="{{ route('products.show', $product) }}" class="brand-text text-sm font-semibold hover:underline">Ajustar</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="grid gap-6 xl:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
            <aside class="sf-card p-5">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Link público de agendamento</h3>
                <p class="mt-1 text-sm leading-6 sf-text-muted">
                    Compartilhe este acesso com clientes para receber pedidos sem login.
                </p>

                @if ($publicBookingUrl)
                    <div class="mt-5 rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_20%,transparent)] bg-[var(--input-bg)] p-5" x-data="{ copied: false }">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] brand-text">Link da empresa</p>
                        <p class="mt-3 break-all text-sm leading-6 text-[var(--text-main)]">{{ $publicBookingUrl }}</p>

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
                    <div class="mt-5 rounded-2xl border border-dashed border-white/10 bg-[var(--input-bg)] p-4 text-sm sf-text-muted">
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
                        <h3 class="text-base font-semibold text-[var(--text-main)]">Ranking de vendas de produtos · {{ $rankingMonthLabel }}</h3>
                        <p class="mt-1 text-sm sf-text-muted">Top vendedores do mês com comissões acumuladas.</p>
                    </div>
                    <a href="{{ route('finance.product-commissions') }}" class="brand-text text-xs font-semibold uppercase tracking-[0.18em] hover:underline">Ver relatório completo</a>
                </div>

                @if ($sellerRanking->isEmpty())
                    <div class="px-6 py-10 text-sm sf-text-muted">
                        Nenhuma venda comissionada registrada no mês corrente.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[var(--input-bg)]">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Vendedor</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Itens</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Vendido</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Comissão</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                                @foreach ($sellerRanking as $index => $row)
                                    <tr class="transition hover:bg-white/[0.03]">
                                        <td class="px-6 py-3 text-sm font-mono brand-text">{{ $index + 1 }}º</td>
                                        <td class="px-6 py-3 text-sm font-semibold text-[var(--text-main)]">{{ $row->user_name }}</td>
                                        <td class="px-6 py-3 text-right text-sm tabular-nums sf-text-muted">{{ (int) $row->items_total }}</td>
                                        <td class="px-6 py-3 text-right text-sm tabular-nums text-[var(--text-main)]">R$ {{ number_format((float) $row->gross_total, 2, ',', '.') }}</td>
                                        <td class="px-6 py-3 text-right text-sm font-semibold text-emerald-200">R$ {{ number_format((float) $row->commission_total, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)]">
            <section class="sf-card overflow-hidden">
                <div class="flex flex-col gap-2 border-b border-white/10 px-6 py-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-[var(--text-main)]">Últimos agendamentos do dia</h3>
                        <p class="mt-1 text-sm sf-text-muted">Acompanhe a movimentação mais recente da agenda de hoje.</p>
                    </div>
                    <p class="text-sm sf-text-muted">{{ now()->format('d/m/Y') }}</p>
                </div>

                @if ($todayAppointments->isEmpty())
                    <div class="px-6 py-10 text-sm sf-text-muted">
                        Nenhum agendamento registrado hoje.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[var(--input-bg)]">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Horário</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Cliente</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Serviço</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Profissional</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                                @foreach ($todayAppointments as $appointment)
                                    <tr class="transition hover:bg-white/[0.03]">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-[var(--text-main)]">
                                            {{ $appointment->start_time->format('H:i') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm sf-text-muted">{{ $appointment->client->name }}</td>
                                        <td class="px-6 py-4 text-sm sf-text-muted">{{ $appointment->service->name }}</td>
                                        <td class="px-6 py-4 text-sm sf-text-muted">{{ $appointment->user->name }}</td>
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
