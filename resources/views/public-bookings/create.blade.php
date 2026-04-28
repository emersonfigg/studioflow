<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Agendar horario - {{ $company->name }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#1b335b] font-sans text-white antialiased">
        <main class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
            <div
                x-data="{
                    selectedServiceIds: @js($selectedServiceIds->map(fn ($id) => (string) $id)->all()),
                    catalog: @js($servicesCatalog),
                    selectedServices() {
                        return this.catalog.filter((service) => this.selectedServiceIds.includes(String(service.id)));
                    },
                    totalDuration() {
                        return this.selectedServices().reduce((total, service) => total + Number(service.duration), 0);
                    },
                    totalPrice() {
                        return this.selectedServices().reduce((total, service) => total + Number(service.price_value), 0);
                    },
                    formattedTotalPrice() {
                        return this.totalPrice().toFixed(2).replace('.', ',');
                    }
                }"
                class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            >
                <section class="space-y-6">
                    <header class="sf-card overflow-hidden px-5 py-6 sm:px-6">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="inline-flex items-center rounded-full border border-[#d4af37]/20 bg-[#d4af37]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-[#d4af37]">
                                    StudioFlow Booking
                                </div>
                                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">
                                    Monte seu agendamento completo
                                </h1>
                                <p class="mt-2 max-w-2xl text-sm leading-7 text-[#c7d2e3]">
                                    Escolha um ou mais servicos, selecione o profissional e reserve um bloco de horario livre de uma vez so.
                                </p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">{{ $company->name }}</p>
                                <p class="mt-2 text-sm text-[#c7d2e3]">Agendamento online premium</p>
                            </div>
                        </div>
                    </header>

                    <form id="booking-filters" method="GET" action="{{ route('public-bookings.create', $company) }}" class="space-y-6">
                        <input type="hidden" name="filters_submitted" value="1">

                        <section class="sf-card p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">1</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-white">1. Escolha os servicos</h2>
                                    <p class="text-sm text-[#c7d2e3]">Marque um ou mais servicos para montar o atendimento.</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3">
                                @foreach ($services as $service)
                                    @php
                                        $checked = $selectedServiceIds->contains($service->id);
                                    @endphp
                                    <label class="block cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="service_ids[]"
                                            value="{{ $service->id }}"
                                            class="peer sr-only"
                                            x-model="selectedServiceIds"
                                            onchange="this.form.submit()"
                                            @checked($checked)
                                        >
                                        <span class="{{ $checked ? 'border-[#d4af37]/50 bg-[#d4af37]/12 shadow-[0_12px_28px_rgba(212,175,55,0.12)]' : 'border-white/10 bg-[#132746] hover:border-[#d4af37]/35 hover:bg-[#183157]' }} flex w-full items-start gap-4 rounded-[22px] border p-4 text-left transition peer-checked:border-[#d4af37]/50 peer-checked:bg-[#d4af37]/12">
                                            <span class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl border border-white/10 bg-[#1b335b]">
                                                @if ($service->image_url)
                                                    <img src="{{ $service->image_url }}" alt="Imagem de {{ $service->name }}" class="h-full w-full object-cover">
                                                @else
                                                    <span class="flex h-full w-full items-center justify-center bg-[#132746]">
                                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#d4af37]/12 text-[#d4af37]">
                                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2 1.586-1.586a2 2 0 012.828 0L20 14m-9-5h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                        </span>
                                                    </span>
                                                @endif
                                            </span>

                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-start justify-between gap-4">
                                                    <span>
                                                        <span class="block text-base font-semibold text-white">{{ $service->name }}</span>
                                                        <span class="mt-2 block text-sm text-[#c7d2e3]">{{ $service->duration_minutes }} min</span>
                                                        <span class="mt-1 block text-sm font-semibold text-[#d4af37]">R$ {{ number_format((float) $service->price, 2, ',', '.') }}</span>
                                                    </span>
                                                    <span class="{{ $checked ? 'border-[#d4af37] bg-[#d4af37] text-[#132746]' : 'border-white/15 bg-[#1b335b] text-[#c7d2e3]' }} mt-1 inline-flex min-h-[32px] items-center justify-center rounded-full border px-3 text-xs font-semibold transition">
                                                        {{ $checked ? 'Selecionado' : 'Selecionar' }}
                                                    </span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('service_ids')" />
                            <x-input-error class="mt-2" :messages="$errors->get('service_ids.*')" />
                        </section>

                        <section class="sf-card p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">2</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-white">2. Escolha o profissional</h2>
                                    <p class="text-sm text-[#c7d2e3]">Selecione quem vai conduzir o atendimento.</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                @foreach ($users as $user)
                                    @php
                                        $selected = $selectedUserId === $user->id;
                                    @endphp
                                    <label class="block cursor-pointer">
                                        <input
                                            type="radio"
                                            name="user_id"
                                            value="{{ $user->id }}"
                                            class="peer sr-only"
                                            onchange="this.form.submit()"
                                            @checked($selected)
                                        >
                                        <span class="{{ $selected ? 'border-[#d4af37]/50 bg-[#d4af37]/12 shadow-[0_12px_28px_rgba(212,175,55,0.12)]' : 'border-white/10 bg-[#132746] hover:border-[#d4af37]/35 hover:bg-[#183157]' }} flex items-center justify-between gap-4 rounded-[22px] border p-4 text-left transition">
                                            <span class="flex min-w-0 items-center gap-3">
                                                @if ($user->photo_url)
                                                    <img src="{{ $user->photo_url }}" alt="Foto de {{ $user->name }}" class="h-14 w-14 shrink-0 rounded-full object-cover ring-1 ring-white/10">
                                                @else
                                                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#d4af37]/12 text-lg font-semibold text-[#d4af37]">
                                                        {{ $user->avatar_initial }}
                                                    </span>
                                                @endif
                                                <span class="min-w-0">
                                                    <span class="block text-base font-semibold text-white">{{ $user->name }}</span>
                                                    <span class="mt-2 block text-sm text-[#c7d2e3]">Profissional</span>
                                                </span>
                                            </span>
                                            @if ($selected)
                                                <span class="rounded-full bg-[#d4af37] px-3 py-1 text-xs font-semibold text-[#132746]">Selecionado</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('user_id')" />
                        </section>

                        <section class="sf-card p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">3</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-white">3. Escolha a data</h2>
                                    <p class="text-sm text-[#c7d2e3]">Veja apenas horarios com o bloco completo livre.</p>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-3 gap-3 sm:grid-cols-6">
                                @foreach ($quickDates as $quickDate)
                                    @php
                                        $selected = $selectedDate === $quickDate['value'];
                                    @endphp
                                    <button
                                        type="submit"
                                        name="date"
                                        value="{{ $quickDate['value'] }}"
                                        class="{{ $selected ? 'border-[#d4af37]/50 bg-[#d4af37]/12 text-white' : 'border-white/10 bg-[#132746] text-[#c7d2e3] hover:border-[#d4af37]/35 hover:bg-[#183157] hover:text-white' }} rounded-2xl border px-3 py-4 text-center transition"
                                    >
                                        <span class="block text-sm font-semibold">{{ $quickDate['label'] }}</span>
                                        <span class="mt-1 block text-xs">{{ $quickDate['subtitle'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <div class="mt-4">
                                <label for="public-date" class="text-sm font-medium text-white">Escolher outra data</label>
                                <input
                                    id="public-date"
                                    name="date"
                                    type="date"
                                    value="{{ $selectedDate }}"
                                    class="sf-input mt-2 block w-full"
                                    onchange="this.form.submit()"
                                >
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('date')" />
                        </section>
                    </form>

                    <form method="POST" action="{{ route('public-bookings.store', $company) }}" class="space-y-6">
                        @csrf
                        @foreach ($selectedServiceIds as $serviceId)
                            <input type="hidden" name="service_ids[]" value="{{ $serviceId }}">
                        @endforeach
                        <input type="hidden" name="user_id" value="{{ $selectedUserId }}">
                        <input type="hidden" name="date" value="{{ $selectedDate }}">

                        <section class="sf-card p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">4</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-white">4. Horarios disponiveis</h2>
                                    <p class="text-sm text-[#c7d2e3]">Mostrando apenas horarios com o bloco total livre.</p>
                                </div>
                            </div>

                            <div class="mt-5">
                                @if ($selectedServiceIds->isEmpty())
                                    <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-5 text-sm text-[#c7d2e3]">
                                        Selecione pelo menos um servico para ver os horarios disponiveis.
                                    </div>
                                @elseif ($availableSlots !== [])
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        @foreach ($availableSlots as $slot)
                                            <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="time"
                                                    value="{{ $slot }}"
                                                    class="peer sr-only"
                                                    @checked(old('time', $selectedTime) === $slot)
                                                    required
                                                >
                                                <span class="flex min-h-[60px] items-center justify-center rounded-2xl border border-white/10 bg-[#132746] px-4 py-4 text-base font-semibold text-white transition peer-checked:border-[#d4af37]/50 peer-checked:bg-[#d4af37] peer-checked:text-[#132746] hover:border-[#d4af37]/35 hover:bg-[#183157]">
                                                    {{ $slot }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-5 text-sm text-[#c7d2e3]">
                                        Nenhum horario disponivel para essa data. Tente outro dia.
                                    </div>
                                @endif
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('time')" />
                        </section>

                        <section class="sf-card p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">5</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-white">5. Seus dados</h2>
                                    <p class="text-sm text-[#c7d2e3]">Informe seus dados para confirmar o agendamento completo.</p>
                                </div>
                            </div>

                            <div class="mt-5 space-y-4">
                                <div>
                                    <label for="client_name" class="text-sm font-medium text-white">Nome</label>
                                    <input
                                        id="client_name"
                                        name="client_name"
                                        type="text"
                                        value="{{ old('client_name') }}"
                                        class="sf-input mt-2 block w-full"
                                        required
                                    >
                                    <x-input-error class="mt-2" :messages="$errors->get('client_name')" />
                                </div>

                                <div>
                                    <label for="client_phone" class="text-sm font-medium text-white">Telefone/WhatsApp</label>
                                    <input
                                        id="client_phone"
                                        name="client_phone"
                                        type="text"
                                        value="{{ old('client_phone') }}"
                                        class="sf-input mt-2 block w-full"
                                        required
                                    >
                                    <x-input-error class="mt-2" :messages="$errors->get('client_phone')" />
                                </div>

                                <div>
                                    <label for="notes" class="text-sm font-medium text-white">Observacoes</label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        rows="3"
                                        class="sf-input mt-2 block w-full"
                                    >{{ old('notes') }}</textarea>
                                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                                </div>
                            </div>
                        </section>

                        <div class="xl:hidden">
                            <div class="sf-card p-5">
                                <h3 class="text-base font-semibold text-white">Resumo do agendamento</h3>
                                <div class="mt-4 space-y-3">
                                    <template x-for="service in selectedServices()" :key="service.id">
                                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-[#132746] px-4 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-white" x-text="service.name"></p>
                                                <p class="mt-1 text-xs text-[#c7d2e3]" x-text="service.duration + ' min'"></p>
                                            </div>
                                            <p class="text-sm font-semibold text-[#d4af37]" x-text="'R$ ' + service.price"></p>
                                        </div>
                                    </template>

                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-sm text-[#c7d2e3]">Profissional</dt>
                                        <dd class="text-sm font-semibold text-white">{{ $selectedUser?->name ?? '-' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-sm text-[#c7d2e3]">Data</dt>
                                        <dd class="text-sm font-semibold text-white">{{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-sm text-[#c7d2e3]">Horario</dt>
                                        <dd class="text-sm font-semibold text-white">{{ old('time', $selectedTime) ?: 'Selecione' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-sm text-[#c7d2e3]">Duracao total</dt>
                                        <dd class="text-sm font-semibold text-white" x-text="totalDuration() + ' min'">{{ $totalDurationMinutes }} min</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-sm text-[#c7d2e3]">Valor total</dt>
                                        <dd class="text-sm font-semibold text-[#d4af37]" x-text="'R$ ' + formattedTotalPrice()">R$ {{ number_format($totalPrice, 2, ',', '.') }}</dd>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="sf-button-primary w-full min-h-[60px] text-base" @disabled($selectedServiceIds->isEmpty())>
                            Confirmar agendamento
                        </button>
                    </form>
                </section>

                <aside class="hidden xl:block">
                    <div class="sticky top-6 space-y-6">
                        <section class="sf-card overflow-hidden">
                            <div class="border-b border-white/10 px-5 py-5">
                                <h3 class="text-lg font-semibold text-white">Resumo do agendamento</h3>
                                <p class="mt-1 text-sm text-[#c7d2e3]">Acompanhe servicos, bloco total e valor antes de confirmar.</p>
                            </div>

                            <div class="space-y-4 px-5 py-5">
                                <template x-for="service in selectedServices()" :key="service.id">
                                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="truncate text-base font-semibold text-white" x-text="service.name"></p>
                                                <p class="mt-1 text-sm text-[#c7d2e3]" x-text="service.duration + ' min'"></p>
                                            </div>
                                            <p class="text-sm font-semibold text-[#d4af37]" x-text="'R$ ' + service.price"></p>
                                        </div>
                                    </div>
                                </template>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Profissional</p>
                                    <p class="mt-2 text-base font-semibold text-white">{{ $selectedUser?->name ?? '-' }}</p>
                                </div>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Data e horario</p>
                                    <p class="mt-2 text-base font-semibold text-white">{{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">{{ old('time', $selectedTime) ?: 'Selecione um horario' }}</p>
                                </div>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Bloco total</p>
                                    <p class="mt-2 text-2xl font-semibold text-white" x-text="totalDuration() + ' min'">{{ $totalDurationMinutes }} min</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Tempo total reservado para os servicos escolhidos.</p>
                                </div>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Valor total</p>
                                    <p class="mt-2 text-2xl font-semibold text-white" x-text="'R$ ' + formattedTotalPrice()">R$ {{ number_format($totalPrice, 2, ',', '.') }}</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Total estimado dos servicos selecionados.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        </main>
    </body>
</html>
