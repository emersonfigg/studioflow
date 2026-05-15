<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Assinaturas</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">{{ $plan->name }}</h2>
                <p class="mt-2 flex flex-wrap items-center gap-2 text-sm sf-text-muted">
                    <span class="inline-flex rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-xs font-semibold text-[var(--text-main)]">{{ $plan->billing_cycle_label }}</span>
                    <span>R$ {{ number_format((float) $plan->price, 2, ',', '.') }} (referência)</span>
                </p>
                <p class="mt-1 text-sm sf-text-muted">Assinantes do plano</p>
            </div>
            <a href="{{ route('membership-plans.edit', $plan) }}" class="sf-button-secondary">Editar plano</a>
        </div>
    </x-slot>

    <div class="sf-card overflow-hidden">
        <table class="min-w-full divide-y divide-white/10 text-sm">
            <thead class="bg-[var(--input-bg)]">
                <tr>
                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase sf-text-muted">Cliente</th>
                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase sf-text-muted">Status</th>
                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase sf-text-muted">Período vigente</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                @foreach ($memberships as $m)
                    <tr>
                        <td class="px-5 py-3">
                            <a href="{{ route('clients.show', $m->client_id) }}" class="brand-text hover:underline">{{ $m->client?->name }}</a>
                        </td>
                        <td class="px-5 py-3 sf-text-muted">{{ $m->status_label }}</td>
                        <td class="px-5 py-3 sf-text-muted">{{ $m->current_cycle_starts_at?->format('d/m/Y') }} — {{ $m->current_cycle_ends_at?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-5 py-4">{{ $memberships->links() }}</div>
    </div>
</x-app-layout>
