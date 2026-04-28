<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#C8A96B]">{{ __('Appointments') }}</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                {{ __('New Appointment') }}
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="sf-card p-6 sm:p-7">
            @include('appointments._form', [
                'appointment' => null,
                'action' => route('appointments.store'),
                'method' => 'POST',
            ])
        </div>
    </div>
</x-app-layout>
