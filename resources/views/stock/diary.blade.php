<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Movimentacoes</h1>
                <p class="mt-1 text-sm sf-text-muted">Historico de entradas, saidas e ajustes reais do estoque.</p>
            </div>
            <a href="{{ route('stock.index') }}" class="sf-button-secondary">Voltar</a>
        </div>
    </x-slot>

    @php
        $movementLabels = [
            'in' => 'Entrada',
            'out' => 'Saida',
            'adjustment' => 'Ajuste',
            'sale' => 'Venda',
            'service_consumption' => 'Consumo em atendimento',
            'initial_balance' => 'Saldo inicial',
            'purchase' => 'Compra',
            'sale_reversal' => 'Estorno de venda',
            'manual_adjustment' => 'Ajuste autorizado',
            'blind_count_adjustment' => 'Ajuste de auditoria',
            'audit_adjustment' => 'Ajuste de auditoria',
            'blind_count_adjustment_applied' => 'Ajuste da auditoria geral',
            'loss' => 'Perda',
            'internal_use' => 'Uso interno',
        ];
    @endphp

    <div class="space-y-6">
        <form method="GET" class="sf-card grid gap-4 p-5 md:grid-cols-5">
            <div>
                <x-input-label for="date_from" value="Data inicial" />
                <x-text-input id="date_from" name="date_from" type="date" class="mt-2 block w-full" :value="request('date_from')" />
            </div>
            <div>
                <x-input-label for="date_to" value="Data final" />
                <x-text-input id="date_to" name="date_to" type="date" class="mt-2 block w-full" :value="request('date_to')" />
            </div>
            <div>
                <x-input-label for="product_id" value="Produto" />
                <select id="product_id" name="product_id" class="mt-2 block w-full rounded-xl border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--input-bg)] sf-text">
                    <option value="">Todos</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((int) request('product_id') === $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="type" value="Tipo" />
                <select id="type" name="type" class="mt-2 block w-full rounded-xl border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--input-bg)] sf-text">
                    <option value="">Todos</option>
                    @foreach (\App\Models\StockMovement::TYPES as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ $movementLabels[$type] ?? str_replace('_', ' ', $type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button class="sf-button-primary w-full" type="submit">Filtrar</button>
            </div>
        </form>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="sf-card p-5"><p class="text-sm sf-text-muted">Entradas</p><p class="mt-2 text-2xl font-semibold sf-text">{{ number_format((float) $summary['in'], 2, ',', '.') }}</p></div>
            <div class="sf-card p-5"><p class="text-sm sf-text-muted">Saidas</p><p class="mt-2 text-2xl font-semibold sf-text">{{ number_format((float) $summary['out'], 2, ',', '.') }}</p></div>
            <div class="sf-card p-5"><p class="text-sm sf-text-muted">Vendido</p><p class="mt-2 text-2xl font-semibold sf-text">{{ number_format((float) $summary['sold'], 2, ',', '.') }}</p></div>
        </div>

        <div class="sf-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-sm">
                    <thead class="text-left text-xs uppercase tracking-[0.16em] sf-text-muted">
                        <tr>
                            <th class="px-5 py-3">Data</th>
                            <th class="px-5 py-3">Produto</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Entrada</th>
                            <th class="px-5 py-3">Saida</th>
                            <th class="px-5 py-3">Antes</th>
                            <th class="px-5 py-3">Depois</th>
                            <th class="px-5 py-3">Usuario</th>
                            <th class="px-5 py-3">Obs.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="px-5 py-4 sf-text">{{ $movement->movement_date?->format('d/m/Y H:i') ?? $movement->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-4 font-semibold sf-text">{{ $movement->product?->name }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ $movementLabels[$movement->type] ?? str_replace('_', ' ', $movement->type) }}</td>
                                <td class="px-5 py-4 text-emerald-200">{{ $movement->direction === 'in' ? $movement->quantity : '-' }}</td>
                                <td class="px-5 py-4 text-rose-200">{{ $movement->direction === 'out' ? $movement->quantity : '-' }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ $movement->balance_before ?? $movement->previous_quantity }}</td>
                                <td class="px-5 py-4 sf-text">{{ $movement->balance_after ?? $movement->new_quantity }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ $movement->user?->name ?? '-' }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ $movement->notes ?? $movement->reason }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-5 py-8 text-center sf-text-muted">Nenhuma movimentacao real encontrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] px-5 py-4">{{ $movements->links() }}</div>
        </div>
    </div>
</x-app-layout>
