<x-guest-layout :brand-company="$company">
    <div class="mb-4">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] brand-text">{{ $company?->name ?? 'StudioFlow' }}</p>
        <h1 class="mt-2 text-2xl font-semibold text-[var(--text-main)]">Sua avaliação</h1>
        <p class="mt-2 text-sm sf-text-muted">Conte como foi seu atendimento. Esta avaliação é privada e visível apenas para a gestão.</p>
    </div>

    <form method="POST" action="{{ route('public-reviews.store', ['token' => $review->token]) }}" class="space-y-4">
        @csrf
        <div>
            <x-input-label for="rating" value="Nota geral (obrigatória)" />
            @php
                $ratingLabels = [1 => 'Muito ruim', 2 => 'Ruim', 3 => 'Regular', 4 => 'Bom', 5 => 'Excelente'];
            @endphp
            <select id="rating" name="rating" class="sf-select brand-focus mt-1 block w-full" required>
                @for ($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected(old('rating') == $i)>{{ $i }} — {{ $ratingLabels[$i] }}</option>
                @endfor
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('rating')" />
        </div>
        <div>
            <x-input-label for="service_quality_rating" value="Qualidade do serviço (opcional)" />
            <select id="service_quality_rating" name="service_quality_rating" class="sf-select mt-1 block w-full">
                <option value="">—</option>
                @for ($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected(old('service_quality_rating') == $i)>{{ $i }}</option>
                @endfor
            </select>
        </div>
        <div>
            <x-input-label for="punctuality_rating" value="Pontualidade (opcional)" />
            <select id="punctuality_rating" name="punctuality_rating" class="sf-select brand-focus mt-1 block w-full">
                <option value="">—</option>
                @for ($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected(old('punctuality_rating') == $i)>{{ $i }}</option>
                @endfor
            </select>
        </div>
        <div>
            <x-input-label for="environment_rating" value="Ambiente (opcional)" />
            <select id="environment_rating" name="environment_rating" class="sf-select mt-1 block w-full">
                <option value="">—</option>
                @for ($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected(old('environment_rating') == $i)>{{ $i }}</option>
                @endfor
            </select>
        </div>
        <div>
            <x-input-label for="comment" value="Comentário (opcional)" />
            <textarea id="comment" name="comment" rows="4" class="sf-input brand-focus mt-1 block w-full">{{ old('comment') }}</textarea>
        </div>
        <button type="submit" class="brand-cta w-full justify-center px-5 py-3 text-sm font-semibold">
            Enviar avaliação
        </button>
    </form>
</x-guest-layout>
