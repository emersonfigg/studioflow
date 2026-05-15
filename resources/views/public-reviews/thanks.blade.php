<x-guest-layout :brand-company="$company">
    <div class="text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--brand-primary)]">{{ $company?->name ?? 'StudioFlow' }}</p>
        <h1 class="mt-4 text-2xl font-semibold text-[var(--text-main)]">Obrigado!</h1>
        <p class="mt-3 text-sm sf-text-muted">
            @if (! empty($already))
                Sua avaliação já havia sido registrada.
            @else
                Sua avaliação foi recebida com sucesso.
            @endif
        </p>
    </div>
</x-guest-layout>
