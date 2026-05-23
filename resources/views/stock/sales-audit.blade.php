<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Vendas x Estoque</h1>
                <p class="mt-1 text-sm sf-text-muted">Confere se os produtos vendidos foram baixados no estoque.</p>
            </div>
            <a href="{{ route('stock.index') }}" class="sf-button-secondary">Voltar</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <form method="GET" class="sf-card flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
            <div>
                <x-input-label for="date" value="Data" />
                <x-text-input id="date" name="date" type="date" class="mt-2 block w-full" :value="$date" />
            </div>
            <button class="sf-button-primary" type="submit">Conferir</button>
        </form>

        <div class="sf-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-sm">
                    <thead class="text-left text-xs uppercase tracking-[0.16em] sf-text-muted">
                        <tr>
                            <th class="px-5 py-3">Produto</th>
                            <th class="px-5 py-3">Vendido no PDV</th>
                            <th class="px-5 py-3">Baixado no estoque</th>
                            <th class="px-5 py-3">Diferenca</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-5 py-4 font-semibold sf-text">{{ $row['product']?->name ?? 'Produto removido' }}</td>
                                <td class="px-5 py-4 sf-text">{{ number_format($row['sold'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 sf-text">{{ number_format($row['moved'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ number_format($row['difference'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ abs($row['difference']) < 0.000001 ? 'bg-emerald-500/10 text-emerald-200' : 'bg-rose-500/10 text-rose-200' }}">
                                        {{ abs($row['difference']) < 0.000001 ? 'OK' : 'Divergente' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center sf-text-muted">Nenhuma venda de produto no periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
