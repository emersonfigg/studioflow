<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Auditoria Geral #{{ $stockCount->id }}</h1>
                <p class="mt-1 text-sm sf-text-muted">{{ $stockCount->status }} · {{ $stockCount->count_date?->format('d/m/Y') }}</p>
            </div>
            @php
                $hasDivergence = $stockCount->items->contains(fn ($item) => abs((float) $item->difference_quantity) > 0.000001);
                $hasPendingAdjustment = $stockCount->isCompleted() && $stockCount->items->contains(fn ($item) => abs((float) $item->difference_quantity) > 0.000001 && ! $item->adjustment_movement_id);
                $hasAppliedAdjustment = $stockCount->items->contains(fn ($item) => (bool) $item->adjustment_movement_id);
            @endphp
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stock.counts.index') }}" class="sf-button-secondary">Voltar</a>
                @if (! $stockCount->isCompleted())
                    <form method="POST" action="{{ route('stock.counts.complete', $stockCount) }}">
                        @csrf
                        <button class="sf-button-primary" type="submit">Finalizar auditoria</button>
                    </form>
                @elseif ($hasPendingAdjustment)
                    <a href="{{ route('stock.adjustments.index', ['stock_count_id' => $stockCount->id]) }}" class="sf-button-primary">Ir para Ajustes</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($stockCount->isCompleted())
            <div class="grid gap-4 md:grid-cols-3">
                <div class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Status</p>
                    <p class="mt-2 text-lg font-semibold sf-text">
                        @if (! $hasDivergence)
                            Sem divergencia
                        @elseif ($hasPendingAdjustment)
                            Divergencia registrada
                        @else
                            Ajuste aplicado
                        @endif
                    </p>
                </div>
                <div class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Itens divergentes</p>
                    <p class="mt-2 text-lg font-semibold sf-text">{{ $stockCount->items->filter(fn ($item) => abs((float) $item->difference_quantity) > 0.000001)->count() }}</p>
                </div>
                <div class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Ajustes aplicados</p>
                    <p class="mt-2 text-lg font-semibold sf-text">{{ $hasAppliedAdjustment ? 'Sim' : 'Nao' }}</p>
                </div>
            </div>
        @endif

        <div class="sf-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-sm">
                <thead class="text-left text-xs uppercase tracking-[0.16em] sf-text-muted">
                    <tr>
                        <th class="px-5 py-3">Produto</th>
                        @if ($stockCount->isCompleted())
                            <th class="px-5 py-3">Esperado</th>
                        @endif
                        <th class="px-5 py-3">Contado</th>
                        @if ($stockCount->isCompleted())
                            <th class="px-5 py-3">Diferenca</th>
                            <th class="px-5 py-3">Valor dif.</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Movimento</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-[color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                    @foreach ($stockCount->items as $item)
                        <tr>
                            <td class="px-5 py-4 font-semibold sf-text">{{ $item->product?->name }}</td>
                            @if ($stockCount->isCompleted())
                                <td class="px-5 py-4 sf-text-muted">{{ $item->expected_quantity }}</td>
                            @endif
                            <td class="px-5 py-4 sf-text">{{ $item->counted_quantity }}</td>
                            @if ($stockCount->isCompleted())
                                <td class="px-5 py-4 {{ (float) $item->difference_quantity === 0.0 ? 'sf-text-muted' : 'text-[var(--brand-primary)]' }}">{{ $item->difference_quantity }}</td>
                                <td class="px-5 py-4 sf-text-muted">
                                    R$ {{ number_format((float) $item->difference_value, 2, ',', '.') }}
                                    @if ($item->product && $item->product->cost_price === null)
                                        <span class="mt-1 block text-xs text-amber-200">Produto sem custo cadastrado</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    @if (abs((float) $item->difference_quantity) <= 0.000001)
                                        <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">Sem divergencia</span>
                                    @elseif ($item->adjustment_movement_id)
                                        <span class="rounded-full bg-[color-mix(in_srgb,var(--brand-primary)_14%,transparent)] px-3 py-1 text-xs font-semibold text-[var(--brand-primary)]">Ajuste aplicado</span>
                                        <span class="mt-1 block text-xs sf-text-muted">{{ $item->adjusted_at?->format('d/m/Y H:i') }} · {{ $item->adjustedBy?->name }}</span>
                                    @else
                                        <span class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-200">Divergencia registrada</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 sf-text-muted">{{ $item->adjustment_movement_id ? '#'.$item->adjustment_movement_id : '-' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        </div>
    </div>
</x-app-layout>
