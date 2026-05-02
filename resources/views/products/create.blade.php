<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Produtos</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Novo produto</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Cadastre itens que também entram no financeiro e no histórico do cliente.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('products.index') }}" class="sf-button-ghost">Voltar</a>
                <button form="product-form" class="sf-button-primary">Salvar produto</button>
            </div>
        </div>
    </x-slot>

    <form id="product-form" method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
        @csrf
        @include('products._form', ['product' => null])
    </form>
</x-app-layout>
