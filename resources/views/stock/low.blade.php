<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Estoque Baixo</h1>
            </div>
            <a href="{{ route('stock.index') }}" class="sf-button-secondary">Voltar</a>
        </div>
    </x-slot>

    <div class="sf-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-sm">
                <thead class="text-left text-xs uppercase tracking-[0.16em] sf-text-muted">
                    <tr>
                        <th class="px-5 py-3">Produto</th>
                        <th class="px-5 py-3">Atual</th>
                        <th class="px-5 py-3">Minimo</th>
                        <th class="px-5 py-3">Sugestao</th>
                        <th class="px-5 py-3">Ultimo movimento</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                    @forelse ($products as $product)
                        @php($last = $product->stockMovements()->first())
                        <tr>
                            <td class="px-5 py-4 font-semibold sf-text">{{ $product->name }}</td>
                            <td class="px-5 py-4 sf-text">{{ $product->stock_quantity }} {{ $product->unit }}</td>
                            <td class="px-5 py-4 sf-text-muted">{{ $product->minimum_stock }}</td>
                            <td class="px-5 py-4 text-[var(--brand-primary)]">{{ max(0, (float) $product->minimum_stock - (float) $product->stock_quantity) }} {{ $product->unit }}</td>
                            <td class="px-5 py-4 sf-text-muted">{{ $last?->movement_date?->format('d/m/Y H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center sf-text-muted">Nenhum produto abaixo do minimo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
