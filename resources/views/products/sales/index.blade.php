<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Produtos</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Vendas de produtos</h2>
            </div>

            <a href="{{ route('product-sales.create') }}" class="sf-button-primary">Nova venda</a>
        </div>
    </x-slot>

    <section class="sf-card overflow-hidden">
        <div class="border-b border-white/10 px-5 py-4">
            <h3 class="text-lg font-semibold text-white">Histórico de vendas</h3>
        </div>
        <div class="space-y-4 px-5 py-5">
            @forelse ($sales as $sale)
                <article class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-base font-semibold text-white">{{ $sale->client->name }}</p>
                            <p class="mt-1 text-sm text-[#c7d2e3]">{{ $sale->sold_at->format('d/m/Y H:i') }} · {{ $sale->payment_method }}</p>
                            <p class="mt-2 text-sm text-[#c7d2e3]">{{ $sale->items->map(fn ($item) => $item->product->name . ' x' . $item->quantity)->join(', ') }}</p>
                        </div>
                        <p class="text-lg font-semibold text-[#d4af37]">R$ {{ number_format((float) $sale->gross_amount, 2, ',', '.') }}</p>
                    </div>
                </article>
            @empty
                <div class="text-sm text-[#c7d2e3]">Nenhuma venda registrada.</div>
            @endforelse
        </div>
        <div class="px-5 pb-5">
            {{ $sales->links() }}
        </div>
    </section>
</x-app-layout>
