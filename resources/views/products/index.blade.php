<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Produtos</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Catalogo e vendas da empresa</h2>
                <p class="mt-2 text-sm sf-text-muted">Cadastre produtos, acompanhe o giro e registre vendas ligadas aos clientes.</p>
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
        @if (($lowStockProducts ?? collect())->isNotEmpty())
            <section class="sf-card border border-amber-500/30 p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-200">Alerta</p>
                        <h3 class="mt-1 text-lg font-semibold text-[var(--text-main)]">Produtos com estoque baixo</h3>
                        <p class="mt-1 text-sm sf-text-muted">Itens em ou abaixo do mínimo com alerta ativo.</p>
                    </div>
                </div>
                <ul class="mt-4 space-y-2">
                    @foreach ($lowStockProducts as $p)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-white/10 bg-[var(--input-bg)] px-4 py-3 text-sm">
                            <span class="font-medium text-[var(--text-main)]">{{ $p->name }}</span>
                            <span class="sf-text-muted">{{ $p->stock_quantity }} / mín. {{ $p->minimum_stock }}</span>
                            <a href="{{ route('products.show', $p) }}" class="text-sm font-semibold brand-text hover:underline">Abrir</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                {{ match (session('status')) {
                    'product-created' => 'Produto cadastrado com sucesso.',
                    'product-updated' => 'Produto atualizado com sucesso.',
                    'product-deleted' => 'Produto removido com sucesso.',
                    'stock-adjusted' => 'Estoque ajustado com sucesso.',
                    default => session('status'),
                } }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Produtos ativos</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $activeProductsCount }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Preço medio</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($averagePrice, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Itens vendidos</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $soldItemsCount }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Estoque total</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $stockTotal }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Receita de produtos</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($inventoryRevenue, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <div class="space-y-4">
                @forelse ($products as $product)
                    <article class="sf-card p-5 transition hover:-translate-y-0.5 hover:border-[color-mix(in_srgb,var(--brand-primary)_20%,transparent)] hover:shadow-[0_18px_40px_rgba(0,0,0,0.2)]">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div class="flex min-w-0 items-start gap-4">
                                <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl ring-1 ring-white/10">
                                    @if ($product->image_url)
                                        <img
                                            src="{{ $product->image_url }}"
                                            alt="Imagem de {{ $product->name }}"
                                            class="absolute inset-0 h-full w-full object-cover"
                                            loading="lazy"
                                            decoding="async"
                                            onerror="this.classList.add('hidden'); this.nextElementSibling?.classList.remove('hidden')"
                                        >
                                    @endif
                                    <div @class([
                                        'absolute inset-0 flex items-center justify-center rounded-2xl border border-dashed border-white/10 bg-[var(--input-bg)] brand-text',
                                        'hidden' => (bool) $product->image_url,
                                    ])>
                                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M7.5 6A2.5 2.5 0 005 8.5v7A2.5 2.5 0 007.5 18h9a2.5 2.5 0 002.5-2.5v-7A2.5 2.5 0 0016.5 6h-9zm0 1.5h9A1 1 0 0117.5 8.5v4.085l-2.23-2.23a1.75 1.75 0 00-2.475 0l-2.92 2.92-1.17-1.17a1.75 1.75 0 00-2.475 0L6.5 13.835V8.5a1 1 0 011-1zm8.75 2.25a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zM7.29 13.166a.25.25 0 01.354 0l1.7 1.7a.75.75 0 001.06 0l3.451-3.45a.25.25 0 01.354 0l2.291 2.29v1.794a1 1 0 01-1 1h-9a1 1 0 01-1-1v-.544l1.79-1.79z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-lg font-semibold text-[var(--text-main)]">
                                            <a href="{{ route('products.show', $product) }}" class="hover:underline">{{ $product->name }}</a>
                                        </h3>
                                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] {{ $product->active ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-white/10 bg-white/5 sf-text-muted' }}">
                                            {{ $product->active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm sf-text-muted">{{ $product->description ?: 'Sem descrição cadastrada.' }}</p>
                                </div>
                            </div>

                            <div class="text-left md:text-right">
                                <p class="text-2xl font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $product->price, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.18em] sf-text-muted">{{ $product->sku ?: 'Sem SKU' }}</p>
                                <p class="mt-2 text-sm font-semibold {{ $product->stock_quantity > 0 ? 'text-emerald-100' : 'text-rose-100' }}">
                                    Estoque: {{ $product->stock_quantity }} un.
                                </p>
                            </div>
                        </div>

                        @if (auth()->user()->isAdmin())
                            <div class="mt-5 flex flex-wrap gap-3">
                                <a href="{{ route('products.show', $product) }}" class="sf-button-secondary">Detalhes / estoque</a>
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
                    <article class="sf-card p-8 text-sm sf-text-muted">
                        Nenhum produto cadastrado ainda.
                    </article>
                @endforelse

                <div>
                    {{ $products->links() }}
                </div>
            </div>

            <aside class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Últimas vendas de produtos</h3>
                </div>
                <div class="space-y-3 px-5 py-5">
                    @forelse ($recentSaleItems as $item)
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-[var(--text-main)]">{{ $item->product->name }}</p>
                                    <p class="mt-1 text-sm sf-text-muted">{{ $item->sale->client->name }}</p>
                                    <p class="mt-1 text-xs sf-text-muted">{{ $item->sale->sold_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold brand-text">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</p>
                                    <p class="mt-1 text-xs sf-text-muted">{{ $item->quantity }} un.</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-white/10 bg-[var(--input-bg)] px-4 py-4 text-sm sf-text-muted">
                            Nenhuma venda registrada ainda.
                        </div>
                    @endforelse
                </div>
            </aside>
        </section>
    </div>
</x-app-layout>
