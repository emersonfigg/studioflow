<x-app-layout>
    <x-slot name="header">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Assinaturas</p>
        <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Novo plano</h2>
    </x-slot>

    <div class="sf-card p-6">
        <form method="POST" action="{{ route('membership-plans.store') }}" class="space-y-6">
            @csrf
            @include('membership-plans._form', ['plan' => null, 'services' => $services, 'billingCycles' => $billingCycles])
            <div class="flex justify-end gap-3">
                <a href="{{ route('membership-plans.index') }}" class="sf-button-secondary">Cancelar</a>
                <x-primary-button>Salvar</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
