@php($productData = $product ?? null)

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
    <section class="sf-card p-6">
        <div class="grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <x-input-label for="name" value="Nome do produto" />
                <x-text-input id="name" name="name" type="text" class="mt-2 block w-full" :value="old('name', $productData?->name ?? '')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="sku" value="SKU" />
                <x-text-input id="sku" name="sku" type="text" class="mt-2 block w-full" :value="old('sku', $productData?->sku ?? '')" />
                <x-input-error :messages="$errors->get('sku')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="price" value="Preço" />
                <x-text-input id="price" name="price" type="text" inputmode="decimal" placeholder="R$ 0,00" class="mt-2 block w-full" :value="old('price', \App\Support\BrazilianCurrency::input($productData?->price))" required />
                <x-input-error :messages="$errors->get('price')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="stock_quantity" value="Quantidade em estoque" />
                <x-text-input id="stock_quantity" name="stock_quantity" type="number" min="0" step="0.01" class="mt-2 block w-full" :value="old('stock_quantity', $productData?->stock_quantity ?? 0)" required />
                <p class="mt-2 text-xs sf-text-muted">Quantidade disponível para venda ou consumo interno.</p>
                <x-input-error :messages="$errors->get('stock_quantity')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="minimum_stock" value="Estoque mínimo (alerta)" />
                <x-text-input id="minimum_stock" name="minimum_stock" type="number" min="0" step="0.01" class="mt-2 block w-full" :value="old('minimum_stock', $productData?->minimum_stock ?? '')" />
                <x-input-error :messages="$errors->get('minimum_stock')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="cost_price" value="Custo unitário" />
                <x-text-input id="cost_price" name="cost_price" type="text" inputmode="decimal" placeholder="R$ 0,00" class="mt-2 block w-full" :value="old('cost_price', $productData?->cost_price !== null ? \App\Support\BrazilianCurrency::input((float) $productData->cost_price) : '')" />
                <x-input-error :messages="$errors->get('cost_price')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="unit" value="Unidade de medida" />
                <x-text-input id="unit" name="unit" type="text" maxlength="32" class="mt-2 block w-full" placeholder="Ex: un, ml, g" :value="old('unit', $productData?->unit ?? '')" />
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>

            <div class="md:col-span-2 flex flex-wrap gap-6 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                <input type="hidden" name="track_stock" value="0">
                <label class="inline-flex items-center gap-3 text-sm text-[var(--text-main)]">
                    <input type="checkbox" name="track_stock" value="1" class="rounded border-white/10 bg-[var(--app-shell-bg)] brand-text focus:ring-[var(--brand-primary)]" @checked(old('track_stock', $productData?->track_stock ?? true))>
                    Controlar estoque deste produto
                </label>
                <input type="hidden" name="low_stock_alert" value="0">
                <label class="inline-flex items-center gap-3 text-sm text-[var(--text-main)]">
                    <input type="checkbox" name="low_stock_alert" value="1" class="rounded border-white/10 bg-[var(--app-shell-bg)] brand-text focus:ring-[var(--brand-primary)]" @checked(old('low_stock_alert', $productData?->low_stock_alert ?? true))>
                    Alertar quando estiver no mínimo
                </label>
            </div>

            @php($currentCommissionType = old('commission_type', $productData?->commission_type ?? ''))
            @php($currentCommissionValue = old('commission_value', $productData?->commission_value !== null ? \App\Support\BrazilianCurrency::input((float) $productData->commission_value) : ''))

            <div
                class="md:col-span-2"
                x-data="{
                    type: @js((string) $currentCommissionType),
                    value: @js((string) $currentCommissionValue),
                    get isPercentage() { return this.type === 'percentage' },
                    get isFixed() { return this.type === 'fixed' },
                    get isNone() { return this.type === '' || this.type === 'none' || this.type === null },
                    get hint() {
                        if (this.isPercentage) {
                            return 'Percentual sobre o subtotal de cada item vendido.';
                        }
                        if (this.isFixed) {
                            return 'Valor fixo em reais por unidade vendida.';
                        }
                        return 'Selecione \"Sem comissão\" para não pagar comissão neste produto.';
                    },
                }"
                x-init="$watch('type', () => { if (isNone) { value = ''; } })"
            >
                <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Comissão por venda</p>
                            <p class="mt-1 text-sm sf-text-muted">Defina se o profissional ganha comissão ao vender este produto.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-[200px_minmax(0,1fr)]">
                        <div>
                            <x-input-label for="commission_type" value="Tipo de comissão" />
                            <select id="commission_type" name="commission_type" class="sf-select mt-2 block w-full" x-model="type">
                                <option value="">Sem comissão</option>
                                <option value="fixed">Valor fixo (R$)</option>
                                <option value="percentage">Percentual (%)</option>
                            </select>
                            <x-input-error :messages="$errors->get('commission_type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="commission_value" value="Valor da comissão" />
                            <div class="relative mt-2">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-semibold sf-text-muted"
                                    x-text="isPercentage ? '%' : (isFixed ? 'R$' : '')"
                                ></span>
                                <input
                                    id="commission_value"
                                    name="commission_value"
                                    type="text"
                                    inputmode="decimal"
                                    placeholder="0,00"
                                    class="sf-input block w-full pl-10"
                                    :disabled="isNone"
                                    :class="isNone ? 'opacity-50 cursor-not-allowed' : ''"
                                    x-model="value"
                                >
                            </div>
                            <p class="mt-2 text-xs sf-text-muted" x-text="hint"></p>
                            <x-input-error :messages="$errors->get('commission_value')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Recompra inteligente</p>
                            <p class="mt-1 text-sm sf-text-muted">Use o prazo para o sistema sugerir este produto novamente no próximo atendimento.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-[200px_minmax(0,1fr)]">
                        <div>
                            <x-input-label for="recommended_repurchase_days" value="Prazo sugerido para recompra (dias)" />
                            <x-text-input
                                id="recommended_repurchase_days"
                                name="recommended_repurchase_days"
                                type="number"
                                min="1"
                                max="730"
                                step="1"
                                placeholder="Ex: 120"
                                class="mt-2 block w-full"
                                :value="old('recommended_repurchase_days', $productData?->recommended_repurchase_days ?? '')"
                            />
                            <x-input-error :messages="$errors->get('recommended_repurchase_days')" class="mt-2" />
                        </div>
                        <div class="flex items-center">
                            <p class="text-xs sf-text-muted">Após esse prazo, o sistema poderá sugerir este produto novamente ao cliente. Deixe em branco para nao gerar previsão de recompra.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <x-input-label for="description" value="Descrição" />
                <textarea id="description" name="description" rows="4" class="sf-input mt-2 block w-full">{{ old('description', $productData?->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="md:col-span-2">
                <x-input-label for="image" value="Imagem do produto" />
                <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="sf-input mt-2 block w-full file:mr-4 file:rounded-xl file:border-0 file:bg-[var(--brand-primary)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--brand-on-primary)] hover:file:bg-[color-mix(in_srgb,var(--btn-primary-bg)_90%,white)]">
                <p class="mt-2 text-xs sf-text-muted">Selecione uma imagem do seu computador. Ela será enviada e salva automaticamente no servidor.</p>
                <x-input-error :messages="$errors->get('image')" class="mt-2" />
            </div>

            @if ($productData?->image_url)
                <label class="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-3 text-sm text-[var(--text-main)]">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-white/10 bg-[var(--app-shell-bg)] brand-text focus:ring-[var(--brand-primary)]">
                    Remover imagem atual
                </label>
            @endif

            <label class="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-3 text-sm text-[var(--text-main)]">
                <input type="checkbox" name="active" value="1" class="rounded border-white/10 bg-[var(--app-shell-bg)] brand-text focus:ring-[var(--brand-primary)]" @checked(old('active', $productData?->active ?? true))>
                Produto ativo para venda
            </label>
        </div>
    </section>

    <aside class="sf-card p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Pré-visualização</p>
        <div class="relative mt-4 h-40 w-full overflow-hidden rounded-2xl ring-1 ring-white/10">
            @if ($productData?->image_url)
                <img
                    src="{{ $productData->image_url }}"
                    alt="Imagem de {{ $productData->name }}"
                    class="absolute inset-0 h-full w-full object-cover"
                    loading="lazy"
                    decoding="async"
                    onerror="this.classList.add('hidden'); this.nextElementSibling?.classList.remove('hidden')"
                >
            @endif
            <div @class([
                'absolute inset-0 flex items-center justify-center rounded-2xl border border-dashed border-white/10 bg-[var(--input-bg)] brand-text',
                'hidden' => (bool) ($productData?->image_url),
            ])>
                <svg class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M7.5 6A2.5 2.5 0 005 8.5v7A2.5 2.5 0 007.5 18h9a2.5 2.5 0 002.5-2.5v-7A2.5 2.5 0 0016.5 6h-9zm0 1.5h9A1 1 0 0117.5 8.5v4.085l-2.23-2.23a1.75 1.75 0 00-2.475 0l-2.92 2.92-1.17-1.17a1.75 1.75 0 00-2.475 0L6.5 13.835V8.5a1 1 0 011-1zm8.75 2.25a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zM7.29 13.166a.25.25 0 01.354 0l1.7 1.7a.75.75 0 001.06 0l3.451-3.45a.25.25 0 01.354 0l2.291 2.29v1.794a1 1 0 01-1 1h-9a1 1 0 01-1-1v-.544l1.79-1.79z" />
                </svg>
            </div>
        </div>
        <h3 class="mt-3 text-2xl font-semibold text-[var(--text-main)]">{{ old('name', $productData?->name ?? 'Novo produto') }}</h3>
        <p class="mt-4 text-3xl font-semibold text-[var(--text-main)]">
            {{ \App\Support\BrazilianCurrency::format(\App\Support\BrazilianCurrency::normalize(old('price', $productData?->price ?? 0))) }}
        </p>
        <p class="mt-2 text-sm font-semibold brand-text">
            Estoque: {{ old('stock_quantity', $productData?->stock_quantity ?? 0) }} un.
        </p>
        <p class="mt-4 text-sm leading-7 sf-text-muted">
            {{ old('description', $productData?->description ?? 'Esse produto aparecerá no controle comercial e nas vendas vinculadas aos clientes.') }}
        </p>
    </aside>
</div>
