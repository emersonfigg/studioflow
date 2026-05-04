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
                <x-text-input id="stock_quantity" name="stock_quantity" type="number" min="0" step="1" class="mt-2 block w-full" :value="old('stock_quantity', $productData?->stock_quantity ?? 0)" required />
                <p class="mt-2 text-xs text-[#c7d2e3]">Informe quantas unidades estão disponíveis para venda.</p>
                <x-input-error :messages="$errors->get('stock_quantity')" class="mt-2" />
            </div>

            <div class="md:col-span-2">
                <x-input-label for="description" value="Descrição" />
                <textarea id="description" name="description" rows="4" class="sf-input mt-2 block w-full">{{ old('description', $productData?->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="md:col-span-2">
                <x-input-label for="image" value="Imagem do produto" />
                <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="sf-input mt-2 block w-full file:mr-4 file:rounded-xl file:border-0 file:bg-[#d4af37] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#132746] hover:file:bg-[#e3bf4a]">
                <p class="mt-2 text-xs text-[#c7d2e3]">Selecione uma imagem do seu computador. Ela será enviada e salva automaticamente no servidor.</p>
                <x-input-error :messages="$errors->get('image')" class="mt-2" />
            </div>

            @if ($productData?->image_url)
                <label class="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-[#132746] px-4 py-3 text-sm text-white">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-white/10 bg-[#0f203b] text-[#d4af37] focus:ring-[#d4af37]">
                    Remover imagem atual
                </label>
            @endif

            <label class="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-[#132746] px-4 py-3 text-sm text-white">
                <input type="checkbox" name="active" value="1" class="rounded border-white/10 bg-[#0f203b] text-[#d4af37] focus:ring-[#d4af37]" @checked(old('active', $productData?->active ?? true))>
                Produto ativo para venda
            </label>
        </div>
    </section>

    <aside class="sf-card p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Pré-visualização</p>
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
                'absolute inset-0 flex items-center justify-center rounded-2xl border border-dashed border-white/10 bg-[#132746] text-[#d4af37]',
                'hidden' => (bool) ($productData?->image_url),
            ])>
                <svg class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M7.5 6A2.5 2.5 0 005 8.5v7A2.5 2.5 0 007.5 18h9a2.5 2.5 0 002.5-2.5v-7A2.5 2.5 0 0016.5 6h-9zm0 1.5h9A1 1 0 0117.5 8.5v4.085l-2.23-2.23a1.75 1.75 0 00-2.475 0l-2.92 2.92-1.17-1.17a1.75 1.75 0 00-2.475 0L6.5 13.835V8.5a1 1 0 011-1zm8.75 2.25a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zM7.29 13.166a.25.25 0 01.354 0l1.7 1.7a.75.75 0 001.06 0l3.451-3.45a.25.25 0 01.354 0l2.291 2.29v1.794a1 1 0 01-1 1h-9a1 1 0 01-1-1v-.544l1.79-1.79z" />
                </svg>
            </div>
        </div>
        <h3 class="mt-3 text-2xl font-semibold text-white">{{ old('name', $productData?->name ?? 'Novo produto') }}</h3>
        <p class="mt-4 text-3xl font-semibold text-white">
            {{ \App\Support\BrazilianCurrency::format(\App\Support\BrazilianCurrency::normalize(old('price', $productData?->price ?? 0))) }}
        </p>
        <p class="mt-2 text-sm font-semibold text-[#d4af37]">
            Estoque: {{ old('stock_quantity', $productData?->stock_quantity ?? 0) }} un.
        </p>
        <p class="mt-4 text-sm leading-7 text-[#c7d2e3]">
            {{ old('description', $productData?->description ?? 'Esse produto aparecerá no controle comercial e nas vendas vinculadas aos clientes.') }}
        </p>
    </aside>
</div>
