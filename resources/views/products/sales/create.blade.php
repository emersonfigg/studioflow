<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="sf-page-eyebrow">Vendas</p>
                <h2 class="sf-page-title mt-1">Registrar venda</h2>
                <p class="mt-1 text-sm leading-5 sf-text-muted">Crie uma comanda avulsa com serviços, produtos ou os dois juntos.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('product-sales.index') }}" class="sf-button-ghost">Voltar</a>
                <button form="sale-form" class="sf-button-primary">Salvar venda</button>
            </div>
        </div>
    </x-slot>

    <form
        id="sale-form"
        method="POST"
        action="{{ route('product-sales.store') }}"
        x-data="{
            serviceItems: {{ Js::from(old('service_items', [])) }},
            productItems: {{ Js::from(old('items', [])) }},
            services: {{ Js::from($services->mapWithKeys(fn ($service) => [$service->id => [
                'name' => $service->name,
                'duration' => (int) $service->duration_minutes,
                'price' => (float) $service->price,
                'formatted_price' => number_format((float) $service->price, 2, ',', '.'),
            ]])) }},
            products: {{ Js::from($products->mapWithKeys(fn ($product) => [$product->id => [
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'formatted_price' => number_format((float) $product->price, 2, ',', '.'),
                'image_url' => $product->image_url,
                'stock_quantity' => $product->stock_quantity,
                'commission' => $product->hasCommission(),
            ]])) }},
            defaultSellerId: {{ (int) old('user_id', auth()->id()) }},
            productSearch: '',
            formatMoney(value) {
                return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            },
            addService() { this.serviceItems.push({ service_id: '' }) },
            removeService(index) { this.serviceItems.splice(index, 1) },
            addProduct(productId = '') { this.productItems.push({ product_id: productId, quantity: 1, seller_id: this.defaultSellerId || '' }) },
            removeProduct(index) { this.productItems.splice(index, 1) },
            productHasCommission(productId) {
                const product = this.products[productId];
                return product ? Boolean(product.commission) : false;
            },
            get filteredProducts() {
                const term = this.productSearch.trim().toLowerCase();

                if (! term) {
                    return [];
                }

                return Object.entries(this.products)
                    .filter(([id, product]) => (product.sku || '').toLowerCase().includes(term) || `${product.name}`.toLowerCase().includes(term))
                    .slice(0, 6);
            },
            selectProduct(productId) {
                const emptyItem = this.productItems.find((item) => ! item.product_id);

                if (emptyItem) {
                    emptyItem.product_id = productId;
                    emptyItem.quantity = emptyItem.quantity || 1;
                    emptyItem.seller_id = emptyItem.seller_id || this.defaultSellerId || '';
                } else {
                    this.addProduct(productId);
                }

                this.productSearch = '';
            },
            get subtotalServices() {
                return this.serviceItems.reduce((total, item) => total + Number(this.services[item.service_id]?.price || 0), 0);
            },
            get subtotalProducts() {
                return this.productItems.reduce((total, item) => total + (Number(this.products[item.product_id]?.price || 0) * Number(item.quantity || 0)), 0);
            },
            get total() {
                return this.subtotalServices + this.subtotalProducts;
            },
            get totalDuration() {
                return this.serviceItems.reduce((total, item) => total + Number(this.services[item.service_id]?.duration || 0), 0);
            },
        }"
        class="sf-operation-page sf-operation-page--fixed grid w-full min-w-0 overflow-x-hidden xl:grid-cols-[minmax(0,1fr)_300px]"
    >
        @csrf

        <section class="sf-operation-column space-y-3">
            <div class="sf-operation-card p-4">
                <div class="grid gap-3 xl:grid-cols-4">
                    <div class="xl:col-span-2">
                        <x-input-label for="client_id" value="Cliente" />
                        <select id="client_id" name="client_id" class="sf-select mt-2 block w-full" required>
                            <option value="">Selecione um cliente</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected(old('client_id', $prefilledClientId) == $client->id)>{{ $client->client_code ?? '-' }} · {{ $client->name }} · {{ $client->phone }}{{ $client->cpf ? ' · CPF '.$client->cpf : '' }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="user_id" value="Profissional responsável" />
                        <select id="user_id" name="user_id" class="sf-select mt-2 block w-full" required>
                            @foreach ($professionals as $professional)
                                <option value="{{ $professional->id }}" @selected(old('user_id', auth()->id()) == $professional->id)>{{ $professional->name }}</option>
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

                    <div class="xl:col-span-2">
                        <x-input-label for="sold_at" value="Data da venda" />
                        <x-text-input id="sold_at" name="sold_at" type="datetime-local" class="mt-2 block w-full" :value="old('sold_at', now()->format('Y-m-d\\TH:i'))" />
                        <x-input-error :messages="$errors->get('sold_at')" class="mt-2" />
                    </div>

                    <div class="xl:col-span-2">
                        <x-input-label for="notes" value="Observações" />
                        <textarea id="notes" name="notes" rows="2" class="sf-input mt-2 block w-full">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="sf-operation-scroll space-y-3">
                <div class="sf-operation-card p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="sf-section-title">Serviços</p>
                            <h3 class="mt-1 text-base font-semibold text-[var(--text-main)]">Serviços da comanda</h3>
                        </div>
                        <button type="button" @click="addService()" class="sf-button-ghost px-3 py-2 text-xs">+ Adicionar serviço</button>
                    </div>

                    <div class="mt-3 space-y-2">
                        <template x-for="(item, index) in serviceItems" :key="index">
                            <div class="sf-operation-item-row md:grid-cols-[minmax(0,1fr)_120px_92px]">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Serviço</label>
                                    <select class="sf-select mt-2 block w-full" :name="`service_items[${index}][service_id]`" x-model="item.service_id" required>
                                        <option value="">Selecione</option>
                                        @foreach ($services as $service)
                                            <option value="{{ $service->id }}">{{ $service->name }} · {{ $service->duration_minutes }} min · R$ {{ number_format((float) $service->price, 2, ',', '.') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-[var(--app-shell-bg)] px-3 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] sf-text-muted">Preço</p>
                                    <p class="mt-1 text-sm font-semibold text-[var(--text-main)]" x-text="item.service_id ? formatMoney(services[item.service_id]?.price) : 'R$ 0,00'"></p>
                                    <p class="mt-1 text-xs sf-text-muted" x-text="item.service_id ? `${services[item.service_id]?.duration} min` : 'Duração'"></p>
                                </div>
                                <div class="flex items-end">
                                    <button type="button" @click="removeService(index)" class="sf-button-ghost w-full px-3 py-2 text-xs">Remover</button>
                                </div>
                            </div>
                        </template>

                        <div x-show="serviceItems.length === 0" class="rounded-2xl border border-dashed border-white/15 bg-[var(--input-bg)] p-4 text-sm sf-text-muted">
                            Nenhum serviço adicionado. Use esta área para registrar atendimento sem agendamento.
                        </div>
                        <x-input-error :messages="$errors->get('service_items')" class="mt-2" />
                    </div>
                </div>

                <div class="sf-operation-card p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="sf-section-title">Produtos</p>
                            <h3 class="mt-1 text-base font-semibold text-[var(--text-main)]">Produtos da comanda</h3>
                        </div>
                        <button type="button" @click="addProduct()" class="sf-button-ghost px-3 py-2 text-xs">+ Adicionar produto</button>
                    </div>

                    <div class="sf-operation-search-grid mt-3 rounded-2xl border border-white/10 bg-[var(--input-bg)] p-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                        <div>
                            <label for="product_search" class="text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Buscar por SKU ou nome</label>
                            <input
                                id="product_search"
                                type="text"
                                x-model="productSearch"
                                placeholder="Ex: SHP-100 ou nome do produto"
                                class="sf-input mt-2 block w-full"
                            >

                            <div x-show="filteredProducts.length > 0" x-cloak class="mt-3 grid gap-2 xl:grid-cols-2">
                                <template x-for="[productId, product] in filteredProducts" :key="productId">
                                    <button type="button" @click="selectProduct(productId)" class="flex w-full items-center gap-3 rounded-2xl border border-white/10 bg-[var(--app-shell-bg)]/70 px-3 py-2 text-left transition hover:border-[color-mix(in_srgb,var(--brand-primary)_40%,transparent)] hover:bg-[color-mix(in_srgb,var(--input-bg)_78%,var(--brand-primary))]">
                                        <template x-if="product.image_url">
                                            <img :src="product.image_url" :alt="product.name" class="h-9 w-9 rounded-xl object-cover ring-1 ring-white/10">
                                        </template>
                                        <template x-if="!product.image_url">
                                            <div class="flex h-9 w-9 items-center justify-center rounded-xl border border-dashed border-white/10 bg-[var(--app-shell-bg)] brand-text">+</div>
                                        </template>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-[var(--text-main)]" x-text="product.name"></p>
                                            <p class="mt-1 text-xs sf-text-muted" x-text="`${product.sku || 'Sem SKU'} · ${formatMoney(product.price)} · Estoque ${product.stock_quantity}`"></p>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_8%,transparent)] px-3 py-3 text-xs sf-text-muted xl:max-w-[210px]">
                            Digite SKU ou nome para puxar o produto rápido e manter a venda fluindo no balcão.
                        </div>
                    </div>

                    <div class="mt-3 space-y-2">
                        <template x-for="(item, index) in productItems" :key="index">
                            <div class="sf-operation-item-row lg:grid-cols-[minmax(0,1fr)_132px_82px_110px_92px]">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Produto</label>
                                    <select class="sf-select mt-2 block w-full" :name="`items[${index}][product_id]`" x-model="item.product_id" required>
                                        <option value="">Selecione</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->sku ?: 'Sem SKU' }} · {{ $product->name }} · R$ {{ number_format((float) $product->price, 2, ',', '.') }} · Estoque {{ $product->stock_quantity }}{{ $product->hasCommission() ? ' · Comissão' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Vendedor</label>
                                    <select
                                        class="sf-select mt-2 block w-full"
                                        :name="`items[${index}][seller_id]`"
                                        x-model="item.seller_id"
                                        :class="productHasCommission(item.product_id) && !item.seller_id ? '!border-amber-400/60' : ''"
                                    >
                                        <option value="">— Sessão —</option>
                                        @foreach ($professionals as $professional)
                                            <option value="{{ $professional->id }}">{{ $professional->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-wide" x-show="productHasCommission(item.product_id) && !item.seller_id" x-cloak>
                                        <span class="text-amber-300">Obrigatório</span>
                                    </p>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Qtd.</label>
                                    <input type="number" min="1" class="sf-input mt-2 block w-full" :name="`items[${index}][quantity]`" x-model="item.quantity" required>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-[var(--app-shell-bg)] px-3 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] sf-text-muted">Subtotal</p>
                                    <p class="mt-1 text-sm font-semibold text-[var(--text-main)]" x-text="item.product_id ? formatMoney((products[item.product_id]?.price || 0) * (item.quantity || 0)) : 'R$ 0,00'"></p>
                                </div>
                                <div class="flex items-end">
                                    <button type="button" @click="removeProduct(index)" class="sf-button-ghost w-full px-3 py-2 text-xs">Remover</button>
                                </div>
                            </div>
                        </template>

                        <div x-show="productItems.length === 0" class="rounded-2xl border border-dashed border-white/15 bg-[var(--input-bg)] p-4 text-sm sf-text-muted">
                            Nenhum produto adicionado. Produtos não alteram a duração do atendimento.
                        </div>
                        <x-input-error :messages="$errors->get('items')" class="mt-2" />
                    </div>
                </div>
            </div>
        </section>

        <aside class="sf-operation-column sf-operation-card p-4 xl:sticky xl:top-24 xl:self-start">
            <p class="sf-section-title">Resumo da venda</p>
            <h3 class="mt-1 text-2xl font-semibold text-[var(--text-main)]">Comanda avulsa</h3>
            <p class="mt-2 text-sm leading-5 sf-text-muted">
                Esta venda não ocupa a agenda. Ela fecha uma comanda sem agendamento vinculado.
            </p>

            <div class="sf-operation-kpis mt-4">
                <div class="sf-operation-kpi">
                    <p class="sf-operation-kpi__label">Serviços</p>
                    <p class="sf-operation-kpi__value" x-text="formatMoney(subtotalServices)"></p>
                    <p class="mt-1 text-xs sf-text-muted" x-text="`${totalDuration} min informativos`"></p>
                </div>

                <div class="sf-operation-kpi">
                    <p class="sf-operation-kpi__label">Produtos</p>
                    <p class="sf-operation-kpi__value" x-text="formatMoney(subtotalProducts)"></p>
                    <p class="mt-1 text-xs sf-text-muted">Baixa de estoque no fechamento.</p>
                </div>

                <div class="sf-operation-kpi">
                    <p class="sf-operation-kpi__label">Total</p>
                    <p class="sf-operation-kpi__value brand-text" x-text="formatMoney(total)"></p>
                </div>
            </div>

            <button class="sf-button-primary mt-5 w-full">Salvar venda</button>
        </aside>
    </form>
</x-app-layout>
