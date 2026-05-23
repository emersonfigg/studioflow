<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Gerar conferencia diaria</h1>
                <p class="mt-1 text-sm sf-text-muted">Crie a rotina de contagem usando ontem como referencia operacional.</p>
            </div>
            <a href="{{ route('stock.daily-checks.index') }}" class="sf-button-secondary">Voltar</a>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('stock.daily-checks.generate') }}" class="sf-card max-w-2xl space-y-5 p-6">
        @csrf
        <div>
            <x-input-label for="reference_date" value="Dia de referencia" />
            <x-text-input id="reference_date" name="reference_date" type="date" class="mt-2 block w-full" :value="old('reference_date', $defaultReferenceDate)" />
            <p class="mt-2 text-sm sf-text-muted">Por padrao usamos ontem para conferir a posicao fisica desta manha.</p>
            <x-input-error :messages="$errors->get('reference_date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="notes" value="Observacao" />
            <textarea id="notes" name="notes" rows="4" class="mt-2 block w-full rounded-xl border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--input-bg)] sf-text">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex justify-end">
            <button class="sf-button-primary" type="submit">Gerar diaria</button>
        </div>
    </form>
</x-app-layout>
