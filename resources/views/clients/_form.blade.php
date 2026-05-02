<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <div>
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" name="name" type="text" class="mt-2 block w-full" :value="old('name', $client?->name)" required autofocus />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="phone" value="Telefone" />
            <x-text-input id="phone" name="phone" type="text" class="mt-2 block w-full" :value="old('phone', $client?->phone)" required />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div>
            <x-input-label for="birthday" value="Aniversário" />
            <x-text-input id="birthday" name="birthday" type="date" class="mt-2 block w-full" :value="old('birthday', optional($client?->birthday)->format('Y-m-d'))" />
            <x-input-error class="mt-2" :messages="$errors->get('birthday')" />
        </div>

        <div>
            <x-input-label for="last_visit_at" value="Última visita" />
            <x-text-input id="last_visit_at" name="last_visit_at" type="datetime-local" class="mt-2 block w-full" :value="old('last_visit_at', optional($client?->last_visit_at)->format('Y-m-d\TH:i'))" />
            <x-input-error class="mt-2" :messages="$errors->get('last_visit_at')" />
        </div>
    </div>

    <div>
        <x-input-label for="notes" value="Observações internas" />
        <textarea id="notes" name="notes" rows="5" class="sf-input mt-2 block w-full">{{ old('notes', $client?->notes) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" class="sf-button-ghost">Cancelar</a>
        <x-primary-button>{{ $submitLabel ?? 'Salvar cliente' }}</x-primary-button>
    </div>
</form>
