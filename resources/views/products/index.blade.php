<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Produtos</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Catalogo e vendas da empresa</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Cadastre produtos, acompanhe o giro e registre vendas ligadas aos clientes.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('product-sales.index') }}" class="sf-button-ghost">Histórico de vendas</a>
                <a href="{{ route('product-sales.create') }}" class="sf-button-secondary">Nova venda</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('products.create') }}" class="sf-button-primary">+ Novo produto</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                {{ match (session('status')) {
                    'product-created' => 'Produto cadastrado com sucesso.',
                    'product-updated' => 'Produto atualizado com sucesso.',
                    'product-deleted' => 'Produto removido com sucesso.',
                    default => session('status'),
                } }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Produtos ativos</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $activeProductsCount }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Preço medio</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($averagePrice, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Itens vendidos</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $soldItemsCount }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Receita de produtos</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($inventoryRevenue, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <div class="space-y-4">
                @forelse ($products as $product)
                    <article class="sf-card p-5 transition hover:-translate-y-0.5 hover:border-[#d4af37]/20 hover:shadow-[0_18px_40px_rgba(9,20,45,0.28)]">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div class="flex min-w-0 items-start gap-4">
                                @if ($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="Imagem de {{ $product->name }}" class="h-20 w-20 rounded-2xl object-cover ring-1 ring-white/10">
                                @else
                                    <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-dashed border-white/10 bg-[#132746] text-[#d4af37]">
                                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M7.5 6A2.5 2.5 0 005 8.5v7A2.5 2.5 0 007.5 18h9a2.5 2.5 0 002.5-2.5v-7A2.5 2.5 0 0016.5 6h-9zm0 1.5h9A1 1 0 0117.5 8.5v4.085l-2.23-2.23a1.75 1.75 0 00-2.475 0l-2.92 2.92-1.17-1.17a1.75 1.75 0 00-2.475 0L6.5 13.835V8.5a1 1 0 011-1zm8.75 2.25a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zM7.29 13.166a.25.25 0 01.354 0l1.7 1.7a.75.75 0 001.06 0l3.451-3.45a.25.25 0 01.354 0l2.291 2.29v1.794a1 1 0 01-1 1h-9a1 1 0 01-1-1v-.544l1.79-1.79z" />
                                        </svg>
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-lg font-semibold text-white">{{ $product->name }}</h3>
                                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] {{ $product->active ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-white/10 bg-white/5 text-[#c7d2e3]' }}">
                                            {{ $product->active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-[#c7d2e3]">{{ $product->description ?: 'Sem descrição cadastrada.' }}</p>
                                </div>
                            </div>

                            <div class="text-left md:text-right">
                                <p class="text-2xl font-semibold text-white">R$ {{ number_format((float) $product->price, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">{{ $product->sku ?: 'Sem SKU' }}</p>
                            </div>
                        </div>

                        @if (auth()->user()->isAdmin())
                            <div class="mt-5 flex flex-wrap gap-3">
                                <a href="{{ route('products.edit', $product) }}" class="sf-button-ghost">Editar</a>
                                <form method="POST" action="{{ route('products.destroy', $product) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button onclick="return confirm('Excluir este produto?')">
                                        Excluir
                                    </x-danger-button>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <article class="sf-card p-8 text-sm text-[#c7d2e3]">
                        Nenhum produto cadastrado ainda.
                    </article>
                @endforelse

                <div>
                    {{ $products->links() }}
                </div>
            </div>

            <aside class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-white">Últimas vendas de produtos</h3>
                </div>
                <div class="space-y-3 px-5 py-5">
                    @forelse ($recentSaleItems as $item)
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ $item->product->name }}</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">{{ $item->sale->client->name }}</p>
                                    <p class="mt-1 text-xs text-[#c7d2e3]">{{ $item->sale->sold_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-[#d4af37]">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</p>
                                    <p class="mt-1 text-xs text-[#c7d2e3]">{{ $item->quantity }} un.</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-4 text-sm text-[#c7d2e3]">
                            Nenhuma venda registrada ainda.
                        </div>
                    @endforelse
                </div>
            </aside>
        </section>
    </div>
</x-app-layout>
