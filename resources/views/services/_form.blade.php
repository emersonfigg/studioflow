<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $service?->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="duration_minutes" :value="__('Duration Minutes')" />
        <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="1" class="mt-1 block w-full" :value="old('duration_minutes', $service?->duration_minutes)" required />
        <x-input-error class="mt-2" :messages="$errors->get('duration_minutes')" />
    </div>

    <div>
        <x-input-label for="price" :value="__('Price')" />
        <x-text-input id="price" name="price" type="text" inputmode="decimal" placeholder="R$ 0,00" class="mt-1 block w-full" :value="old('price', \App\Support\BrazilianCurrency::input($service?->price))" required />
        <x-input-error class="mt-2" :messages="$errors->get('price')" />
    </div>

    <div>
        <label for="active" class="inline-flex items-center">
            <input id="active" type="checkbox" name="active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('active', $service?->active ?? true))>
            <span class="ms-2 text-sm text-gray-600">{{ __('Active') }}</span>
        </label>
        <x-input-error class="mt-2" :messages="$errors->get('active')" />
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ route('services.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
        <x-primary-button>{{ __('Save') }}</x-primary-button>
    </div>
</form>
