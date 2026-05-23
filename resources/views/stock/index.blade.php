<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Controle de estoque</h1>
                <p class="mt-1 text-sm sf-text-muted">Movimentacoes reais, conferencias operacionais, auditoria e ajustes autorizados.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('dashboard') }}" class="sf-button-secondary">Voltar</a>
                <a href="{{ route('stock.adjustments.index') }}" class="sf-button-primary">Novo ajuste</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Entradas hoje</p>
                <p class="mt-3 text-3xl font-semibold sf-text">{{ number_format((float) $todayIn, 2, ',', '.') }}</p>
            </div>
            <div class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Saidas hoje</p>
                <p class="mt-3 text-3xl font-semibold sf-text">{{ number_format((float) $todayOut, 2, ',', '.') }}</p>
            </div>
            <div class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Estoque baixo</p>
                <p class="mt-3 text-3xl font-semibold sf-text">{{ $lowStock->count() }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['title' => 'Movimentacoes', 'desc' => 'Historico de entradas, saidas e ajustes reais do estoque.', 'route' => route('stock.movements')],
                ['title' => 'Conferencia Diaria', 'desc' => 'Rotina operacional diaria para conferir o fisico com base nas movimentacoes de ontem.', 'route' => route('stock.daily-checks.index')],
                ['title' => 'Auditoria Geral', 'desc' => 'Conferencia ampla e periodica do estoque completo.', 'route' => route('stock.audit-general.index')],
                ['title' => 'Relatorio de Auditoria', 'desc' => 'Compare saldo inicial, movimentos, divergencias e ajustes aplicados.', 'route' => route('stock.audit')],
                ['title' => 'Vendas x Estoque', 'desc' => 'Confira se o vendido ontem foi baixado.', 'route' => route('stock.sales-audit')],
                ['title' => 'Estoque Baixo', 'desc' => 'Produtos abaixo do minimo configurado.', 'route' => route('stock.low')],
                ['title' => 'Ajustes', 'desc' => 'Correcoes autorizadas, compras, perdas e uso interno.', 'route' => route('stock.adjustments.index')],
            ] as $card)
                <a href="{{ $card['route'] }}" class="sf-card block p-5 transition hover:border-[color-mix(in_srgb,var(--brand-primary)_32%,transparent)] hover:bg-[color-mix(in_srgb,var(--brand-primary)_8%,var(--card-bg))]">
                    <h2 class="text-lg font-semibold sf-text">{{ $card['title'] }}</h2>
                    <p class="mt-2 text-sm sf-text-muted">{{ $card['desc'] }}</p>
                </a>
            @endforeach
        </div>

        <div class="sf-card p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold sf-text">Produtos com estoque baixo</h2>
                    <p class="mt-1 text-sm sf-text-muted">Visao rapida dos alertas ativos.</p>
                </div>
                <a href="{{ route('stock.low') }}" class="sf-button-secondary">Ver todos</a>
            </div>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-sm">
                    <thead class="text-left text-xs uppercase tracking-[0.16em] sf-text-muted">
                        <tr>
                            <th class="py-3 pr-4">Produto</th>
                            <th class="py-3 pr-4">Atual</th>
                            <th class="py-3 pr-4">Minimo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                        @forelse ($lowStock as $product)
                            <tr>
                                <td class="py-3 pr-4 font-semibold sf-text">{{ $product->name }}</td>
                                <td class="py-3 pr-4 sf-text">{{ $product->stock_quantity }} {{ $product->unit }}</td>
                                <td class="py-3 pr-4 sf-text-muted">{{ $product->minimum_stock }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center sf-text-muted">Nenhum produto abaixo do minimo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
