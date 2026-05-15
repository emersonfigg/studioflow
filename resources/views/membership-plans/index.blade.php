<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Assinaturas</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Planos</h2>
            </div>
            <a href="{{ route('membership-plans.create') }}" class="sf-button-primary">Novo plano</a>
        </div>
    </x-slot>

    <div class="sf-card overflow-hidden">
        <table class="min-w-full divide-y divide-white/10 text-sm">
            <thead class="bg-[var(--input-bg)]">
                <tr>
                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase sf-text-muted">Nome</th>
                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase sf-text-muted">Ciclo</th>
                    <th class="px-5 py-4 text-right text-xs font-semibold uppercase sf-text-muted">Preço</th>
                    <th class="px-5 py-4 text-center text-xs font-semibold uppercase sf-text-muted">Ativo</th>
                    <th class="px-5 py-4 text-right text-xs font-semibold uppercase sf-text-muted">Assinantes</th>
                    <th class="px-5 py-4 text-right text-xs font-semibold uppercase sf-text-muted"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                @forelse ($plans as $plan)
                    <tr class="hover:bg-white/5">
                        <td class="px-5 py-3 font-medium text-[var(--text-main)]">{{ $plan->name }}</td>
                        <td class="px-5 py-3 sf-text-muted">
                            <span class="inline-flex rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-xs font-medium sf-text-muted">{{ $plan->billing_cycle_label }}</span>
                        </td>
                        <td class="px-5 py-3 text-right text-[var(--text-main)]">R$ {{ number_format((float) $plan->price, 2, ',', '.') }}</td>
                        <td class="px-5 py-3 text-center">
                            @if ($plan->active)
                                <span class="text-emerald-300">Sim</span>
                            @else
                                <span class="text-rose-300">Não</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right sf-text-muted">{{ $plan->customer_memberships_count }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('membership-plans.show', $plan) }}" class="text-xs brand-text hover:underline">Assinantes</a>
                            <span class="text-white/30">·</span>
                            <a href="{{ route('membership-plans.edit', $plan) }}" class="text-xs brand-text hover:underline">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center sf-text-muted">Nenhum plano cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
