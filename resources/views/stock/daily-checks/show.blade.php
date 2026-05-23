<x-app-layout>
    <x-slot name="header">
        @php
            $hasDivergence = $dailyStockCheck->items->contains(fn ($item) => abs((float) $item->difference_quantity) > 0.000001);
            $hasPendingAdjustment = $dailyStockCheck->isCompleted() && $dailyStockCheck->items->contains(fn ($item) => abs((float) $item->difference_quantity) > 0.000001 && ! $item->adjustment_movement_id);
        @endphp
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Conferencia Diaria #{{ $dailyStockCheck->id }}</h1>
                <p class="mt-1 text-sm sf-text-muted">{{ $dailyStockCheck->status }} · referencia {{ $dailyStockCheck->reference_date?->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($dailyStockCheck->status === \App\Models\DailyStockCheck::STATUS_DRAFT)
                    <form method="POST" action="{{ route('stock.daily-checks.cancel', $dailyStockCheck) }}">
                        @csrf
                        <button class="sf-button-secondary" type="submit">Cancelar</button>
                    </form>
                @elseif ($hasPendingAdjustment)
                    <a href="{{ route('stock.adjustments.index', ['daily_stock_check_id' => $dailyStockCheck->id]) }}" class="sf-button-primary">Ir para Ajustes</a>
                @endif
                <a href="{{ route('stock.daily-checks.index') }}" class="sf-button-secondary">Voltar</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($dailyStockCheck->isCompleted())
            <div class="grid gap-4 md:grid-cols-3">
                <div class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Status</p>
                    <p class="mt-2 text-lg font-semibold sf-text">{{ ! $hasDivergence ? 'Sem divergencia' : ($hasPendingAdjustment ? 'Divergencia registrada' : 'Ajuste aplicado') }}</p>
                </div>
                <div class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Itens divergentes</p>
                    <p class="mt-2 text-lg font-semibold sf-text">{{ $dailyStockCheck->items->filter(fn ($item) => abs((float) $item->difference_quantity) > 0.000001)->count() }}</p>
                </div>
                <div class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Finalizada em</p>
                    <p class="mt-2 text-lg font-semibold sf-text">{{ $dailyStockCheck->completed_at?->format('d/m/Y H:i') }}</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('stock.daily-checks.complete', $dailyStockCheck) }}" class="sf-card overflow-hidden">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-sm">
                    <thead class="text-left text-xs uppercase tracking-[0.16em] sf-text-muted">
                        <tr>
                            <th class="px-5 py-3">Produto</th>
                            <th class="px-5 py-3">Vendido ontem</th>
                            <th class="px-5 py-3">Baixado ontem</th>
                            <th class="px-5 py-3">Outras saidas</th>
                            <th class="px-5 py-3">Entradas</th>
                            @if ($dailyStockCheck->isCompleted())
                                <th class="px-5 py-3">Esperado</th>
                            @endif
                            <th class="px-5 py-3">Contado</th>
                            @if ($dailyStockCheck->isCompleted())
                                <th class="px-5 py-3">Diferenca</th>
                                <th class="px-5 py-3">Valor</th>
                                <th class="px-5 py-3">Status</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                        @foreach ($dailyStockCheck->items as $index => $item)
                            <tr>
                                <td class="px-5 py-4 font-semibold sf-text">{{ $item->product?->name }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ $item->sold_quantity }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ $item->sale_stock_quantity }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ $item->other_output_quantity }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ $item->input_quantity }}</td>
                                @if ($dailyStockCheck->isCompleted())
                                    <td class="px-5 py-4 sf-text">{{ $item->expected_quantity }}</td>
                                @endif
                                <td class="px-5 py-4">
                                    @if ($dailyStockCheck->isCompleted())
                                        <span class="sf-text">{{ $item->counted_quantity }}</span>
                                    @else
                                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                        <x-text-input name="items[{{ $index }}][counted_quantity]" type="number" min="0" step="0.01" class="block w-32" :value="old('items.'.$index.'.counted_quantity')" required />
                                    @endif
                                </td>
                                @if ($dailyStockCheck->isCompleted())
                                    <td class="px-5 py-4 {{ abs((float) $item->difference_quantity) > 0.000001 ? 'text-[var(--brand-primary)]' : 'sf-text-muted' }}">{{ $item->difference_quantity }}</td>
                                    <td class="px-5 py-4 sf-text-muted">
                                        R$ {{ number_format((float) $item->difference_value, 2, ',', '.') }}
                                        @if ($item->product && $item->product->cost_price === null)
                                            <span class="mt-1 block text-xs text-amber-200">Produto sem custo cadastrado</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($item->status === \App\Models\DailyStockCheckItem::STATUS_OK)
                                            <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">OK</span>
                                        @elseif ($item->adjustment_movement_id)
                                            <span class="rounded-full bg-[color-mix(in_srgb,var(--brand-primary)_14%,transparent)] px-3 py-1 text-xs font-semibold text-[var(--brand-primary)]">Ajuste aplicado</span>
                                            <span class="mt-1 block text-xs sf-text-muted">{{ $item->adjusted_at?->format('d/m/Y H:i') }} · {{ $item->adjustedBy?->name }}</span>
                                        @else
                                            <span class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-200">Divergente</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($dailyStockCheck->status === \App\Models\DailyStockCheck::STATUS_DRAFT)
                <div class="border-t border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] px-5 py-4">
                    <div class="flex justify-end"><button class="sf-button-primary" type="submit">Finalizar contagem</button></div>
                </div>
            @endif
        </form>
    </div>
</x-app-layout>
