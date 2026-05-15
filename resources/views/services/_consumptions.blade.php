@php
    $consumptionRows = $consumptionRows ?? [['product_id' => '', 'quantity' => '1', 'unit' => '', 'active' => true]];
@endphp

<div class="lg:col-span-2 space-y-4 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Produtos consumidos neste serviço</p>
        <p class="mt-1 text-sm sf-text-muted">
            Ao concluir um atendimento com este serviço, o estoque dos produtos abaixo será baixado automaticamente (respeitando controle de estoque e disponibilidade).
        </p>
    </div>

    <x-input-error class="mt-2" :messages="$errors->get('consumptions')" />

    <div class="space-y-3">
        @foreach ($consumptionRows as $index => $row)
            @php($row = is_array($row) ? $row : [])
            <div class="grid gap-3 rounded-xl border border-white/10 bg-[var(--app-shell-bg)]/40 p-3 sm:grid-cols-12 sm:items-end">
                <div class="sm:col-span-5">
                    <label class="text-xs font-medium text-[var(--text-main)]">Produto</label>
                    <select name="consumptions[{{ $index }}][product_id]" class="sf-select mt-1 block w-full">
                        <option value="">— Não vincular —</option>
                        @foreach ($products as $productOption)
                            <option value="{{ $productOption->id }}" @selected((string) old("consumptions.$index.product_id", $row['product_id'] ?? '') === (string) $productOption->id)>
                                {{ $productOption->name }} @if ($productOption->tracksStock()) · Estoque {{ $productOption->stock_quantity }} @if ($productOption->unit) {{ $productOption->unit }} @endif @else · sem controle @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-[var(--text-main)]">Quantidade</label>
                    <input
                        type="number"
                        name="consumptions[{{ $index }}][quantity]"
                        min="0.01"
                        step="0.01"
                        value="{{ old("consumptions.$index.quantity", $row['quantity'] ?? '1') }}"
                        class="sf-input mt-1 block w-full"
                    >
                </div>
                <div class="sm:col-span-3">
                    <label class="text-xs font-medium text-[var(--text-main)]">Unidade (opcional)</label>
                    <input
                        type="text"
                        name="consumptions[{{ $index }}][unit]"
                        maxlength="32"
                        value="{{ old("consumptions.$index.unit", $row['unit'] ?? '') }}"
                        class="sf-input mt-1 block w-full"
                        placeholder="Ex: ml, g, un"
                    >
                </div>
                <div class="sm:col-span-2 flex items-center gap-2 pb-1">
                    <input type="hidden" name="consumptions[{{ $index }}][active]" value="0">
                    <label class="inline-flex items-center gap-2 text-xs text-[var(--text-main)]">
                        <input
                            type="checkbox"
                            name="consumptions[{{ $index }}][active]"
                            value="1"
                            class="rounded border-white/20 bg-[var(--app-shell-bg)] brand-text focus:ring-[var(--brand-primary)]"
                            @checked(old("consumptions.$index.active", $row['active'] ?? true))
                        >
                        Ativo
                    </label>
                </div>
            </div>
        @endforeach
    </div>
</div>
