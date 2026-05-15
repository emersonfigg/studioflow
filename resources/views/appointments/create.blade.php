<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Agendamentos</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                Novo agendamento
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl">
        <div class="sf-card p-6 sm:p-7">
            @include('appointments._form', [
                'appointment' => null,
                'action' => route('appointments.store'),
                'method' => 'POST',
            ])
        </div>
    </div>
</x-app-layout>
