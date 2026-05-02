<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Produtos</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Registrar venda</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Toda venda entra no histórico do cliente e no caixa do dia.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('products.index') }}" class="sf-button-ghost">Voltar</a>
                <button form="sale-form" class="sf-button-primary">Salvar venda</button>
            </div>
        </div>
    </x-slot>

    <form id="sale-form" method="POST" action="{{ route('product-sales.store') }}" x-data="{
        items: {{ Js::from(old('items', [['product_id' => null, 'quantity' => 1]])) }},
        products: {{ Js::from($products->mapWithKeys(fn ($product) => [$product->id => [
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => number_format((float) $product->price, 2, ',', '.'),
            'image_url' => $product->image_url,
            'stock_quantity' => $product->stock_quantity,
        ]])) }},
        skuSearch: '',
        get filteredProducts() {
            const term = this.skuSearch.trim().toLowerCase();

            if (!term) {
                return [];
            }

            return Object.entries(this.products)
                .filter(([id, product]) => {
                    return (product.sku || '').toLowerCase().includes(term)
                        || `${product.name}`.toLowerCase().includes(term);
                })
                .slice(0, 6);
        },
        addItem() { this.items.push({ product_id: null, quantity: 1 }) },
        removeItem(index) { this.items.splice(index, 1); if (this.items.length === 0) this.addItem(); },
        applySkuSearch() {
            const term = this.skuSearch.trim().toLowerCase();

            if (!term) {
                return;
            }

            const match = Object.entries(this.products).find(([id, product]) => {
                return (product.sku || '').toLowerCase() === term
                    || `${product.name}`.toLowerCase().includes(term);
            });

            if (!match) {
                return;
            }

            const [productId] = match;
            this.selectProduct(productId);
        },
        selectProduct(productId) {
            const emptyItem = this.items.find((item) => !item.product_id);

            if (emptyItem) {
                emptyItem.product_id = productId;
                emptyItem.quantity = emptyItem.quantity || 1;
            } else {
                this.items.push({ product_id: productId, quantity: 1 });
            }

            this.skuSearch = '';
        },
    }" class="grid w-full min-w-0 gap-4 overflow-x-hidden xl:grid-cols-[minmax(0,1fr)_280px]">
        @csrf

        <section class="sf-card min-w-0 p-5">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <x-input-label for="client_id" value="Cliente" />
                    <select id="client_id" name="client_id" class="sf-select mt-2 block w-full" required>
                        <option value="">Selecione</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id', $prefilledClientId) == $client->id)>{{ $client->name }} · {{ $client->phone }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="user_id" value="Profissional responsavel" />
                    <select id="user_id" name="user_id" class="sf-select mt-2 block w-full">
                        <option value="">Usar usuario atual</option>
                        @foreach ($professionals as $professional)
                            <option value="{{ $professional->id }}" @selected(old('user_id') == $professional->id)>{{ $professional->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="payment_method" value="Forma de pagamento" />
                    <select id="payment_method" name="payment_method" class="sf-select mt-2 block w-full" required>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="sold_at" value="Data da venda" />
                    <x-text-input id="sold_at" name="sold_at" type="datetime-local" class="mt-2 block w-full" :value="old('sold_at', now()->format('Y-m-d\\TH:i'))" />
                    <x-input-error :messages="$errors->get('sold_at')" class="mt-2" />
                </div>

                <div class="md:col-span-3">
                    <x-input-label for="notes" value="Observações" />
                    <textarea id="notes" name="notes" rows="2" class="sf-input mt-2 block w-full">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white">Itens vendidos</h3>
                    <button type="button" @click="addItem()" class="sf-button-ghost px-3 py-2 text-xs">+ Adicionar item</button>
                </div>

                <div class="mt-3 grid gap-3 rounded-2xl border border-white/10 bg-[#132746] px-4 py-3 md:grid-cols-[minmax(0,1fr)_auto]">
                    <div class="min-w-0">
                        <label for="product_sku_search" class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Buscar por SKU ou nome</label>
                        <input
                            id="product_sku_search"
                            type="text"
                            x-model="skuSearch"
                            @keydown.enter.prevent="applySkuSearch()"
                            placeholder="Ex: SHP-100 ou nome do produto"
                            class="sf-input mt-2 block w-full"
                        >
                        <p class="mt-2 text-[11px] text-[#c7d2e3]">Atalho rápido para localizar item sem rolar listas grandes.</p>

                        <div x-show="filteredProducts.length > 0" x-cloak class="mt-3 grid gap-2 sm:grid-cols-2">
                            <template x-for="[productId, product] in filteredProducts" :key="productId">
                                <button
                                    type="button"
                                    @click="selectProduct(productId)"
                                    class="flex w-full items-center gap-3 rounded-2xl border border-white/10 bg-[#1b335b]/70 px-3 py-2.5 text-left transition hover:border-[#d4af37]/40 hover:bg-[#173056]"
                                >
                                    <template x-if="product.image_url">
                                        <img :src="product.image_url" :alt="product.name" class="h-10 w-10 rounded-xl object-cover ring-1 ring-white/10">
                                    </template>
                                    <template x-if="!product.image_url">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-dashed border-white/10 bg-[#0f203b] text-[#d4af37]">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <path d="M7.5 6A2.5 2.5 0 005 8.5v7A2.5 2.5 0 007.5 18h9a2.5 2.5 0 002.5-2.5v-7A2.5 2.5 0 0016.5 6h-9zm0 1.5h9A1 1 0 0117.5 8.5v4.085l-2.23-2.23a1.75 1.75 0 00-2.475 0l-2.92 2.92-1.17-1.17a1.75 1.75 0 00-2.475 0L6.5 13.835V8.5a1 1 0 011-1zm8.75 2.25a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zM7.29 13.166a.25.25 0 01.354 0l1.7 1.7a.75.75 0 001.06 0l3.451-3.45a.25.25 0 01.354 0l2.291 2.29v1.794a1 1 0 01-1 1h-9a1 1 0 01-1-1v-.544l1.79-1.79z" />
                                                </svg>
                                        </div>
                                    </template>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-white" x-text="product.name"></p>
                                        <p class="mt-1 text-xs text-[#c7d2e3]" x-text="`${product.sku || 'Sem SKU'} · R$ ${product.price} · Estoque ${product.stock_quantity}`"></p>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-end">
                        <button type="button" @click="applySkuSearch()" class="sf-button-secondary w-full px-4 py-2.5 text-xs md:w-auto">Buscar SKU</button>
                    </div>
                </div>

                <div class="mt-3 space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="grid gap-3 rounded-2xl border border-white/10 bg-[#132746] p-3 lg:grid-cols-[64px_minmax(0,1fr)_84px_92px]">
                            <div class="flex items-center justify-center">
                                <template x-if="item.product_id && products[item.product_id]?.image_url">
                                    <img :src="products[item.product_id].image_url" :alt="products[item.product_id].name" class="h-16 w-16 rounded-2xl object-cover ring-1 ring-white/10">
                                </template>
                                <template x-if="! item.product_id || ! products[item.product_id]?.image_url">
                                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-white/10 bg-[#0f203b] text-[#d4af37]">
                                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M7.5 6A2.5 2.5 0 005 8.5v7A2.5 2.5 0 007.5 18h9a2.5 2.5 0 002.5-2.5v-7A2.5 2.5 0 0016.5 6h-9zm0 1.5h9A1 1 0 0117.5 8.5v4.085l-2.23-2.23a1.75 1.75 0 00-2.475 0l-2.92 2.92-1.17-1.17a1.75 1.75 0 00-2.475 0L6.5 13.835V8.5a1 1 0 011-1zm8.75 2.25a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zM7.29 13.166a.25.25 0 01.354 0l1.7 1.7a.75.75 0 001.06 0l3.451-3.45a.25.25 0 01.354 0l2.291 2.29v1.794a1 1 0 01-1 1h-9a1 1 0 01-1-1v-.544l1.79-1.79z" />
                                            </svg>
                                    </div>
                                </template>
                            </div>
                            <div class="min-w-0">
                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Produto</label>
                                <select class="sf-select mt-2 block w-full" :name="`items[${index}][product_id]`" x-model="item.product_id" required>
                                    <option value="">Selecione</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->sku ?: 'Sem SKU' }} · {{ $product->name }} · R$ {{ number_format((float) $product->price, 2, ',', '.') }} · Estoque {{ $product->stock_quantity }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs text-[#c7d2e3]" x-show="item.product_id && products[item.product_id]" x-text="item.product_id && products[item.product_id] ? `SKU ${products[item.product_id].sku || 'sem cadastro'} · R$ ${products[item.product_id].price} · Estoque ${products[item.product_id].stock_quantity}` : ''"></p>
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Qtd.</label>
                                <input type="number" min="1" class="sf-input mt-2 block w-full" :name="`items[${index}][quantity]`" x-model="item.quantity" required>
                            </div>
                            <div class="flex items-end">
                                <button type="button" @click="removeItem(index)" class="sf-button-ghost w-full px-3 py-2 text-xs">Remover</button>
                            </div>
                        </div>
                    </template>
                    <x-input-error :messages="$errors->get('items')" class="mt-2" />
                </div>
            </div>
        </section>

        <aside class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Venda vinculada</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">Histórico do cliente</h3>
            <p class="mt-3 text-sm leading-6 text-[#c7d2e3]">
                Assim que salvar, a compra entra no cadastro do cliente, no financeiro e no caixa do dia.
            </p>

            <div class="mt-5 space-y-3">
                @foreach ($products->take(4) as $product)
                    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-[#132746] px-3 py-2.5">
                        @if ($product->image_url)
                            <img src="{{ $product->image_url }}" alt="Imagem de {{ $product->name }}" class="h-12 w-12 rounded-xl object-cover ring-1 ring-white/10">
                        @else
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-dashed border-white/10 bg-[#0f203b] text-[#d4af37]">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M7.5 6A2.5 2.5 0 005 8.5v7A2.5 2.5 0 007.5 18h9a2.5 2.5 0 002.5-2.5v-7A2.5 2.5 0 0016.5 6h-9zm0 1.5h9A1 1 0 0117.5 8.5v4.085l-2.23-2.23a1.75 1.75 0 00-2.475 0l-2.92 2.92-1.17-1.17a1.75 1.75 0 00-2.475 0L6.5 13.835V8.5a1 1 0 011-1zm8.75 2.25a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zM7.29 13.166a.25.25 0 01.354 0l1.7 1.7a.75.75 0 001.06 0l3.451-3.45a.25.25 0 01.354 0l2.291 2.29v1.794a1 1 0 01-1 1h-9a1 1 0 01-1-1v-.544l1.79-1.79z" />
                                </svg>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ $product->name }}</p>
                            <p class="mt-1 text-xs text-[#c7d2e3]">{{ $product->sku ?: 'Sem SKU' }} · R$ {{ number_format((float) $product->price, 2, ',', '.') }} · Estoque {{ $product->stock_quantity }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </aside>
    </form>
</x-app-layout>
