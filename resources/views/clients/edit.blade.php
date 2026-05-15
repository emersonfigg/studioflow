<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Clientes</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Editar cliente</h2>
                <p class="mt-2 text-sm sf-text-muted">Atualize dados de contato, observações e histórico basico.</p>
            </div>
            <a href="{{ route('clients.show', $client) }}" class="sf-button-secondary">Voltar</a>
        </div>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <section class="sf-card p-5 sm:p-7">
            @include('clients._form', [
                'client' => $client,
                'action' => route('clients.update', $client),
                'method' => 'PATCH',
                'submitLabel' => 'Salvar cliente',
            ])
        </section>

        <aside class="space-y-6">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Cliente atual</p>
                <p class="mt-3 text-base font-semibold text-[var(--text-main)]">{{ $client->name }}</p>
                <p class="mt-1 text-sm sf-text-muted">{{ $client->phone }}</p>
            </article>
        </aside>
    </div>
</x-app-layout>
