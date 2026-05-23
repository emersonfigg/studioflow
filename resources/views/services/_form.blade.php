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
        <x-input-label for="description" value="Descrição" />
        <textarea id="description" name="description" rows="3" placeholder="Opcional — visível no cadastro e na área pública de agendamento" class="sf-input mt-1 block w-full">{{ old('description', $service?->description) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('description')" />
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

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="price_mode" value="Tipo de preço" />
            <select id="price_mode" name="price_mode" class="sf-select mt-1 block w-full">
                <option value="fixed" @selected(old('price_mode', $service?->price_mode ?? 'fixed') === 'fixed')>Preço fixo</option>
                <option value="from" @selected(old('price_mode', $service?->price_mode ?? 'fixed') === 'from')>A partir de</option>
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('price_mode')" />
        </div>
        <label class="mt-7 flex items-center gap-2 text-sm text-gray-600">
            <input type="hidden" name="allow_pdv_price_edit" value="0">
            <input type="checkbox" name="allow_pdv_price_edit" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('allow_pdv_price_edit', $service?->allow_pdv_price_edit ?? false))>
            Permitir ajuste de preço no PDV
        </label>
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
