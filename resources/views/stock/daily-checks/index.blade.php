<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Conferencia Diaria</h1>
                <p class="mt-1 text-sm sf-text-muted">Rotina operacional diaria para conferir o fisico com base nas movimentacoes de ontem.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stock.index') }}" class="sf-button-secondary">Voltar</a>
                <a href="{{ route('stock.daily-checks.create') }}" class="sf-button-primary">Gerar diaria</a>
            </div>
        </div>
    </x-slot>

    <div class="sf-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-sm">
                <thead class="text-left text-xs uppercase tracking-[0.16em] sf-text-muted">
                    <tr>
                        <th class="px-5 py-3">Referencia</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Usuario</th>
                        <th class="px-5 py-3">Itens</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                    @forelse ($checks as $check)
                        <tr>
                            <td class="px-5 py-4 sf-text">{{ $check->reference_date?->format('d/m/Y') }}</td>
                            <td class="px-5 py-4 sf-text-muted">{{ $check->status }}</td>
                            <td class="px-5 py-4 sf-text-muted">{{ $check->user?->name ?? '-' }}</td>
                            <td class="px-5 py-4 sf-text-muted">{{ $check->items()->count() }}</td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('stock.daily-checks.show', $check) }}" class="sf-button-secondary">Abrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center sf-text-muted">Nenhuma conferencia diaria gerada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] px-5 py-4">{{ $checks->links() }}</div>
    </div>
</x-app-layout>
