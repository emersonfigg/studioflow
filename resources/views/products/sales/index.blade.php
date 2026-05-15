<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Vendas</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Vendas registradas</h2>
            </div>

            <a href="{{ route('product-sales.create') }}" class="sf-button-primary">Nova venda</a>
        </div>
    </x-slot>

    <section class="sf-card overflow-hidden">
        <div class="border-b border-white/10 px-5 py-4">
            <h3 class="text-lg font-semibold text-[var(--text-main)]">Historico de vendas</h3>
        </div>
        <div class="space-y-4 px-5 py-5">
            @forelse ($sales as $sale)
                <article class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-base font-semibold text-[var(--text-main)]">{{ $sale->client->name }}</p>
                            <p class="mt-1 text-sm sf-text-muted">{{ $sale->closed_at?->format('d/m/Y H:i') }} - {{ $sale->professional?->name ?? 'Sem profissional' }}</p>
                            <p class="mt-2 text-sm sf-text-muted">
                                {{ $sale->items->map(fn ($item) => $item->description . ($item->type === 'product' ? ' x' . $item->quantity : ''))->join(', ') }}
                            </p>
                        </div>
                        <p class="text-lg font-semibold brand-text">R$ {{ number_format((float) $sale->total, 2, ',', '.') }}</p>
                    </div>
                </article>
            @empty
                <div class="text-sm sf-text-muted">Nenhuma venda registrada.</div>
            @endforelse
        </div>
        <div class="px-5 pb-5">
            {{ $sales->links() }}
        </div>
    </section>

    @if ($legacySales->isNotEmpty())
        <section class="sf-card mt-4 overflow-hidden">
            <div class="border-b border-white/10 px-5 py-4">
                <h3 class="text-lg font-semibold text-[var(--text-main)]">Vendas antigas de produtos</h3>
                <p class="mt-1 text-sm sf-text-muted">Registros feitos antes da comanda avulsa.</p>
            </div>
            <div class="space-y-4 px-5 py-5">
                @foreach ($legacySales as $legacySale)
                    <article class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-base font-semibold text-[var(--text-main)]">{{ $legacySale->client->name }}</p>
                                <p class="mt-1 text-sm sf-text-muted">{{ $legacySale->sold_at->format('d/m/Y H:i') }} - {{ $legacySale->user?->name ?? 'Sem profissional' }}</p>
                                <p class="mt-2 text-sm sf-text-muted">{{ $legacySale->items->map(fn ($item) => $item->product->name . ' x' . $item->quantity)->join(', ') }}</p>
                            </div>
                            <p class="text-lg font-semibold brand-text">R$ {{ number_format((float) $legacySale->gross_amount, 2, ',', '.') }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</x-app-layout>
