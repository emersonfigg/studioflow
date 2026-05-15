<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">{{ __('Appointments') }}</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                {{ __('Edit Appointment') }}
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="sf-card p-6 sm:p-7">
            @include('appointments._form', [
                'appointment' => $appointment,
                'action' => route('appointments.update', $appointment),
                'method' => 'PATCH',
            ])
        </div>
    </div>
</x-app-layout>
