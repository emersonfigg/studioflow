<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $service->name }}
            </h2>

            @if (auth()->user()->isAdmin())
                <a href="{{ route('services.edit', $service) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
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
                        <dt class="text-sm font-medium text-gray-500">{{ __('Duration') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $service->duration_minutes }} min</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Price') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">R$ {{ number_format((float) $service->price, 2, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Status') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $service->active ? __('Active') : __('Inactive') }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex items-center justify-between">
                    <a href="{{ route('services.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to services') }}</a>

                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('services.destroy', $service) }}">
                            @csrf
                            @method('DELETE')
                            <x-danger-button onclick="return confirm('{{ __('Delete this service?') }}')">
                                {{ __('Delete') }}
                            </x-danger-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
