<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $client?->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="phone" :value="__('Phone')" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $client?->phone)" required />
        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
    </div>

    <div>
        <x-input-label for="birthday" :value="__('Birthday')" />
        <x-text-input id="birthday" name="birthday" type="date" class="mt-1 block w-full" :value="old('birthday', optional($client?->birthday)->format('Y-m-d'))" />
        <x-input-error class="mt-2" :messages="$errors->get('birthday')" />
    </div>

    <div>
        <x-input-label for="last_visit_at" :value="__('Last Visit')" />
        <x-text-input id="last_visit_at" name="last_visit_at" type="datetime-local" class="mt-1 block w-full" :value="old('last_visit_at', optional($client?->last_visit_at)->format('Y-m-d\TH:i'))" />
        <x-input-error class="mt-2" :messages="$errors->get('last_visit_at')" />
    </div>

    <div>
        <x-input-label for="notes" :value="__('Notes')" />
        <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $client?->notes) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
        <x-primary-button>{{ __('Save') }}</x-primary-button>
    </div>
</form>
