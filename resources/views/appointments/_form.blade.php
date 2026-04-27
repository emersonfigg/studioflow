<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <x-input-label for="client_id" :value="__('Client')" />
        <select id="client_id" name="client_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
            <option value="">{{ __('Select a client') }}</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((int) old('client_id', $appointment?->client_id) === $client->id)>
                    {{ $client->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('client_id')" />
    </div>

    <div>
        <x-input-label for="service_id" :value="__('Service')" />
        <select id="service_id" name="service_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
            <option value="">{{ __('Select a service') }}</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected((int) old('service_id', $appointment?->service_id) === $service->id)>
                    {{ $service->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
    </div>

    <div>
        <x-input-label for="user_id" :value="__('Staff')" />
        <select id="user_id" name="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
            <option value="">{{ __('Select a staff member') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected((int) old('user_id', $appointment?->user_id ?? auth()->id()) === $user->id)>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('user_id')" />
    </div>

    <div>
        <x-input-label for="start_time" :value="__('Start')" />
        <x-text-input id="start_time" name="start_time" type="datetime-local" class="mt-1 block w-full" :value="old('start_time', optional($appointment?->start_time)->format('Y-m-d\TH:i'))" required />
        <p class="mt-1 text-sm text-gray-500">{{ __('End time is calculated automatically from the selected service duration.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="status" :value="__('Status')" />
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $appointment?->status ?? 'scheduled') === $status)>
                        {{ match ($status) {
                            'scheduled' => __('Scheduled'),
                            'confirmed' => __('Confirmed'),
                            'in_progress' => __('In Progress'),
                            'completed' => __('Completed'),
                            'cancelled' => __('Cancelled'),
                            default => $status,
                        } }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('status')" />
        </div>

        <div>
            <x-input-label for="source" :value="__('Source')" />
            <select id="source" name="source" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                @foreach ($sources as $source)
                    <option value="{{ $source }}" @selected(old('source', $appointment?->source ?? 'internal') === $source)>
                        {{ __(str_replace('_', ' ', ucfirst($source))) }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('source')" />
        </div>
    </div>

    <div>
        <x-input-label for="notes" :value="__('Notes')" />
        <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $appointment?->notes) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ route('appointments.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
        <x-primary-button>{{ __('Save') }}</x-primary-button>
    </div>
</form>
