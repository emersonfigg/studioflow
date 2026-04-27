<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('Book Appointment') }} - {{ $company->name }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-5xl items-start justify-center px-4 py-6 sm:px-6 lg:px-8">
            <div class="w-full space-y-6">
                <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-6 sm:px-6">
                        <p class="text-sm font-medium text-indigo-600">{{ __('Book Appointment') }}</p>
                        <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $company->name }}</h1>
                        <p class="mt-2 text-sm text-gray-600">{{ __('Choose service, professional and a good time to schedule.') }}</p>
                    </div>

                    <div class="px-5 py-6 sm:px-6">
                        @if (session('status') === 'public-booking-created')
                            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                                {{ __('Booking request sent successfully.') }}
                            </div>
                        @endif

                        <form method="GET" action="{{ route('public-bookings.create', $company) }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="sm:col-span-2 lg:col-span-1">
                                <label for="service_id" class="text-sm font-medium text-gray-700">{{ __('Service') }}</label>
                                <select id="service_id" name="service_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Select a service') }}</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}" @selected((int) old('service_id', $selectedServiceId) === $service->id)>
                                            {{ $service->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sm:col-span-2 lg:col-span-1">
                                <label for="user_id" class="text-sm font-medium text-gray-700">{{ __('Professional') }}</label>
                                <select id="user_id" name="user_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Select a staff member') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected((int) old('user_id', $selectedUserId) === $user->id)>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="date" class="text-sm font-medium text-gray-700">{{ __('Date') }}</label>
                                <input id="date" name="date" type="date" value="{{ old('date', $selectedDate) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div class="flex items-end">
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-800">
                                    {{ __('Check Availability') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                            <h2 class="text-base font-semibold text-gray-900">{{ __('Available Times') }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Schedule for') }} {{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}</p>
                        </div>

                        <div class="px-5 py-5 sm:px-6">
                            @if ($selectedServiceId && $selectedUserId)
                                @if ($availableSlots !== [])
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        @foreach ($availableSlots as $slot)
                                            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-3 text-sm font-medium text-gray-700 transition hover:border-indigo-300 hover:bg-indigo-50">
                                                <input
                                                    type="radio"
                                                    name="time"
                                                    value="{{ $slot }}"
                                                    form="public-booking-form"
                                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    @checked(old('time') === $slot)
                                                >
                                                <span>{{ $slot }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                                        {{ __('No times available for the selected date.') }}
                                    </p>
                                @endif
                            @else
                                <p class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                                    {{ __('Select a service, professional and date to view available times.') }}
                                </p>
                            @endif

                            <x-input-error class="mt-4" :messages="$errors->get('time')" />
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                            <h2 class="text-base font-semibold text-gray-900">{{ __('Your Details') }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Complete your details to confirm the request.') }}</p>
                        </div>

                        <form id="public-booking-form" method="POST" action="{{ route('public-bookings.store', $company) }}" class="space-y-4 px-5 py-5 sm:px-6">
                            @csrf

                            <input type="hidden" name="service_id" value="{{ old('service_id', $selectedServiceId) }}">
                            <input type="hidden" name="user_id" value="{{ old('user_id', $selectedUserId) }}">
                            <input type="hidden" name="date" value="{{ old('date', $selectedDate) }}">

                            <div>
                                <label for="client_name" class="text-sm font-medium text-gray-700">{{ __('Client Name') }}</label>
                                <input id="client_name" name="client_name" type="text" value="{{ old('client_name') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <x-input-error class="mt-2" :messages="$errors->get('client_name')" />
                            </div>

                            <div>
                                <label for="client_phone" class="text-sm font-medium text-gray-700">{{ __('Client Phone') }}</label>
                                <input id="client_phone" name="client_phone" type="text" value="{{ old('client_phone') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <x-input-error class="mt-2" :messages="$errors->get('client_phone')" />
                            </div>

                            <div>
                                <label for="notes" class="text-sm font-medium text-gray-700">{{ __('Notes') }}</label>
                                <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                            </div>

                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                {{ __('Schedule Appointment') }}
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        </main>
    </body>
</html>
