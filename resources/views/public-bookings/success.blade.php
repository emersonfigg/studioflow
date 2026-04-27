<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Booking Confirmed') }} - {{ $company->name }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-3xl items-center justify-center px-4 py-6 sm:px-6 lg:px-8">
            <section class="w-full overflow-hidden rounded-xl bg-white shadow-sm">
                <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-6 sm:px-6">
                    <p class="text-sm font-semibold text-emerald-700">{{ __('Booking Confirmed') }}</p>
                    <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $company->name }}</h1>
                    <p class="mt-2 text-sm text-gray-600">{{ __('Your booking request has been registered successfully.') }}</p>
                </div>

                <div class="space-y-6 px-5 py-6 sm:px-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Client') }}</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900">{{ $appointment->client->name }}</p>
                            <p class="mt-1 text-sm text-gray-600">{{ $appointment->client->phone }}</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Status') }}</p>
                            <p class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                {{ $appointment->statusLabel() }}
                            </p>
                            <p class="mt-2 text-sm text-gray-600">{{ __('Source') }}: {{ __('Public Booking') }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200">
                        <dl class="grid gap-0 sm:grid-cols-2">
                            <div class="border-b border-gray-200 px-4 py-4 sm:border-b-0 sm:border-r">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Service') }}</dt>
                                <dd class="mt-2 text-sm font-semibold text-gray-900">{{ $appointment->service->name }}</dd>
                                <p class="mt-1 text-sm text-gray-600">{{ __('Price') }}: R$ {{ number_format((float) $appointment->service->price, 2, ',', '.') }}</p>
                            </div>
                            <div class="px-4 py-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Professional') }}</dt>
                                <dd class="mt-2 text-sm font-semibold text-gray-900">{{ $appointment->user->name }}</dd>
                                <p class="mt-1 text-sm text-gray-600">{{ __('Duration') }}: {{ $appointment->service->duration_minutes }} {{ __('minutes') }}</p>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Appointment Details') }}</p>
                        <p class="mt-2 text-sm text-gray-900">{{ __('Date') }}: {{ $appointment->start_time->format('d/m/Y') }}</p>
                        <p class="mt-1 text-sm text-gray-900">{{ __('Start') }}: {{ $appointment->start_time->format('H:i') }}</p>
                        <p class="mt-1 text-sm text-gray-900">{{ __('End') }}: {{ $appointment->end_time->format('H:i') }}</p>
                        @if ($appointment->notes)
                            <p class="mt-3 text-sm text-gray-600">{{ __('Notes') }}: {{ $appointment->notes }}</p>
                        @endif
                    </div>

                    <a href="{{ route('public-bookings.create', $company) }}" class="inline-flex w-full items-center justify-center rounded-md bg-gray-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-gray-800">
                        {{ __('Book Another Appointment') }}
                    </a>
                </div>
            </section>
        </main>
    </body>
</html>
