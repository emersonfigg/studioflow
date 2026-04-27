<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Appointment') }}
            </h2>

            @if (auth()->user()->isAdmin())
                <a href="{{ route('appointments.edit', $appointment) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Edit') }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Client') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $appointment->client->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Service') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $appointment->service->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Staff') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $appointment->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Status') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $appointment->statusLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Source') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ __(str_replace('_', ' ', ucfirst($appointment->source))) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Start') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $appointment->start_time->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('End') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $appointment->end_time->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">{{ __('Notes') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $appointment->notes ?: '-' }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex items-center justify-between">
                    <a href="{{ route('appointments.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to appointments') }}</a>

                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('appointments.destroy', $appointment) }}">
                            @csrf
                            @method('DELETE')
                            <x-danger-button onclick="return confirm('{{ __('Delete this appointment?') }}')">
                                {{ __('Delete') }}
                            </x-danger-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
