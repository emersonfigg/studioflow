<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $client->name }}
            </h2>

            @if (auth()->user()->isAdmin())
                <a href="{{ route('clients.edit', $client) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
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
                        <dt class="text-sm font-medium text-gray-500">{{ __('Phone') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $client->phone }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Birthday') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $client->birthday?->format('d/m/Y') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Last Visit') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $client->last_visit_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">{{ __('Notes') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $client->notes ?: '-' }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex items-center justify-between">
                    <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to clients') }}</a>

                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('clients.destroy', $client) }}">
                            @csrf
                            @method('DELETE')
                            <x-danger-button onclick="return confirm('{{ __('Delete this client?') }}')">
                                {{ __('Delete') }}
                            </x-danger-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
