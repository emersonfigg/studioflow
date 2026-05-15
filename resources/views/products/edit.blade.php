<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Produtos</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Editar produto</h2>
                <p class="mt-2 text-sm sf-text-muted">Ajuste preço, descrição e status comercial do item.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('products.index') }}" class="sf-button-ghost">Voltar</a>
                <button form="product-form" class="sf-button-primary">Salvar produto</button>
            </div>
        </div>
    </x-slot>

    <form id="product-form" method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')
        @include('products._form', ['product' => $product])
    </form>
</x-app-layout>
