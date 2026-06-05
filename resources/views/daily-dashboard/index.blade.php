@php
    $money = fn (float|int|null $value): string => 'R$ '.number_format((float) $value, 2, ',', '.');
    $number = fn (float|int|null $value): string => number_format((float) $value, 0, ',', '.');
    $kpis = $dashboard['kpis'];
    $maxHourRevenue = max(1, (float) collect($dashboard['charts']['revenue_by_hour'])->max('value'));
    $maxPaymentRevenue = max(1, (float) collect($dashboard['charts']['revenue_by_payment_method'])->max('value'));
    $maxProfessionalRevenue = max(1, (float) collect($dashboard['charts']['production_by_professional'])->max('value'));
    $mixTotal = max(1, (float) collect($dashboard['charts']['mix'])->sum('value'));
    $statusTotal = max(1, (float) collect($dashboard['charts']['appointment_status'])->sum('value'));
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-200">Central do Dia</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Resumo executivo da operacao</h2>
                <p class="mt-2 text-sm sf-text-muted">Visao consolidada de receita, agenda, producao e estoque da empresa logada.</p>
            </div>

            <div class="rounded-2xl border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">
                {{ $date->format('d/m/Y') }}
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-amber-300/20 bg-[linear-gradient(135deg,color-mix(in_srgb,var(--card-bg)_92%,black),color-mix(in_srgb,var(--brand-primary)_10%,var(--card-bg)))] p-5 shadow-[0_24px_60px_rgba(0,0,0,0.28)]">
            <form method="GET" action="{{ route('daily-dashboard.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[180px_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-end">
                <div>
                    <x-input-label for="date" value="Data" />
                    <x-text-input id="date" name="date" type="date" class="mt-2 block w-full" :value="$date->toDateString()" />
                </div>

                <div>
                    <x-input-label for="user_id" value="Profissional" />
                    <select id="user_id" name="user_id" class="sf-select mt-2 block w-full" @disabled(! $canFilterProfessionals)>
                        <option value="">Todos</option>
                        @foreach ($dashboard['users'] as $user)
                            <option value="{{ $user->id }}" @selected((int) $selectedUserId === (int) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="sf-select mt-2 block w-full">
                        <option value="">Todos</option>
                        @foreach ($dashboard['status_options'] as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="payment_method" value="Forma de pagamento" />
                    <select id="payment_method" name="payment_method" class="sf-select mt-2 block w-full">
                        <option value="">Todas</option>
                        @foreach ($dashboard['payment_methods'] as $value => $label)
                            <option value="{{ $value }}" @selected($selectedPaymentMethod === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button class="sf-button-primary h-11 whitespace-nowrap">Atualizar</button>
            </form>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-6">
            @foreach ([
                ['label' => 'Receita bruta', 'value' => $money($kpis['gross_revenue']), 'hint' => 'Servicos, comandas e vendas avulsas'],
                ['label' => 'Receita liquida', 'value' => $money($kpis['net_revenue']), 'hint' => 'Bruto menos comissoes'],
                ['label' => 'Comissoes', 'value' => $money($kpis['commissions']), 'hint' => 'Servicos e produtos'],
                ['label' => 'Realizados', 'value' => $number($kpis['completed_appointments']), 'hint' => 'Comandas pagas com servicos'],
                ['label' => 'Agendados', 'value' => $number($kpis['scheduled_appointments']), 'hint' => 'Agenda do dia'],
                ['label' => 'Cancelados', 'value' => $number($kpis['cancelled_appointments']), 'hint' => 'Agenda cancelada'],
                ['label' => 'Ticket medio', 'value' => $money($kpis['average_ticket']), 'hint' => 'Media por atendimento/venda'],
                ['label' => 'Produtos vendidos', 'value' => $number($kpis['products_sold']), 'hint' => 'Quantidade de itens'],
                ['label' => 'Servicos realizados', 'value' => $number($kpis['services_done']), 'hint' => 'Quantidade de servicos'],
                ['label' => 'Clientes atendidos', 'value' => $number($kpis['clients_attended']), 'hint' => 'Clientes unicos'],
                ['label' => 'Novos clientes', 'value' => $number($kpis['new_clients']), 'hint' => 'Cadastros no dia'],
            ] as $metric)
                <article class="relative overflow-hidden rounded-2xl border border-white/10 bg-[color-mix(in_srgb,var(--card-bg)_88%,black)] p-5 shadow-[0_18px_40px_rgba(0,0,0,0.18)]">
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-amber-300/80 to-transparent"></div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-200">{{ $metric['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold tracking-tight text-[var(--text-main)]">{{ $metric['value'] }}</p>
                    <p class="mt-2 text-xs sf-text-muted">{{ $metric['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 2xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.65fr)]">
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Atendimentos realizados no dia</h3>
                    <p class="mt-1 text-sm sf-text-muted">Comandas fechadas, produtos vinculados, descontos e resultado liquido.</p>
                </div>

                @if ($dashboard['appointments']->isEmpty())
                    <div class="px-6 py-10 text-sm sf-text-muted">Nenhum atendimento realizado para os filtros selecionados.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[var(--input-bg)]">
                                <tr>
                                    @foreach (['Hora', 'Cliente', 'Profissional', 'Servicos', 'Produtos', 'Bruto', 'Desconto', 'Comissao', 'Liquido', 'Pagamento', 'Status'] as $heading)
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] sf-text-muted">{{ $heading }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/8">
                                @foreach ($dashboard['appointments'] as $row)
                                    <tr class="transition hover:bg-white/[0.03]">
                                        <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-amber-100">{{ $row['time']?->format('H:i') ?? '--:--' }}</td>
                                        <td class="px-5 py-4 text-sm font-semibold text-[var(--text-main)]">{{ $row['client'] }}</td>
                                        <td class="px-5 py-4 text-sm sf-text-muted">{{ $row['professional'] }}</td>
                                        <td class="min-w-48 px-5 py-4 text-sm sf-text-muted">{{ $row['services'] ?: '-' }}</td>
                                        <td class="min-w-48 px-5 py-4 text-sm sf-text-muted">{{ $row['products'] ?: '-' }}</td>
                                        <td class="px-5 py-4 text-sm tabular-nums text-[var(--text-main)]">{{ $money($row['gross']) }}</td>
                                        <td class="px-5 py-4 text-sm tabular-nums sf-text-muted">{{ $money($row['discount']) }}</td>
                                        <td class="px-5 py-4 text-sm tabular-nums text-amber-100">{{ $money($row['commission']) }}</td>
                                        <td class="px-5 py-4 text-sm font-semibold tabular-nums text-emerald-200">{{ $money($row['net']) }}</td>
                                        <td class="px-5 py-4 text-sm sf-text-muted">{{ $row['payment_method'] }}</td>
                                        <td class="px-5 py-4 text-sm">
                                            <span class="rounded-full border border-amber-300/20 bg-amber-300/10 px-2.5 py-1 text-xs font-semibold text-amber-100">{{ $row['status'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </article>

            <aside class="space-y-6">
                <article class="sf-card p-6">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">Receita por hora</h3>
                    <div class="mt-5 flex h-48 items-end gap-2">
                        @foreach ($dashboard['charts']['revenue_by_hour'] as $bar)
                            @php($height = max(4, round(((float) $bar['value'] / $maxHourRevenue) * 100)))
                            <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-2">
                                <div class="w-full rounded-t-lg bg-amber-300/80" style="height: {{ $height }}%"></div>
                                <span class="text-[10px] sf-text-muted">{{ $bar['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="sf-card p-6">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">Mix de receita</h3>
                    <div class="mt-5 space-y-3">
                        @foreach ($dashboard['charts']['mix'] as $slice)
                            @php($pct = round(((float) $slice['value'] / $mixTotal) * 100, 1))
                            <div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-[var(--text-main)]">{{ $slice['label'] }}</span>
                                    <span class="sf-text-muted">{{ $pct }}%</span>
                                </div>
                                <div class="mt-2 h-3 overflow-hidden rounded-full bg-white/10">
                                    <div class="h-full rounded-full bg-amber-300" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            </aside>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Produtos vendidos no dia</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                @foreach (['Produto', 'Qtd.', 'Receita', 'Custo est.', 'Margem est.', 'Estoque', 'Alerta'] as $heading)
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] sf-text-muted">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8">
                            @forelse ($dashboard['products'] as $row)
                                <tr>
                                    <td class="px-5 py-4 text-sm font-semibold text-[var(--text-main)]">{{ $row['name'] }}</td>
                                    <td class="px-5 py-4 text-sm tabular-nums sf-text-muted">{{ $row['quantity'] }}</td>
                                    <td class="px-5 py-4 text-sm tabular-nums text-[var(--text-main)]">{{ $money($row['revenue']) }}</td>
                                    <td class="px-5 py-4 text-sm tabular-nums sf-text-muted">{{ $row['cost'] !== null ? $money($row['cost']) : '-' }}</td>
                                    <td class="px-5 py-4 text-sm tabular-nums {{ ($row['margin'] ?? 0) >= 0 ? 'text-emerald-200' : 'text-rose-200' }}">{{ $row['margin'] !== null ? $money($row['margin']) : '-' }}</td>
                                    <td class="px-5 py-4 text-sm sf-text-muted">{{ $row['stock'] ?? '-' }} {{ $row['unit'] }}</td>
                                    <td class="px-5 py-4 text-sm">
                                        @if ($row['low_stock'])
                                            <span class="rounded-full border border-amber-300/30 bg-amber-300/10 px-2.5 py-1 text-xs font-semibold text-amber-100">Baixo</span>
                                        @else
                                            <span class="sf-text-muted">OK</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-8 text-sm sf-text-muted">Nenhum produto vendido.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Servicos realizados no dia</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                @foreach (['Servico', 'Qtd.', 'Receita', 'Profissional', 'Ticket medio'] as $heading)
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] sf-text-muted">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8">
                            @forelse ($dashboard['services'] as $row)
                                <tr>
                                    <td class="px-5 py-4 text-sm font-semibold text-[var(--text-main)]">{{ $row['name'] }}</td>
                                    <td class="px-5 py-4 text-sm tabular-nums sf-text-muted">{{ $row['quantity'] }}</td>
                                    <td class="px-5 py-4 text-sm tabular-nums text-[var(--text-main)]">{{ $money($row['revenue']) }}</td>
                                    <td class="px-5 py-4 text-sm sf-text-muted">{{ $row['professional'] }}</td>
                                    <td class="px-5 py-4 text-sm tabular-nums text-emerald-200">{{ $money($row['ticket_average']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-sm sf-text-muted">Nenhum servico realizado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-4">
            @foreach ([
                'Profissionais com maior producao' => $dashboard['rankings']['professionals']->map(fn ($row) => ['name' => $row['name'], 'meta' => $money($row['gross'])]),
                'Servicos mais vendidos' => $dashboard['rankings']['services']->map(fn ($row) => ['name' => $row['name'], 'meta' => $row['quantity'].' venda(s)']),
                'Produtos mais vendidos' => $dashboard['rankings']['products']->map(fn ($row) => ['name' => $row['name'], 'meta' => $row['quantity'].' item(ns)']),
                'Clientes com maior consumo' => $dashboard['rankings']['clients']->map(fn ($row) => ['name' => $row['name'], 'meta' => $money($row['gross'])]),
            ] as $title => $rows)
                <article class="sf-card p-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">{{ $title }}</h3>
                    <div class="mt-4 space-y-3">
                        @forelse ($rows as $index => $row)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-[var(--input-bg)] px-3 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-[var(--text-main)]">{{ $index + 1 }}. {{ $row['name'] }}</p>
                                    <p class="text-xs sf-text-muted">{{ $row['meta'] }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm sf-text-muted">Sem dados para o periodo.</p>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <article class="sf-card p-6">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Receita por forma de pagamento</h3>
                <div class="mt-5 space-y-4">
                    @forelse ($dashboard['charts']['revenue_by_payment_method'] as $row)
                        @php($pct = round(((float) $row['value'] / $maxPaymentRevenue) * 100, 1))
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-[var(--text-main)]">{{ $row['label'] }}</span>
                                <span class="sf-text-muted">{{ $money($row['value']) }}</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-emerald-300" style="width: {{ $pct }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm sf-text-muted">Sem pagamentos no dia.</p>
                    @endforelse
                </div>
            </article>

            <article class="sf-card p-6">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Producao por profissional</h3>
                <div class="mt-5 space-y-4">
                    @forelse ($dashboard['charts']['production_by_professional'] as $row)
                        @php($pct = round(((float) $row['value'] / $maxProfessionalRevenue) * 100, 1))
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-[var(--text-main)]">{{ $row['label'] }}</span>
                                <span class="sf-text-muted">{{ $money($row['value']) }}</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-amber-300" style="width: {{ $pct }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm sf-text-muted">Sem producao no dia.</p>
                    @endforelse
                </div>
            </article>

            <article class="sf-card p-6">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Status dos atendimentos</h3>
                <div class="mt-5 space-y-4">
                    @forelse ($dashboard['charts']['appointment_status'] as $row)
                        @php($pct = round(((float) $row['value'] / $statusTotal) * 100, 1))
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-[var(--text-main)]">{{ $row['label'] }}</span>
                                <span class="sf-text-muted">{{ $row['value'] }}</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-sky-300" style="width: {{ $pct }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm sf-text-muted">Sem agendamentos no dia.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</x-app-layout>
