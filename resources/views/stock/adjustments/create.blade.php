<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Ajustes</h1>
                <p class="mt-1 text-sm sf-text-muted">Correcoes autorizadas, compras, perdas e uso interno.</p>
            </div>
            <a href="{{ $dailyStockCheck ? route('stock.daily-checks.show', $dailyStockCheck) : ($stockCount ? route('stock.counts.show', $stockCount) : route('stock.index')) }}" class="sf-button-secondary">Voltar</a>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('stock.adjustments.store') }}" class="sf-card max-w-3xl space-y-5 p-6">
        @csrf
        @if ($dailyStockCheck)
            <input type="hidden" name="daily_stock_check_id" value="{{ $dailyStockCheck->id }}">
            <div class="rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_28%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,var(--card-bg))] p-4">
                <p class="text-sm font-semibold sf-text">Ajuste vinculado a Conferencia Diaria #{{ $dailyStockCheck->id }}</p>
                <p class="mt-1 text-sm sf-text-muted">Use os dados da divergencia registrada para lancar a correcao autorizada. O ajuste sera registrado como movimento real no estoque.</p>
                <div class="mt-3 space-y-2 text-sm">
                    @foreach ($dailyStockCheck->items->filter(fn ($item) => abs((float) $item->difference_quantity) > 0.000001 && ! $item->adjustment_movement_id) as $item)
                        <div class="flex flex-col gap-1 rounded-xl border border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[color-mix(in_srgb,var(--text-main)_4%,transparent)] px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="font-semibold sf-text">{{ $item->product?->name }}</span>
                            <span class="sf-text-muted">Dif.: {{ $item->difference_quantity }} · {{ (float) $item->difference_quantity > 0 ? 'entrada sugerida' : 'saida sugerida' }} {{ abs((float) $item->difference_quantity) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        @if ($stockCount)
            <input type="hidden" name="stock_count_id" value="{{ $stockCount->id }}">
            <div class="rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_28%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,var(--card-bg))] p-4">
                <p class="text-sm font-semibold sf-text">Ajuste vinculado a Auditoria Geral #{{ $stockCount->id }}</p>
                <p class="mt-1 text-sm sf-text-muted">Use os dados da divergencia registrada para lancar a correcao autorizada. O ajuste sera registrado como movimento real no estoque.</p>
                <div class="mt-3 space-y-2 text-sm">
                    @foreach ($stockCount->items->filter(fn ($item) => abs((float) $item->difference_quantity) > 0.000001 && ! $item->adjustment_movement_id) as $item)
                        <div class="flex flex-col gap-1 rounded-xl border border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[color-mix(in_srgb,var(--text-main)_4%,transparent)] px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="font-semibold sf-text">{{ $item->product?->name }}</span>
                            <span class="sf-text-muted">Dif.: {{ $item->difference_quantity }} · {{ (float) $item->difference_quantity > 0 ? 'entrada sugerida' : 'saida sugerida' }} {{ abs((float) $item->difference_quantity) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div>
            <x-input-label for="product_id" value="Produto" />
            <select id="product_id" name="product_id" class="mt-2 block w-full rounded-xl border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--input-bg)] sf-text" required>
                <option value="">Selecione</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((int) old('product_id') === $product->id)>{{ $product->name }} · atual {{ $product->stock_quantity }} {{ $product->unit }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <x-input-label for="direction" value="Tipo" />
                <select id="direction" name="direction" class="mt-2 block w-full rounded-xl border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--input-bg)] sf-text" required>
                    <option value="in" @selected(old('direction') === 'in')>Entrada</option>
                    <option value="out" @selected(old('direction') === 'out')>Saida</option>
                </select>
                <x-input-error :messages="$errors->get('direction')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="quantity" value="Quantidade" />
                <x-text-input id="quantity" name="quantity" type="number" min="0.01" step="0.01" class="mt-2 block w-full" :value="old('quantity')" required />
                <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <x-input-label for="reason" value="Motivo" />
                <select id="reason" name="reason" class="mt-2 block w-full rounded-xl border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--input-bg)] sf-text" required>
                    <option value="purchase" @selected(old('reason') === 'purchase')>Compra</option>
                    <option value="loss" @selected(old('reason') === 'loss')>Perda</option>
                    <option value="internal_use" @selected(old('reason') === 'internal_use')>Uso interno</option>
                    <option value="correction" @selected(old('reason') === 'correction')>Correcao</option>
                    <option value="other" @selected(old('reason') === 'other')>Outro</option>
                </select>
                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="unit_cost" value="Custo unitario" />
                <x-text-input id="unit_cost" name="unit_cost" type="number" min="0" step="0.01" class="mt-2 block w-full" :value="old('unit_cost')" />
                <x-input-error :messages="$errors->get('unit_cost')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="notes" value="Observacao" />
            <textarea id="notes" name="notes" rows="4" class="mt-2 block w-full rounded-xl border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--input-bg)] sf-text">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex justify-end">
            <button class="sf-button-primary" type="submit">Registrar ajuste</button>
        </div>
    </form>
</x-app-layout>
