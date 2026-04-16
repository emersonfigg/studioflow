<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Client') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                @include('clients._form', [
                    'client' => $client,
                    'action' => route('clients.update', $client),
                    'method' => 'PATCH',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
