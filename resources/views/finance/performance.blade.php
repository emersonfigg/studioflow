<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Relatórios</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                    Dashboard de desempenho profissional
                </h2>
                <p class="mt-2 text-sm sf-text-muted">{{ $periodLabel }} · {{ $from->format('d/m/Y') }} até {{ $to->format('d/m/Y') }}</p>
            </div>
        </div>
    </x-slot>

    @include('finance.partials.nav', ['page' => $page])

    <section class="sf-card mb-6 p-5">
        <form method="GET" action="{{ route('finance.performance') }}" class="flex flex-wrap items-center gap-3">
            <select name="period" class="sf-select min-w-[220px]">
                <option value="today" @selected($period === 'today')>Hoje</option>
                <option value="7d" @selected($period === '7d')>7 dias</option>
                <option value="30d" @selected($period === '30d')>30 dias</option>
                <option value="this_month" @selected($period === 'this_month')>Este mês</option>
                <option value="last_month" @selected($period === 'last_month')>Mês anterior</option>
                <option value="custom" @selected($period === 'custom')>Período personalizado</option>
            </select>
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
            <button class="sf-button-ghost">Aplicar</button>
        </form>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium sf-text-muted">Receita total</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($metrics['summary']['revenue_total'], 2, ',', '.') }}</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium sf-text-muted">Atendimentos concluídos</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $metrics['summary']['completed_appointments'] }}</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium sf-text-muted">Ticket médio</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($metrics['summary']['ticket_average'], 2, ',', '.') }}</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium sf-text-muted">Clientes atendidos</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $metrics['summary']['clients_attended'] }}</p>
        </article>
        <article class="sf-card-soft p-5">
            <p class="text-sm font-medium sf-text-muted">Taxa de retorno</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ number_format($metrics['summary']['return_rate'], 2, ',', '.') }}%</p>
        </article>
    </section>

    @if (! $metrics['has_data'])
        <section class="sf-card mt-6 px-6 py-10 text-center">
            <h3 class="text-lg font-semibold text-[var(--text-main)]">Sem dados no período selecionado</h3>
            <p class="mt-2 text-sm sf-text-muted">Ajuste os filtros para visualizar o desempenho financeiro e operacional.</p>
        </section>
    @else
        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">1. Receita</h3>
                    <p class="mt-1 text-sm sf-text-muted">Serviços, produtos e evolução diária.</p>
                </div>
                <div class="space-y-4 p-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                            <p class="text-xs uppercase tracking-[0.18em] sf-text-muted">Serviços</p>
                            <p class="mt-2 text-xl font-semibold text-[var(--text-main)]">R$ {{ number_format($metrics['summary']['service_revenue'], 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                            <p class="text-xs uppercase tracking-[0.18em] sf-text-muted">Produtos</p>
                            <p class="mt-2 text-xl font-semibold text-[var(--text-main)]">R$ {{ number_format($metrics['summary']['product_revenue'], 2, ',', '.') }}</p>
                        </div>
                    </div>
                    <p class="text-sm sf-text-muted">
                        Crescimento vs período anterior:
                        @if ($metrics['summary']['growth_rate'] !== null)
                            <span class="font-semibold brand-text">{{ number_format($metrics['summary']['growth_rate'], 2, ',', '.') }}%</span>
                        @else
                            <span class="sf-text-muted">não disponível</span>
                        @endif
                    </p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[var(--input-bg)]">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Dia</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Receita</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                                @foreach ($metrics['revenue']['by_day'] as $day)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-[var(--text-main)]">{{ $day['date']->format('d/m/Y') }}</td>
                                        <td class="px-4 py-3 text-sm sf-text-muted">R$ {{ number_format($day['amount'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">2. Clientes</h3>
                    <p class="mt-1 text-sm sf-text-muted">Retenção e faturamento por cliente.</p>
                </div>
                <div class="space-y-4 p-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                            <p class="text-xs sf-text-muted uppercase tracking-[0.18em]">Novos</p>
                            <p class="mt-2 text-xl font-semibold text-[var(--text-main)]">{{ $metrics['clients']['new'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                            <p class="text-xs sf-text-muted uppercase tracking-[0.18em]">Recorrentes</p>
                            <p class="mt-2 text-xl font-semibold text-[var(--text-main)]">{{ $metrics['clients']['recurring'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                            <p class="text-xs sf-text-muted uppercase tracking-[0.18em]">Inativos</p>
                            <p class="mt-2 text-xl font-semibold text-[var(--text-main)]">{{ $metrics['clients']['inactive'] }}</p>
                        </div>
                    </div>
                    <div class="text-sm sf-text-muted">
                        Clientes em risco (30+ dias): {{ $metrics['clients']['at_risk']->count() }}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[var(--input-bg)]">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Top clientes</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Faturamento</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Comandas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                                @forelse ($metrics['clients']['top'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-[var(--text-main)]">{{ $row['client']?->name ?? 'Cliente removido' }}</td>
                                        <td class="px-4 py-3 text-sm sf-text-muted">R$ {{ number_format($row['revenue'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm sf-text-muted">{{ $row['orders'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-4 py-3 text-sm sf-text-muted">Sem clientes para o período.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">3. Profissionais</h3>
                    <p class="mt-1 text-sm sf-text-muted">Ranking por receita, comissão e retorno.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Profissional</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Receita</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Atend.</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Ticket</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Comissão</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Retorno</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                            @forelse ($metrics['professionals']['ranking'] as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-[var(--text-main)]">{{ $row['professional']?->name ?? 'Profissional removido' }}</td>
                                    <td class="px-4 py-3 text-sm sf-text-muted">R$ {{ number_format($row['revenue'], 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm sf-text-muted">{{ $row['appointments'] }}</td>
                                    <td class="px-4 py-3 text-sm sf-text-muted">R$ {{ number_format($row['ticket_average'], 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm sf-text-muted">R$ {{ number_format($row['commission'], 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm sf-text-muted">{{ number_format($row['return_rate'], 2, ',', '.') }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-3 text-sm sf-text-muted">Sem dados de profissionais.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">4. Serviços</h3>
                    <p class="mt-1 text-sm sf-text-muted">Mais vendidos, menos vendidos e upsell.</p>
                </div>
                <div class="space-y-4 p-6">
                    <p class="text-sm sf-text-muted">Taxa de upsell: <span class="font-semibold brand-text">{{ number_format($metrics['summary']['upsell_rate'], 2, ',', '.') }}%</span></p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                            <h4 class="text-sm font-semibold text-[var(--text-main)]">Mais vendidos</h4>
                            <ul class="mt-3 space-y-2 text-sm sf-text-muted">
                                @forelse ($metrics['services']['top'] as $row)
                                    <li>{{ $row['service']?->name ?? 'Serviço removido' }} · {{ $row['quantity'] }} · R$ {{ number_format($row['revenue'], 2, ',', '.') }}</li>
                                @empty
                                    <li>Sem dados de serviços.</li>
                                @endforelse
                            </ul>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                            <h4 class="text-sm font-semibold text-[var(--text-main)]">Menos vendidos</h4>
                            <ul class="mt-3 space-y-2 text-sm sf-text-muted">
                                @forelse ($metrics['services']['low'] as $row)
                                    <li>{{ $row['service']?->name ?? 'Serviço removido' }} · {{ $row['quantity'] }}</li>
                                @empty
                                    <li>Sem dados de serviços.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">5. Produtos</h3>
                    <p class="mt-1 text-sm sf-text-muted">Desempenho de itens e alerta de estoque.</p>
                </div>
                <div class="space-y-4 p-6">
                    <p class="text-sm sf-text-muted">Produtos vendidos no período: <span class="font-semibold brand-text">{{ $metrics['summary']['products_sold'] }}</span></p>
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                        <h4 class="text-sm font-semibold text-[var(--text-main)]">Produtos mais vendidos</h4>
                        <ul class="mt-3 space-y-2 text-sm sf-text-muted">
                            @forelse ($metrics['products']['top'] as $row)
                                <li>{{ $row['product']?->name ?? 'Produto removido' }} · {{ $row['quantity'] }} un · R$ {{ number_format($row['revenue'], 2, ',', '.') }}</li>
                            @empty
                                <li>Sem vendas de produtos no período.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                        <h4 class="text-sm font-semibold text-[var(--text-main)]">Estoque baixo</h4>
                        <ul class="mt-3 space-y-2 text-sm sf-text-muted">
                            @forelse ($metrics['products']['low_stock'] as $product)
                                <li>{{ $product->name }} · {{ $product->stock_quantity }} un</li>
                            @empty
                                <li>Nenhum produto com estoque baixo.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </section>

            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">6. Insights do negócio</h3>
                    <p class="mt-1 text-sm sf-text-muted">Frases geradas exclusivamente com os dados do período.</p>
                </div>
                <ul class="space-y-3 p-6 text-sm sf-text-muted">
                    @forelse ($metrics['insights'] as $insight)
                        <li class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-3">{{ $insight }}</li>
                    @empty
                        <li class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-3">Sem insights disponíveis para o período selecionado.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endif
</x-app-layout>
