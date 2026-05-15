<x-app-layout>
    <x-slot name="header">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Assinaturas</p>
        <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Editar plano</h2>
    </x-slot>

    <div class="space-y-4">
        <div class="sf-card p-6">
            <form method="POST" action="{{ route('membership-plans.update', $plan) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('membership-plans._form', ['plan' => $plan, 'services' => $services, 'billingCycles' => $billingCycles])
                <div class="flex justify-end gap-3">
                    <a href="{{ route('membership-plans.index') }}" class="sf-button-secondary">Voltar</a>
                    <x-primary-button>Salvar alterações</x-primary-button>
                </div>
            </form>
        </div>

        <div class="sf-card p-4">
            <form method="POST" action="{{ route('membership-plans.toggle-active', $plan) }}" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="sf-button-secondary text-xs">{{ $plan->active ? 'Desativar plano' : 'Ativar plano' }}</button>
            </form>
        </div>
    </div>
</x-app-layout>
