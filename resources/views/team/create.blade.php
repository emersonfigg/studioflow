<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Equipe</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                    Novo profissional
                </h2>
            </div>

            <a href="{{ route('team.index') }}" class="sf-button-ghost">
                Voltar para equipe
            </a>
        </div>
    </x-slot>

    <section class="sf-card p-5 sm:p-6">
        <form method="POST" action="{{ route('team.store') }}" enctype="multipart/form-data">
            @csrf

            @include('team._form')

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <a href="{{ route('team.index') }}" class="sf-button-ghost">Cancelar</a>
                <button type="submit" class="sf-button-primary">Salvar profissional</button>
            </div>
        </form>
    </section>
</x-app-layout>
