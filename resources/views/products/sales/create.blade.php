<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Vendas</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Nova venda</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Crie uma comanda avulsa com servicos, produtos ou os dois juntos.</p>
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
            ]])) }},
            productSearch: '',
            formatMoney(value) {
                return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            },
            addService() { this.serviceItems.push({ service_id: '' }) },
            removeService(index) { this.serviceItems.splice(index, 1) },
            addProduct(productId = '') { this.productItems.push({ product_id: productId, quantity: 1 }) },
            removeProduct(index) { this.productItems.splice(index, 1) },
            get filteredProducts() {
                const term = this.productSearch.trim().toLowerCase();

                if (!term) {
                    return [];
                }

                return Object.entries(this.products)
                    .filter(([id, product]) => (product.sku || '').toLowerCase().includes(term) || `${product.name}`.toLowerCase().includes(term))
                    .slice(0, 6);
            },
            selectProduct(productId) {
                const emptyItem = this.productItems.find((item) => !item.product_id);

                if (emptyItem) {
                    emptyItem.product_id = productId;
                    emptyItem.quantity = emptyItem.quantity || 1;
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
        class="grid w-full min-w-0 gap-4 overflow-x-hidden xl:grid-cols-[minmax(0,1fr)_320px]"
    >
        @csrf

        <section class="space-y-4">
            <div class="sf-card min-w-0 p-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="client_id" value="Cliente" />
                        <select id="client_id" name="client_id" class="sf-select mt-2 block w-full" required>
                            <option value="">Selecione um cliente</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected(old('client_id', $prefilledClientId) == $client->id)>{{ $client->name }} - {{ $client->phone }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="user_id" value="Profissional responsavel" />
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

                    <div>
                        <x-input-label for="sold_at" value="Data da venda" />
                        <x-text-input id="sold_at" name="sold_at" type="datetime-local" class="mt-2 block w-full" :value="old('sold_at', now()->format('Y-m-d\\TH:i'))" />
                        <x-input-error :messages="$errors->get('sold_at')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="notes" value="Observacoes" />
                        <textarea id="notes" name="notes" rows="2" class="sf-input mt-2 block w-full">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="sf-card min-w-0 p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Servicos realizados</p>
                        <h3 class="mt-2 text-xl font-semibold text-white">Servicos da comanda</h3>
                    </div>
                    <button type="button" @click="addService()" class="sf-button-ghost px-3 py-2 text-xs">+ Adicionar servico</button>
                </div>

                <div class="mt-4 space-y-3">
                    <template x-for="(item, index) in serviceItems" :key="index">
                        <div class="grid gap-3 rounded-2xl border border-white/10 bg-[#132746] p-3 md:grid-cols-[minmax(0,1fr)_120px_96px]">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Servico</label>
                                <select class="sf-select mt-2 block w-full" :name="`service_items[${index}][service_id]`" x-model="item.service_id" required>
                                    <option value="">Selecione</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}">{{ $service->name }} - {{ $service->duration_minutes }} min - R$ {{ number_format((float) $service->price, 2, ',', '.') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-[#0f203b] px-3 py-2">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">Preco</p>
                                <p class="mt-1 text-sm font-semibold text-white" x-text="item.service_id ? formatMoney(services[item.service_id]?.price) : 'R$ 0,00'"></p>
                                <p class="mt-1 text-xs text-[#c7d2e3]" x-text="item.service_id ? `${services[item.service_id]?.duration} min` : 'Duracao'"></p>
                            </div>
                            <div class="flex items-end">
                                <button type="button" @click="removeService(index)" class="sf-button-ghost w-full px-3 py-2 text-xs">Remover</button>
                            </div>
                        </div>
                    </template>

                    <div x-show="serviceItems.length === 0" class="rounded-2xl border border-dashed border-white/15 bg-[#132746] p-4 text-sm text-[#c7d2e3]">
                        Nenhum servico adicionado. Use esta area para registrar atendimento sem agendamento.
                    </div>
                    <x-input-error :messages="$errors->get('service_items')" class="mt-2" />
                </div>
            </div>

            <div class="sf-card min-w-0 p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Produtos vendidos</p>
                        <h3 class="mt-2 text-xl font-semibold text-white">Produtos da comanda</h3>
                    </div>
                    <button type="button" @click="addProduct()" class="sf-button-ghost px-3 py-2 text-xs">+ Adicionar produto</button>
                </div>

                <div class="mt-4 rounded-2xl border border-white/10 bg-[#132746] p-3">
                    <label for="product_search" class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Buscar por SKU ou nome</label>
                    <input
                        id="product_search"
                        type="text"
                        x-model="productSearch"
                        placeholder="Ex: SHP-100 ou nome do produto"
                        class="sf-input mt-2 block w-full"
                    >

                    <div x-show="filteredProducts.length > 0" x-cloak class="mt-3 grid gap-2 sm:grid-cols-2">
                        <template x-for="[productId, product] in filteredProducts" :key="productId">
                            <button type="button" @click="selectProduct(productId)" class="flex w-full items-center gap-3 rounded-2xl border border-white/10 bg-[#1b335b]/70 px-3 py-2.5 text-left transition hover:border-[#d4af37]/40 hover:bg-[#173056]">
                                <template x-if="product.image_url">
                                    <img :src="product.image_url" :alt="product.name" class="h-10 w-10 rounded-xl object-cover ring-1 ring-white/10">
                                </template>
                                <template x-if="!product.image_url">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-dashed border-white/10 bg-[#0f203b] text-[#d4af37]">+</div>
                                </template>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-white" x-text="product.name"></p>
                                    <p class="mt-1 text-xs text-[#c7d2e3]" x-text="`${product.sku || 'Sem SKU'} - ${formatMoney(product.price)} - Estoque ${product.stock_quantity}`"></p>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <template x-for="(item, index) in productItems" :key="index">
                        <div class="grid gap-3 rounded-2xl border border-white/10 bg-[#132746] p-3 lg:grid-cols-[minmax(0,1fr)_96px_120px_96px]">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Produto</label>
                                <select class="sf-select mt-2 block w-full" :name="`items[${index}][product_id]`" x-model="item.product_id" required>
                                    <option value="">Selecione</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->sku ?: 'Sem SKU' }} - {{ $product->name }} - R$ {{ number_format((float) $product->price, 2, ',', '.') }} - Estoque {{ $product->stock_quantity }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Qtd.</label>
                                <input type="number" min="1" class="sf-input mt-2 block w-full" :name="`items[${index}][quantity]`" x-model="item.quantity" required>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-[#0f203b] px-3 py-2">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">Subtotal</p>
                                <p class="mt-1 text-sm font-semibold text-white" x-text="item.product_id ? formatMoney((products[item.product_id]?.price || 0) * (item.quantity || 0)) : 'R$ 0,00'"></p>
                            </div>
                            <div class="flex items-end">
                                <button type="button" @click="removeProduct(index)" class="sf-button-ghost w-full px-3 py-2 text-xs">Remover</button>
                            </div>
                        </div>
                    </template>

                    <div x-show="productItems.length === 0" class="rounded-2xl border border-dashed border-white/15 bg-[#132746] p-4 text-sm text-[#c7d2e3]">
                        Nenhum produto adicionado. Produtos nao alteram a duracao do atendimento.
                    </div>
                    <x-input-error :messages="$errors->get('items')" class="mt-2" />
                </div>
            </div>
        </section>

        <aside class="sf-card h-fit p-5 xl:sticky xl:top-24">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Resumo da venda</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">Comanda avulsa</h3>
            <p class="mt-3 text-sm leading-6 text-[#c7d2e3]">
                Esta venda nao ocupa a agenda. Ela fecha uma comanda sem agendamento vinculado.
            </p>

            <div class="mt-5 space-y-3">
                <div class="rounded-2xl border border-white/10 bg-[#132746] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">Servicos</p>
                    <p class="mt-2 text-lg font-semibold text-white" x-text="formatMoney(subtotalServices)"></p>
                    <p class="mt-1 text-xs text-[#c7d2e3]" x-text="`${totalDuration} min informativos`"></p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-[#132746] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">Produtos</p>
                    <p class="mt-2 text-lg font-semibold text-white" x-text="formatMoney(subtotalProducts)"></p>
                    <p class="mt-1 text-xs text-[#c7d2e3]">Baixa de estoque no fechamento.</p>
                </div>

                <div class="rounded-2xl border border-[#d4af37]/40 bg-[#172f55] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Total</p>
                    <p class="mt-2 text-3xl font-semibold text-white" x-text="formatMoney(total)"></p>
                </div>
            </div>

            <button class="sf-button-primary mt-5 w-full">Salvar venda</button>
        </aside>
    </form>
</x-app-layout>
