<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Financeiro</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Concluir atendimento
                </h2>
            </div>
            <p class="max-w-xl text-sm leading-6 text-[#c7d2e3]">
                Registre o pagamento e finalize o atendimento com comissão calculada automaticamente.
            </p>
        </div>
    </x-slot>

    <div
        x-data="{
            serviceAmount: @js((string) old('gross_amount', $defaultGrossAmount)),
            items: @js(old('items', [])),
            products: @js($products->mapWithKeys(fn ($product) => [$product->id => [
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'formatted_price' => number_format((float) $product->price, 2, ',', '.'),
                'image_url' => $product->image_url,
            ]])),
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
            addItem() { this.items.push({ product_id: '', quantity: 1 }); },
            removeItem(index) { this.items.splice(index, 1); },
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
            itemTotal(item) {
                if (! item.product_id || ! this.products[item.product_id]) {
                    return 0;
                }

                return this.products[item.product_id].price * Number(item.quantity || 0);
            },
            productTotal() {
                return this.items.reduce((total, item) => total + this.itemTotal(item), 0);
            },
            grandTotal() {
                return this.parseCurrency(this.serviceAmount) + this.productTotal();
            },
            parseCurrency(value) {
                if (typeof value === 'number') {
                    return value;
                }

                const normalized = `${value || 0}`.replace(/[R$\s.]/g, '').replace(',', '.');

                return Number(normalized || 0);
            },
            formatCurrency(value) {
                return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
            }
        }"
        class="mx-auto grid w-full max-w-7xl min-w-0 gap-4 overflow-x-hidden xl:grid-cols-[320px_minmax(0,1fr)]"
    >
        <aside class="sf-card p-5">
            <h3 class="text-base font-semibold text-white">Resumo do atendimento</h3>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                <div class="rounded-2xl border border-white/10 bg-[#132746] px-3 py-3">
                    <dt class="text-sm text-[#c7d2e3]">Cliente</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $appointment->client->name }}</dd>
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#132746] px-3 py-3">
                    <dt class="text-sm text-[#c7d2e3]">Profissional</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $appointment->user->name }}</dd>
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#132746] px-3 py-3">
                    <dt class="text-sm text-[#c7d2e3]">Serviço</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $appointment->bookedServices()->pluck('name')->join(', ') }}</dd>
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#132746] px-3 py-3">
                    <dt class="text-sm text-[#c7d2e3]">Horário</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $appointment->start_time->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#132746] px-3 py-3 sm:col-span-2 xl:col-span-1">
                    <dt class="text-sm text-[#c7d2e3]">Comissão configurada</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">
                        @if ($appointment->user->commission_type === 'percent')
                            {{ number_format((float) $appointment->user->commission_value, 2, ',', '.') }}%
                        @elseif ($appointment->user->commission_type === 'fixed')
                            R$ {{ number_format((float) $appointment->user->commission_value, 2, ',', '.') }}
                        @else
                            Sem comissão
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="mt-4 rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Resumo financeiro</p>
                <div class="mt-4 space-y-3 text-sm text-[#c7d2e3]">
                    <div class="flex items-center justify-between gap-3">
                        <span>Serviços</span>
                        <span class="font-semibold text-white" x-text="formatCurrency(parseCurrency(serviceAmount))"></span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span>Produtos</span>
                        <span class="font-semibold text-white" x-text="formatCurrency(productTotal())"></span>
                    </div>
                    <div class="border-t border-white/10 pt-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold text-white">Total a cobrar</span>
                            <span class="text-lg font-semibold text-[#d4af37]" x-text="formatCurrency(grandTotal())"></span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <section class="sf-card min-w-0 p-5 sm:p-6">
            <form method="POST" action="{{ route('appointments.payments.store', $appointment) }}" class="space-y-4">
                @csrf

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div>
                    <x-input-label for="gross_amount" value="Valor dos serviços" />
                    <x-text-input
                        id="gross_amount"
                        name="gross_amount"
                        type="text"
                        inputmode="decimal"
                        placeholder="R$ 0,00"
                        class="mt-1 block w-full"
                        :value="old('gross_amount', $defaultGrossAmount)"
                        x-model="serviceAmount"
                        required
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('gross_amount')" />
                    </div>

                    <div>
                        <x-input-label for="payment_method" value="Forma de pagamento" />
                        <select id="payment_method" name="payment_method" class="sf-select mt-1 block w-full" required>
                            <option value="">Selecione</option>
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-white">Produtos vendidos neste atendimento</h3>
                            <p class="mt-1 text-xs leading-6 text-[#c7d2e3]">Adicione produtos e o sistema registra a venda no histórico do cliente e no caixa junto com o fechamento.</p>
                        </div>
                        <button type="button" @click="addItem()" class="sf-button-ghost px-3 py-2 text-xs">+ Adicionar produto</button>
                    </div>

                    <div class="mt-3 grid gap-3 rounded-2xl border border-white/10 bg-[#1b335b]/60 px-4 py-3 md:grid-cols-[minmax(0,1fr)_auto]">
                        <div class="min-w-0">
                            <label for="sku_search" class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Buscar por SKU ou nome</label>
                            <input
                                id="sku_search"
                                type="text"
                                x-model="skuSearch"
                                @keydown.enter.prevent="applySkuSearch()"
                                placeholder="Ex: POM-100 ou nome do produto"
                                class="sf-input mt-2 block w-full"
                            >
                            <p class="mt-2 text-[11px] text-[#c7d2e3]">Digite o SKU para localizar rápido e jogar o produto direto no fechamento.</p>

                            <div x-show="filteredProducts.length > 0" x-cloak class="mt-3 grid gap-2 sm:grid-cols-2">
                                <template x-for="[productId, product] in filteredProducts" :key="productId">
                                    <button
                                        type="button"
                                        @click="selectProduct(productId)"
                                        class="flex w-full items-center gap-3 rounded-2xl border border-white/10 bg-[#132746] px-3 py-2.5 text-left transition hover:border-[#d4af37]/40 hover:bg-[#173056]"
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
                                            <p class="mt-1 text-xs text-[#c7d2e3]" x-text="`${product.sku || 'Sem SKU'} · R$ ${product.formatted_price}`"></p>
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
                        <template x-if="items.length === 0">
                            <div class="rounded-2xl border border-dashed border-white/10 bg-[#1b335b]/60 px-4 py-3 text-sm text-[#c7d2e3]">
                                Nenhum produto adicional selecionado neste atendimento.
                            </div>
                        </template>

                        <template x-for="(item, index) in items" :key="index">
                            <div class="grid gap-3 rounded-2xl border border-white/10 bg-[#1b335b]/70 p-3 lg:grid-cols-[64px_minmax(0,1fr)_84px_100px_92px]">
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
                                    <select class="sf-select mt-2 block w-full" :name="`items[${index}][product_id]`" x-model="item.product_id">
                                        <option value="">Selecione</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->sku ?: 'Sem SKU' }} · {{ $product->name }} · R$ {{ number_format((float) $product->price, 2, ',', '.') }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-2 text-xs text-[#c7d2e3]" x-show="item.product_id && products[item.product_id]" x-text="item.product_id && products[item.product_id] ? `SKU ${products[item.product_id].sku || 'sem cadastro'}` : ''"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Qtd.</label>
                                    <input type="number" min="1" class="sf-input mt-2 block w-full" :name="`items[${index}][quantity]`" x-model="item.quantity">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Subtotal</label>
                                    <p class="mt-3 text-sm font-semibold text-white" x-text="formatCurrency(itemTotal(item))"></p>
                                </div>
                                <div class="flex items-end">
                                    <button type="button" @click="removeItem(index)" class="sf-button-ghost w-full px-3 py-2 text-xs">Remover</button>
                                </div>
                            </div>
                        </template>

                        <x-input-error class="mt-2" :messages="$errors->get('items')" />
                        @foreach ($errors->get('items.*') as $messages)
                            <x-input-error class="mt-2" :messages="$messages" />
                        @endforeach
                    </div>
                </div>

                <div>
                    <x-input-label for="notes" value="Observações" />
                    <textarea id="notes" name="notes" rows="3" class="sf-input mt-1 block w-full">{{ old('notes') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                </div>

                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route('appointments.show', $appointment) }}" class="text-sm font-medium text-[#c7d2e3] transition hover:text-white">
                        Voltar
                    </a>
                    <x-primary-button>
                        Confirmar pagamento
                    </x-primary-button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
