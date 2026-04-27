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
        <main class="mx-auto flex min-h-screen max-w-2xl items-start justify-center px-4 py-5 sm:px-6">
            <section class="w-full overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-100 px-4 py-5 sm:px-6">
                    <p class="text-sm font-medium text-indigo-600">{{ $company->name }}</p>
                    <h1 class="mt-1 text-xl font-semibold text-gray-900">{{ __('Book Appointment') }}</h1>
                </div>

                <form method="POST" action="{{ route('public-bookings.store', $company) }}" class="space-y-6 px-4 py-5 sm:px-6">
                    @csrf

                    <div class="space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">1. Escolha o serviço</p>
                        </div>

                        <div class="grid gap-3">
                            @foreach ($services as $service)
                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        name="service_id"
                                        value="{{ $service->id }}"
                                        class="peer sr-only"
                                        @checked((int) old('service_id', $selectedServiceId) === $service->id)
                                        required
                                    >
                                    <span class="flex rounded-xl border border-gray-200 bg-white px-4 py-4 text-sm text-gray-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-950 hover:border-indigo-300">
                                        <span class="font-medium">
                                            {{ $service->name }} - R$ {{ number_format((float) $service->price, 2, ',', '.') }} - {{ $service->duration_minutes }} min
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-gray-900">2. Escolha o profissional</p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($users as $user)
                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        name="user_id"
                                        value="{{ $user->id }}"
                                        class="peer sr-only"
                                        @checked((int) old('user_id', $selectedUserId) === $user->id)
                                        required
                                    >
                                    <span class="flex min-h-12 items-center rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-950 hover:border-indigo-300">
                                        {{ $user->name }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <x-input-error class="mt-2" :messages="$errors->get('user_id')" />
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-gray-900">3. Escolha a data</p>

                        <input
                            id="date"
                            name="date"
                            type="date"
                            value="{{ old('date', $selectedDate) }}"
                            class="block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >

                        <x-input-error class="mt-2" :messages="$errors->get('date')" />
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-gray-900">4. Horários disponíveis</p>
                            <a
                                href="{{ route('public-bookings.create', [
                                    'company' => $company,
                                    'service_id' => old('service_id', $selectedServiceId),
                                    'user_id' => old('user_id', $selectedUserId),
                                    'date' => old('date', $selectedDate),
                                ]) }}"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                {{ __('Check Availability') }}
                            </a>
                        </div>

                        @if ($availableSlots !== [])
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($availableSlots as $slot)
                                    <label class="cursor-pointer">
                                        <input
                                            type="radio"
                                            name="time"
                                            value="{{ $slot }}"
                                            class="peer sr-only"
                                            @checked(old('time') === $slot)
                                            required
                                        >
                                        <span class="flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm font-semibold text-gray-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-600 peer-checked:text-white hover:border-indigo-300 hover:bg-indigo-50">
                                            {{ $slot }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                                Nenhum horário disponível para essa data.
                            </div>
                        @endif

                        <x-input-error class="mt-2" :messages="$errors->get('time')" />
                    </div>

                    <div class="space-y-4 border-t border-gray-100 pt-6">
                        <p class="text-sm font-semibold text-gray-900">5. Seus dados</p>

                        <div>
                            <label for="client_name" class="text-sm font-medium text-gray-700">Nome</label>
                            <input
                                id="client_name"
                                name="client_name"
                                type="text"
                                value="{{ old('client_name') }}"
                                class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                            <x-input-error class="mt-2" :messages="$errors->get('client_name')" />
                        </div>

                        <div>
                            <label for="client_phone" class="text-sm font-medium text-gray-700">Telefone</label>
                            <input
                                id="client_phone"
                                name="client_phone"
                                type="text"
                                value="{{ old('client_phone') }}"
                                class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                            <x-input-error class="mt-2" :messages="$errors->get('client_phone')" />
                        </div>

                        <div>
                            <label for="notes" class="text-sm font-medium text-gray-700">{{ __('Notes') }}</label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >{{ old('notes') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                        </div>
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                        Confirmar agendamento
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
