<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Nova auditoria geral</h1>
                <p class="mt-1 text-sm sf-text-muted">Conferencia ampla do estoque completo. O saldo esperado nao aparece durante a contagem.</p>
            </div>
            <a href="{{ route('stock.counts.index') }}" class="sf-button-secondary">Voltar</a>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('stock.counts.store') }}" class="space-y-6">
        @csrf
        <div class="sf-card grid gap-4 p-5 md:grid-cols-2">
            <div>
                <x-input-label for="count_date" value="Data da auditoria" />
                <x-text-input id="count_date" name="count_date" type="date" class="mt-2 block w-full" :value="old('count_date', now()->toDateString())" required />
                <x-input-error :messages="$errors->get('count_date')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="notes" value="Observacao" />
                <x-text-input id="notes" name="notes" class="mt-2 block w-full" :value="old('notes')" />
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>
        </div>

        <div class="sf-card overflow-hidden">
            <div class="border-b border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] px-5 py-4">
                <h2 class="text-lg font-semibold sf-text">Produtos controlados</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-sm">
                    <thead class="text-left text-xs uppercase tracking-[0.16em] sf-text-muted">
                        <tr>
                            <th class="px-5 py-3">Produto</th>
                            <th class="px-5 py-3">Unidade</th>
                            <th class="px-5 py-3">Quantidade contada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                        @foreach ($products as $index => $product)
                            <tr>
                                <td class="px-5 py-4 font-semibold sf-text">
                                    {{ $product->name }}
                                    <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $product->id }}">
                                </td>
                                <td class="px-5 py-4 sf-text-muted">{{ $product->unit ?: 'un' }}</td>
                                <td class="px-5 py-4">
                                    <x-text-input name="items[{{ $index }}][counted_quantity]" type="number" min="0" step="0.01" class="block w-40" value="0" required />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] px-5 py-4">
                <x-input-error :messages="$errors->get('items')" class="mb-3" />
                <div class="flex justify-end"><button class="sf-button-primary" type="submit">Salvar auditoria</button></div>
            </div>
        </div>
    </form>
</x-app-layout>
