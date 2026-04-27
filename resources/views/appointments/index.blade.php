<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ __('Appointments') }}</p>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Daily Schedule') }}
                </h2>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('appointments.index') }}" class="flex flex-wrap items-center gap-3">
                    <input
                        type="date"
                        name="date"
                        value="{{ $selectedDate->format('Y-m-d') }}"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    <select
                        name="user_id"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">{{ __('All Professionals') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($selectedUserId === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>

                    <button class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('Filter') }}
                    </button>
                </form>

                @if (auth()->user()->company_id)
                    <a href="{{ route('appointments.create') }}" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                        {{ __('New Appointment') }}
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ __('Appointment action completed successfully.') }}
                </div>
            @endif

            <div class="grid gap-6 xl:grid-cols-[1.15fr,0.85fr]">
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">{{ __('Timeline View') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Schedule for') }} {{ $selectedDate->format('d/m/Y') }}
                        </p>
                    </div>

                    <div class="divide-y divide-gray-200">
                        @foreach ($timelineSlots as $slot)
                            <div class="grid gap-4 px-4 py-4 sm:grid-cols-[88px,1fr] sm:px-6">
                                <div class="pt-1 text-sm font-medium text-gray-500">
                                    {{ $slot['time'] }}
                                </div>

                                <div class="space-y-3">
                                    @forelse ($slot['appointments'] as $appointment)
                                        <article class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="text-sm font-semibold text-gray-900">{{ $appointment->client->name }}</p>
                                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                                            {{ $appointment->statusLabel() }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-sm text-gray-600">
                                                        {{ $appointment->service->name }} • {{ $appointment->user->name }}
                                                    </p>
                                                    <p class="mt-1 text-sm text-gray-500">
                                                        {{ $appointment->start_time->format('H:i') }} - {{ $appointment->end_time->format('H:i') }}
                                                    </p>
                                                </div>

                                                <div class="flex flex-wrap gap-2">
                                                    @if (auth()->user()->isAdmin())
                                                        <a href="{{ route('appointments.edit', $appointment) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-white">
                                                            {{ __('Quick Edit') }}
                                                        </a>
                                                    @endif

                                                    @if ($appointment->status === 'scheduled')
                                                        <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                                                {{ __('Quick Confirm') }}
                                                            </button>
                                                        </form>
                                                    @endif

                                                    @if (in_array($appointment->status, ['scheduled', 'confirmed'], true))
                                                        <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="in_progress">
                                                            <button class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 hover:bg-amber-100">
                                                                {{ __('Quick Start') }}
                                                            </button>
                                                        </form>
                                                    @endif

                                                    @if ($appointment->status !== 'completed')
                                                        <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="completed">
                                                            <button class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                                                                {{ __('Quick Complete') }}
                                                            </button>
                                                        </form>
                                                    @endif

                                                    @if ($appointment->status !== 'cancelled')
                                                        <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">
                                                                {{ __('Quick Cancel') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </article>
                                    @empty
                                        <div class="rounded-lg border border-dashed border-gray-200 px-4 py-4 text-sm text-gray-400">
                                            {{ __('No appointments at this time.') }}
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">{{ __('Chronological List') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Operational view for the selected day.') }}</p>
                    </div>

                    <div class="divide-y divide-gray-200">
                        @forelse ($appointments as $appointment)
                            <article class="px-6 py-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ $appointment->start_time->format('H:i') }} - {{ $appointment->end_time->format('H:i') }}
                                            </p>
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                                {{ $appointment->statusLabel() }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-sm font-medium text-gray-800">{{ $appointment->client->name }}</p>
                                        <p class="mt-1 text-sm text-gray-500">{{ $appointment->service->name }} • {{ $appointment->user->name }}</p>
                                    </div>

                                    <a href="{{ route('appointments.show', $appointment) }}" class="shrink-0 text-sm font-medium text-gray-600 hover:text-gray-900">
                                        {{ __('View') }}
                                    </a>
                                </div>
                            </article>
                        @empty
                            <div class="px-6 py-8 text-sm text-gray-500">
                                {{ __('No appointments found.') }}
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
