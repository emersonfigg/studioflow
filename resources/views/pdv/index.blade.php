<x-app-layout>
    <div class="pdv-page mx-auto w-full max-w-[1800px] px-0 pb-6 sm:px-1 lg:px-2">
        <form
            method="POST"
            action="{{ route('pdv.store') }}"
            x-data="pdvScreen(catalog)"
            x-init="init()"
            x-on:keydown.window="handlePdvHotkeys($event)"
            x-on:keydown.slash.prevent="$refs.searchInput.focus()"
            class="pdv-frame flex min-h-[min(100vh,920px)] flex-col overflow-hidden rounded-2xl border border-[#d4af37]/25 bg-[#132746] shadow-[0_24px_48px_rgba(9,20,45,0.45)] ring-1 ring-[#d4af37]/10"
        >
            @csrf

            {{-- 2. Barra superior (identidade StudioFlow) --}}
            <header class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-white/10 bg-[#1b335b] px-4 py-2.5 text-xs text-[#c7d2e3] sm:px-5 sm:text-sm">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="whitespace-nowrap font-bold tracking-wide text-[#d4af37]">PDV — Ponto de Venda</span>
                    <span class="hidden h-4 w-px bg-white/15 sm:inline-block" aria-hidden="true"></span>
                    <span class="font-semibold text-white">Operador: <span class="text-[#c7d2e3]">{{ auth()->user()->name }}</span></span>
                </div>
                <div class="flex w-full flex-wrap items-end gap-3 lg:w-auto lg:flex-nowrap">
                    <label class="grid min-w-[140px] flex-1 gap-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-[#d4af37]/90 lg:max-w-[200px]">
                        Cliente
                        <select
                            name="client_id"
                            x-ref="clientSelect"
                            class="sf-select !border-white/15 !bg-[#223d69] !py-2 !text-sm !text-white"
                        >
                            <option value="">— Balcão —</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid min-w-[140px] flex-1 gap-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-[#d4af37]/90 lg:max-w-[200px]">
                        Profissional
                        <select
                            name="user_id"
                            x-ref="professionalSelect"
                            class="sf-select !border-white/15 !bg-[#223d69] !py-2 !text-sm !text-white"
                        >
                            <option value="">— Sessão —</option>
                            @foreach ($professionals as $professional)
                                <option value="{{ $professional->id }}">{{ $professional->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="flex w-full flex-wrap items-center justify-between gap-3 sm:w-auto sm:justify-end">
                    <span class="tabular-nums text-[#c7d2e3]" x-text="currentTime"></span>
                    <span
                        class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide
                        @if ($cashRegister?->closed_at)
                            border-white/20 bg-white/10 text-[#c7d2e3]
                        @elseif ($cashRegister)
                            border-[#d4af37]/40 bg-[#d4af37]/15 text-[#d4af37]
                        @else
                            border-amber-500/30 bg-amber-500/10 text-amber-200
                        @endif
                        "
                    >
                        @if ($cashRegister?->closed_at)
                            Caixa fechado
                        @elseif ($cashRegister)
                            Caixa aberto
                        @else
                            Caixa não iniciado
                        @endif
                    </span>
                </div>
            </header>

            {{-- 3. Faixa do item atual --}}
            <div class="border-b border-[#d4af37]/20 bg-gradient-to-r from-[#223d69] via-[#1b335b] to-[#132746] px-4 py-5 sm:px-6 sm:py-6">
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d4af37]" x-text="currentKindLabel"></p>
                <p class="mt-1 text-center text-xl font-bold uppercase leading-tight tracking-tight text-white sm:text-2xl md:text-3xl" x-text="bannerTitle"></p>
            </div>

            {{-- Corpo: 3 colunas (desktop) --}}
            <div class="grid flex-1 grid-cols-1 gap-0 lg:grid-cols-12 lg:divide-x lg:divide-white/10">
                {{-- 4. Esquerda: imagem / ícone + VENDA --}}
                <aside class="flex flex-col gap-4 border-b border-white/10 p-4 lg:col-span-3 lg:border-b-0 lg:p-5">
                    <div class="relative flex aspect-square max-h-[220px] min-h-[160px] w-full overflow-hidden rounded-2xl border border-white/10 bg-[#223d69] shadow-inner">
                        <template x-if="visualItem && visualItem.image_url && !previewImageFailed">
                            <img
                                :src="visualItem.image_url"
                                :alt="visualItem.name"
                                class="absolute inset-0 z-20 h-full w-full object-cover"
                                loading="lazy"
                                decoding="async"
                                x-on:error="previewImageFailed = true"
                            >
                        </template>
                        <div
                            class="relative z-10 flex h-full w-full items-center justify-center transition-opacity duration-150"
                            :class="visualItem && visualItem.image_url && !previewImageFailed ? 'pointer-events-none opacity-0' : 'opacity-100'"
                        >
                            <template x-if="visualItem && visualItem.type === 'product'">
                                <svg class="h-24 w-24 text-[#d4af37]/90" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M7 4h10l1 2h2v2H4V6h2l1-2zm0 4h10v10a2 2 0 01-2 2H9a2 2 0 01-2-2V8zm2 2v8h6v-8H9z"/>
                                </svg>
                            </template>
                            <template x-if="visualItem && visualItem.type === 'service'">
                                <svg class="h-24 w-24 text-[#d4af37]/90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                                </svg>
                            </template>
                            <template x-if="!visualItem">
                                <svg class="h-24 w-24 text-[#d4af37]/50" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                </svg>
                            </template>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-[#d4af37]/30 bg-[#d4af37]/10 py-4 text-center">
                        <span class="text-lg font-black uppercase tracking-[0.25em] text-[#d4af37] sm:text-xl">Venda</span>
                        <p class="mt-1 text-[10px] font-semibold uppercase text-[#c7d2e3]">Item atual</p>
                    </div>
                </aside>

                {{-- 5. Centro: campos grandes + sugestões --}}
                <section class="flex flex-col border-b border-white/10 p-4 lg:col-span-4 lg:border-b-0 lg:p-5">
                    <label class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#d4af37]">Código / SKU / Nome</label>
                    <input
                        x-ref="searchInput"
                        x-model="search"
                        x-on:keydown.arrow-down.prevent="highlightNext()"
                        x-on:keydown.arrow-up.prevent="highlightPrev()"
                        x-on:keydown.enter.prevent="selectHighlighted()"
                        type="text"
                        autofocus
                        autocomplete="off"
                        class="sf-input mt-2 !border-white/15 !bg-[#223d69] !py-4 !text-lg !font-semibold !text-white placeholder:text-[#c7d2e3]/50"
                        placeholder="Buscar ou escanear…"
                    >

                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#c7d2e3]">Quantidade</label>
                            <div class="sf-input mt-1 !border-white/15 !bg-[#1b335b] !py-4 !text-right !text-xl !font-bold !text-white tabular-nums" x-text="previewQty"></div>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#c7d2e3]">Preço unitário</label>
                            <div class="sf-input mt-1 !border-white/15 !bg-[#1b335b] !py-4 !text-right !text-xl !font-bold !text-[#d4af37] tabular-nums">
                                R$ <span x-text="formatMoneyBRL(previewUnit)"></span>
                            </div>
                        </div>
                        <div class="sm:col-span-1">
                            <label class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#c7d2e3]">Preço total</label>
                            <div class="sf-input mt-1 !border-[#d4af37]/35 !bg-[#223d69] !py-4 !text-right !text-xl !font-bold !text-white tabular-nums">
                                R$ <span x-text="formatMoneyBRL(previewLineTotal)"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 min-h-0 flex-1 overflow-hidden rounded-xl border border-white/10 bg-[#1b335b]/80">
                        <p class="border-b border-white/10 bg-[#132746] px-3 py-2 text-[10px] font-bold uppercase tracking-[0.18em] text-[#d4af37]">Sugestões</p>
                        <div class="max-h-48 overflow-y-auto p-1">
                            <template x-for="(item, idx) in filteredCatalog" :key="`${item.type}-${item.id}`">
                                <button
                                    type="button"
                                    x-on:click="addCatalogItem(item)"
                                    :class="highlightedIndex === idx ? 'border-[#d4af37]/50 bg-[#d4af37]/15' : 'border-transparent hover:bg-white/5'"
                                    class="w-full rounded-lg border px-3 py-2 text-left transition"
                                >
                                    <span class="font-mono text-xs font-bold text-[#d4af37]" x-text="item.code"></span>
                                    <span class="mt-0.5 block truncate text-sm text-white" x-text="item.name"></span>
                                </button>
                            </template>
                            <p x-show="filteredCatalog.length === 0" class="px-3 py-6 text-center text-sm text-[#c7d2e3]">Digite para filtrar.</p>
                        </div>
                    </div>
                </section>

                {{-- 6. Direita: cupom --}}
                <section class="flex min-h-[280px] flex-col p-4 lg:col-span-5 lg:p-5">
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#223d69] shadow-inner">
                        <div class="min-h-0 flex-1 overflow-x-auto overflow-y-auto">
                            <table class="w-full min-w-[520px] border-collapse text-left text-[11px] text-[#c7d2e3]">
                                <thead>
                                    <tr class="border-b border-dashed border-white/25 bg-[#132746] text-[10px] font-bold uppercase tracking-[0.12em] text-[#d4af37]">
                                        <th class="w-10 px-2 py-2 text-left">Item</th>
                                        <th class="w-20 px-1 py-2 text-left">Cód.</th>
                                        <th class="px-2 py-2 text-left">Descrição</th>
                                        <th class="w-16 px-1 py-2 text-right">Qtd</th>
                                        <th class="w-20 px-2 py-2 text-right">Vl.un.</th>
                                        <th class="w-20 px-2 py-2 text-right">Vl.item</th>
                                        <th class="w-12 px-1 py-2 text-center"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-if="cart.length === 0">
                                        <tr>
                                            <td colspan="7" class="px-4 py-12 text-center text-sm text-[#c7d2e3]">Nenhum item lançado.</td>
                                        </tr>
                                    </template>
                                    <template x-for="(item, index) in cart" :key="`${item.type}-${item.id}-${index}`">
                                        <tr class="border-b border-white/5 odd:bg-[#1b335b]/40 even:bg-transparent">
                                            <td class="w-10 px-2 py-2 font-mono text-white" x-text="String(index + 1).padStart(4, '0')"></td>
                                            <td class="w-20 px-1 py-2 font-mono text-[#d4af37]" x-text="item.code"></td>
                                            <td class="max-w-[1px] px-2 py-2">
                                                <span class="block truncate text-white" x-text="item.name"></span>
                                            </td>
                                            <td class="w-16 px-1 py-2">
                                                <input
                                                    x-model.number="item.quantity"
                                                    type="number"
                                                    min="1"
                                                    class="w-full rounded border border-white/15 bg-[#1b335b] px-1 py-1 text-right text-[11px] font-semibold text-white tabular-nums"
                                                >
                                            </td>
                                            <td class="w-20 whitespace-nowrap px-2 py-2 text-right tabular-nums" x-text="formatMoneyBRL(item.price)"></td>
                                            <td class="w-20 whitespace-nowrap px-2 py-2 text-right font-semibold text-white tabular-nums" x-text="formatMoneyBRL(item.price * Math.max(1, Number(item.quantity || 1)))"></td>
                                            <td class="w-12 px-1 py-2 text-center">
                                                <button
                                                    type="button"
                                                    class="text-[10px] font-bold uppercase text-rose-300 hover:text-rose-200"
                                                    x-on:click="removeItem(index)"
                                                >Excl.</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            {{-- 7 + 8. Rodapé totais + pagamento --}}
            <div class="border-t border-white/10 bg-[#0f203b] px-4 py-4 sm:px-5">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-6 lg:gap-4">
                    <div class="rounded-xl border border-white/10 bg-[#132746] p-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Volumes / Itens</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-white" x-text="formatQty(totalVolumeQty)"></p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-[#132746] p-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Subtotal serviços</p>
                        <p class="mt-1 text-lg font-bold text-[#c7d2e3]">R$ <span class="tabular-nums text-white" x-text="formatMoneyBRL(subtotalServices)"></span></p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-[#132746] p-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Subtotal produtos</p>
                        <p class="mt-1 text-lg font-bold text-[#c7d2e3]">R$ <span class="tabular-nums text-white" x-text="formatMoneyBRL(subtotalProducts)"></span></p>
                    </div>
                    <div class="col-span-2 rounded-xl border-2 border-[#d4af37]/40 bg-gradient-to-br from-[#223d69] to-[#132746] p-4 md:col-span-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#d4af37]">Total da venda</p>
                        <p class="mt-1 text-right text-4xl font-black tabular-nums text-[#d4af37] sm:text-5xl">
                            R$ <span x-text="formatMoneyBRL(total)"></span>
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                    <label class="lg:col-span-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Forma de pagamento</span>
                        <select
                            name="payment_method"
                            x-ref="paymentSelect"
                            required
                            class="sf-select mt-2 !w-full !border-white/15 !bg-[#223d69] !py-3 !text-white"
                        >
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="lg:col-span-5">
                        <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Observações</span>
                        <textarea
                            name="notes"
                            rows="2"
                            class="sf-input mt-2 !min-h-[3rem] !w-full !border-white/15 !bg-[#223d69] !text-white placeholder:text-[#c7d2e3]/40"
                            placeholder="Opcional"
                        ></textarea>
                    </label>
                    <div class="lg:col-span-3">
                        <button
                            x-ref="submitBtn"
                            type="submit"
                            class="sf-button-primary w-full !py-4 !text-base !font-black !uppercase !tracking-wider disabled:opacity-40"
                            :disabled="cart.length === 0"
                        >
                            Concluir venda
                        </button>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-4 rounded-xl border border-rose-400/40 bg-rose-950/40 px-4 py-3 text-sm text-rose-100">
                        {{ $errors->first() }}
                    </div>
                @endif
            </div>

            {{-- Hidden payload --}}
            <template x-for="(item, idx) in servicePayload" :key="`s-${idx}-${item.service_id}`">
                <input type="hidden" :name="`service_items[${idx}][service_id]`" :value="item.service_id">
            </template>
            <template x-for="(item, idx) in productPayload" :key="`p-${idx}-${item.product_id}`">
                <div>
                    <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id">
                    <input type="hidden" :name="`items[${idx}][quantity]`" :value="item.quantity">
                </div>
            </template>

            {{-- 9. Barra de atalhos --}}
            <footer class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 border-t border-white/10 bg-[#1b335b] px-3 py-2.5 text-[10px] font-semibold text-[#c7d2e3] sm:justify-between sm:text-[11px]">
                <div class="flex flex-wrap justify-center gap-x-3 gap-y-1 sm:justify-start">
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">F2</kbd> Cliente</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">F3</kbd> Busca</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">Enter</kbd> Adicionar</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">Del</kbd> Remover último</span>
                    <span class="hidden sm:inline"><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">↑↓</kbd> Lista</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">F8</kbd> Pagamento</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">Esc</kbd> Limpar busca</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">F12</kbd> Finalizar</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">/</kbd> Busca</span>
                </div>
            </footer>
        </form>
    </div>

    <script>
        const catalog = @json($catalog);

        function pdvScreen(catalogData) {
            return {
                search: '',
                highlightedIndex: 0,
                cart: [],
                catalog: [...catalogData.products, ...catalogData.services],
                currentTime: '',
                previewImageFailed: false,
                init() {
                    this.tickClock();
                    setInterval(() => this.tickClock(), 1000);
                    this.$watch(
                        () => {
                            const v = this.visualItem;

                            return v ? `${v.type}-${v.id}-${v.image_url || ''}` : '';
                        },
                        () => {
                            this.previewImageFailed = false;
                        },
                    );
                },
                tickClock() {
                    const d = new Date();
                    this.currentTime = d.toLocaleTimeString('pt-BR') + ' | ' + d.toLocaleDateString('pt-BR');
                },
                formatMoneyBRL(value) {
                    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                formatQty(value) {
                    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
                },
                get filteredCatalog() {
                    const term = this.search.trim().toLowerCase();
                    const rows = !term
                        ? this.catalog.slice(0, 12)
                        : this.catalog
                              .filter((item) => {
                                  const code = `${item.code || ''}`.toLowerCase();
                                  const sku = `${item.sku || ''}`.toLowerCase();
                                  const name = `${item.name}`.toLowerCase();
                                  return name.includes(term) || code.includes(term) || sku.includes(term);
                              })
                              .slice(0, 20);
                    if (this.highlightedIndex >= rows.length) {
                        this.highlightedIndex = 0;
                    }
                    return rows;
                },
                get previewItem() {
                    return this.filteredCatalog[this.highlightedIndex] || null;
                },
                get visualItem() {
                    if (this.cart.length > 0) {
                        return this.cart[this.cart.length - 1];
                    }

                    return this.previewItem;
                },
                get previewQty() {
                    return this.previewItem ? '1' : '0';
                },
                get previewUnit() {
                    return this.previewItem ? Number(this.previewItem.price || 0) : 0;
                },
                get previewLineTotal() {
                    return this.previewItem ? this.previewUnit : 0;
                },
                get currentKindLabel() {
                    if (this.cart.length > 0) {
                        const last = this.cart[this.cart.length - 1];
                        return last.type === 'product' ? 'Produto' : 'Serviço';
                    }
                    const p = this.previewItem;
                    if (p) {
                        return p.type === 'product' ? 'Produto' : 'Serviço';
                    }
                    return 'Pronto para venda';
                },
                get bannerTitle() {
                    if (this.cart.length > 0) {
                        const last = this.cart[this.cart.length - 1];
                        return String(last.name || '').toUpperCase();
                    }
                    const p = this.previewItem;
                    if (p) {
                        return String(p.name || '').toUpperCase();
                    }
                    return 'Selecione um item';
                },
                get totalVolumeQty() {
                    return this.cart.reduce((acc, item) => acc + Math.max(1, Number(item.quantity || 1)), 0);
                },
                highlightNext() {
                    if (!this.filteredCatalog.length) {
                        return;
                    }
                    this.highlightedIndex = (this.highlightedIndex + 1) % this.filteredCatalog.length;
                },
                highlightPrev() {
                    if (!this.filteredCatalog.length) {
                        return;
                    }
                    this.highlightedIndex = (this.highlightedIndex - 1 + this.filteredCatalog.length) % this.filteredCatalog.length;
                },
                selectHighlighted() {
                    const item = this.filteredCatalog[this.highlightedIndex];
                    if (item) {
                        this.addCatalogItem(item);
                    }
                },
                addCatalogItem(item) {
                    this.cart.push({
                        id: item.id,
                        type: item.type,
                        code: item.code || (item.type === 'product' ? 'P' + item.id : 'S' + item.id),
                        name: item.name,
                        price: Number(item.price || 0),
                        quantity: 1,
                        image_url: item.image_url ?? null,
                    });
                    this.search = '';
                    this.highlightedIndex = 0;
                },
                removeItem(index) {
                    this.cart.splice(index, 1);
                },
                removeLastItem() {
                    if (this.cart.length > 0) {
                        this.cart.pop();
                    }
                },
                clearSearch() {
                    this.search = '';
                    this.highlightedIndex = 0;
                },
                handlePdvHotkeys(e) {
                    if (e.key === 'F2') {
                        e.preventDefault();
                        this.$refs.clientSelect?.focus();
                    }
                    if (e.key === 'F3') {
                        e.preventDefault();
                        this.$refs.searchInput?.focus();
                    }
                    if (e.key === 'F8') {
                        e.preventDefault();
                        this.$refs.paymentSelect?.focus();
                    }
                    if (e.key === 'F12') {
                        e.preventDefault();
                        if (this.cart.length > 0 && this.$refs.submitBtn) {
                            this.$refs.submitBtn.click();
                        }
                    }
                    if (e.key === 'Delete') {
                        const tag = e.target?.tagName;
                        const type = e.target?.type;
                        if (tag === 'TEXTAREA' || tag === 'SELECT') {
                            return;
                        }
                        if (tag === 'INPUT' && type === 'number') {
                            return;
                        }
                        if (e.target === this.$refs.searchInput && String(this.search || '').length > 0) {
                            return;
                        }
                        if (tag === 'INPUT' && type === 'text' && e.target !== this.$refs.searchInput) {
                            return;
                        }
                        e.preventDefault();
                        this.removeLastItem();
                    }
                    if (e.key === 'Escape') {
                        if (document.activeElement !== this.$refs.searchInput) {
                            return;
                        }
                        e.preventDefault();
                        this.clearSearch();
                    }
                },
                get subtotalServices() {
                    return this.cart
                        .filter((item) => item.type === 'service')
                        .reduce((acc, item) => acc + item.price * Math.max(1, Number(item.quantity || 1)), 0);
                },
                get subtotalProducts() {
                    return this.cart
                        .filter((item) => item.type === 'product')
                        .reduce((acc, item) => acc + item.price * Math.max(1, Number(item.quantity || 1)), 0);
                },
                get total() {
                    return this.subtotalServices + this.subtotalProducts;
                },
                get servicePayload() {
                    const rows = [];
                    this.cart
                        .filter((item) => item.type === 'service')
                        .forEach((item) => {
                            const qty = Math.max(1, Number(item.quantity || 1));
                            for (let i = 0; i < qty; i++) {
                                rows.push({ service_id: item.id });
                            }
                        });
                    return rows;
                },
                get productPayload() {
                    return this.cart
                        .filter((item) => item.type === 'product')
                        .map((item) => ({ product_id: item.id, quantity: Math.max(1, Number(item.quantity || 1)) }));
                },
            };
        }
    </script>
</x-app-layout>
