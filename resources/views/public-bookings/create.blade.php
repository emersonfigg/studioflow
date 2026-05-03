<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Agendar horário - {{ $company->name }}</title>

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
                    selectedDate: @js($selectedDate),
                    hasProfessional: @js((bool) $selectedUser),
                    selectedServices() {
                        return this.catalog.filter((service) => this.selectedServiceIds.includes(String(service.id)));
                    },
                    totalDuration() {
                        return this.selectedServices().reduce((total, service) => total + Number(service.duration), 0);
                    },
                    totalPrice() {
                        return this.selectedServices().reduce((total, service) => total + Number(service.price_value), 0);
                    },
                    hasSelectedServices() {
                        return this.selectedServiceIds.length > 0;
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
                            <div class="flex items-start gap-4">
                                @if ($company->logo_url)
                                    <img src="{{ $company->logo_url }}" alt="Logo de {{ $company->name }}" class="h-16 w-16 rounded-3xl object-cover ring-1 ring-white/10">
                                @else
                                    <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-[#d4af37]/12 text-[#d4af37] ring-1 ring-white/10">
                                        <x-application-logo class="h-8 w-8" />
                                    </div>
                                @endif
                                <div>
                                <div class="inline-flex items-center rounded-full border border-[#d4af37]/20 bg-[#d4af37]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-[#d4af37]">
                                    Agendamento StudioFlow
                                </div>
                                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">
                                    Monte seu agendamento completo
                                </h1>
                                <p class="mt-2 max-w-2xl text-sm leading-7 text-[#c7d2e3]">
                                    {{ $company->description ?: 'Escolha um ou mais serviços, selecione o profissional e reserve um bloco de horário livre de uma vez só.' }}
                                </p>
                            </div>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">{{ $company->name }}</p>
                                <p class="mt-2 text-sm text-[#c7d2e3]">{{ $company->instagram ?: 'Agendamento online completo' }}</p>
                            </div>
                        </div>
                    </header>

                    <form id="booking-filters" method="GET" action="{{ route('public-bookings.create', $company) }}" class="space-y-6">
                        <input type="hidden" name="filters_submitted" value="1">

                        <section class="sf-card p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">1</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-white">1. Escolha os serviços</h2>
                                    <p class="text-sm text-[#c7d2e3]">Marque um ou mais serviços para calcular duração, valor e horários reais.</p>
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
                                    <p class="text-sm text-[#c7d2e3]">Selecione quem vai conduzir o atendimento. Hoje todos os profissionais ativos aparecem; a relação serviço/profissional ainda não existe.</p>
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
                                        <span class="{{ $selected ? 'border-[#d4af37] bg-gradient-to-br from-[#d4af37]/20 via-[#1f3a63] to-[#132746] ring-2 ring-[#d4af37]/45 shadow-[0_18px_40px_rgba(212,175,55,0.22)]' : 'border-white/10 bg-[#132746] hover:border-[#d4af37]/35 hover:bg-[#183157] hover:shadow-[0_14px_30px_rgba(8,20,42,0.28)]' }} flex items-center justify-between gap-4 rounded-[22px] border p-4 text-left transition">
                                            <span class="flex min-w-0 items-center gap-3">
                                                @if ($user->photo_url)
                                                    <img src="{{ $user->photo_url }}" alt="Foto de {{ $user->name }}" class="{{ $selected ? 'ring-[#d4af37]/60' : 'ring-white/10' }} h-14 w-14 shrink-0 rounded-full object-cover ring-2">
                                                @else
                                                    <span class="{{ $selected ? 'bg-[#d4af37] text-[#132746]' : 'bg-[#d4af37]/12 text-[#d4af37]' }} flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-lg font-semibold">
                                                        {{ $user->avatar_initial }}
                                                    </span>
                                                @endif
                                                <span class="min-w-0">
                                                    <span class="block text-base font-semibold text-white">{{ $user->name }}</span>
                                                    <span class="mt-2 block text-sm text-[#c7d2e3]">Profissional</span>
                                                </span>
                                            </span>
                                            @if ($selected)
                                                <span class="inline-flex items-center gap-2 rounded-full bg-[#d4af37] px-3 py-1 text-xs font-semibold text-[#132746]">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.415 0l-3.25-3.25a1 1 0 111.414-1.42l2.543 2.544 6.543-6.544a1 1 0 011.415 0z" clip-rule="evenodd" />
                                                    </svg>
                                                    Selecionado
                                                </span>
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
                                    <p class="text-sm text-[#c7d2e3]">Veja apenas horários com o bloco completo livre.</p>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-3 gap-3 sm:grid-cols-6">
                                @foreach ($quickDates as $quickDate)
                                    @php
                                        $selected = $selectedDate === $quickDate['value'];
                                    @endphp
                                    <button
                                        type="button"
                                        @click="selectedDate = '{{ $quickDate['value'] }}'; $nextTick(() => $el.form.submit())"
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
                                    x-model="selectedDate"
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
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">3</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-white">3. Horários disponíveis</h2>
                                    <p class="text-sm text-[#c7d2e3]">Mostrando apenas horários com o bloco total livre.</p>
                                </div>
                            </div>

                            <div class="mt-5">
                                @if ($selectedServiceIds->isEmpty() || ! $selectedUser)
                                    <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-5 text-sm text-[#c7d2e3]">
                                        Para carregar os horários da agenda real, selecione pelo menos um serviço e um profissional.
                                        @if ($selectedUser && $selectedServiceIds->isEmpty())
                                            O profissional {{ $selectedUser->name }} já está selecionado; falta escolher o serviço.
                                        @endif
                                    </div>
                                @else
                                    @if (($slotOptions ?? []) !== [])
                                        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-white/10 bg-[#132746] px-4 py-3 text-xs text-[#c7d2e3]">
                                            <span class="font-semibold uppercase tracking-[0.16em] text-white">Legenda</span>
                                            <span class="inline-flex items-center gap-2">
                                                <span class="h-2.5 w-2.5 rounded-full bg-[#d4af37]"></span>
                                                Livre
                                            </span>
                                            <span class="inline-flex items-center gap-2">
                                                <span class="h-2.5 w-2.5 rounded-full bg-[#d96b6b]"></span>
                                                Reservado
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                            @foreach ($slotOptions as $slotOption)
                                                @php
                                                    $slot = $slotOption['time'];
                                                    $disabled = ! $slotOption['available'];
                                                    $reasonLabel = match ($slotOption['reason']) {
                                                        'reserved' => 'Reservado',
                                                        default => null,
                                                    };
                                                    $slotClasses = match ($slotOption['reason']) {
                                                        'reserved' => 'border-[#d96b6b]/35 bg-[#3a1f2b] text-[#ffd9d9]',
                                                        default => 'border-white/10 bg-[#132746] text-white hover:border-[#d4af37]/35 hover:bg-[#183157] peer-checked:border-[#d4af37]/50 peer-checked:bg-[#d4af37] peer-checked:text-[#132746]',
                                                    };
                                                @endphp
                                                <label class="cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="time"
                                                        value="{{ $slot }}"
                                                        class="peer sr-only"
                                                        @checked(old('time', $selectedTime) === $slot)
                                                        @disabled($disabled)
                                                        required
                                                    >
                                                    <span class="{{ $slotClasses }} flex min-h-[72px] flex-col items-center justify-center rounded-2xl border px-4 py-4 text-center transition {{ $disabled ? 'cursor-not-allowed opacity-85' : '' }}">
                                                        <span class="text-base font-semibold">{{ $slot }}</span>
                                                        @if ($reasonLabel)
                                                            <span class="mt-1 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em] {{ $slotOption['reason'] === 'reserved' ? 'bg-[#d96b6b]/18 text-[#ffd9d9]' : 'bg-white/10 text-[#d5deec]' }}">
                                                                {{ $reasonLabel }}
                                                            </span>
                                                        @endif
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-5 text-sm text-[#c7d2e3]">
                                            Nenhum horário disponível para essa data. Tente outro dia.
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('time')" />
                        </section>

                        <section class="sf-card p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">4</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-white">4. Identificação do cliente</h2>
                                    <p class="text-sm text-[#c7d2e3]">Entre com Google ou preencha seus dados manualmente para confirmar.</p>
                                </div>
                            </div>

                            <div class="mt-5 space-y-4">
                                @if ($identifiedClient)
                                    <div class="rounded-2xl border border-[#d4af37]/30 bg-[#d4af37]/10 px-4 py-4">
                                        <p class="text-sm font-semibold text-white">Cliente identificado</p>
                                        <p class="mt-1 text-sm text-[#c7d2e3]">{{ $identifiedClient->name }}{{ $identifiedClient->email ? ' · '.$identifiedClient->email : '' }}</p>
                                    </div>
                                @else
                                    <a href="{{ route('public-bookings.google.redirect', ['company' => $company, ...request()->query()]) }}" class="flex w-full items-center justify-center gap-3 rounded-2xl border border-white/10 bg-white px-4 py-4 text-sm font-semibold text-[#132746] transition hover:bg-[#f3f6fb]">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#4285f4] text-xs font-bold text-white">G</span>
                                        Continuar com Google
                                    </a>
                                    <div class="flex items-center gap-3 text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">
                                        <span class="h-px flex-1 bg-white/10"></span>
                                        ou preencher manualmente
                                        <span class="h-px flex-1 bg-white/10"></span>
                                    </div>
                                @endif
                                @unless ($identifiedClient)
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
                                    <label for="client_email" class="text-sm font-medium text-white">E-mail</label>
                                    <input
                                        id="client_email"
                                        name="client_email"
                                        type="email"
                                        value="{{ old('client_email') }}"
                                        class="sf-input mt-2 block w-full"
                                        required
                                    >
                                    <x-input-error class="mt-2" :messages="$errors->get('client_email')" />
                                </div>
                                @endunless

                                <div>
                                    <label for="notes" class="text-sm font-medium text-white">Observações</label>
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
                                    <template x-if="hasSelectedServices()">
                                        <div class="space-y-3">
                                            <template x-for="service in selectedServices()" :key="service.id">
                                                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-[#132746] px-4 py-3">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-semibold text-white" x-text="service.name"></p>
                                                        <p class="mt-1 text-xs text-[#c7d2e3]" x-text="service.duration + ' min'"></p>
                                                    </div>
                                                    <p class="text-sm font-semibold text-[#d4af37]" x-text="'R$ ' + service.price"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="!hasSelectedServices()">
                                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-[#132746] px-4 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-white">Escolha depois</p>
                                                <p class="mt-1 text-xs text-[#c7d2e3]">Você pode selecionar o serviço antes de confirmar.</p>
                                            </div>
                                            <p class="text-sm font-semibold text-[#d4af37]">A definir</p>
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
                                        <dt class="text-sm text-[#c7d2e3]">Horário</dt>
                                        <dd class="text-sm font-semibold text-white">{{ old('time', $selectedTime) ?: 'Selecione' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-sm text-[#c7d2e3]">Bloco total</dt>
                                        <dd class="text-sm font-semibold text-white" x-text="hasSelectedServices() ? totalDuration() + ' min' : '30 min estimado'">{{ $usingEstimatedDuration ? '30 min estimado' : $totalDurationMinutes . ' min' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-sm text-[#c7d2e3]">Valor total</dt>
                                        <dd class="text-sm font-semibold text-[#d4af37]" x-text="hasSelectedServices() ? 'R$ ' + formattedTotalPrice() : 'A definir'">{{ $usingEstimatedDuration ? 'A definir' : 'R$ ' . number_format($totalPrice, 2, ',', '.') }}</dd>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="sf-button-primary w-full min-h-[60px] text-base disabled:cursor-not-allowed disabled:opacity-60"
                            x-bind:disabled="!hasSelectedServices() || !hasProfessional"
                        >
                            5. Confirmar agendamento
                        </button>
                        <template x-if="!hasSelectedServices() || !hasProfessional">
                            <p class="mt-3 text-center text-sm text-[#c7d2e3]">
                                Escolha pelo menos um serviço e um profissional antes de confirmar.
                            </p>
                        </template>
                    </form>
                </section>

                <aside class="hidden xl:block">
                    <div class="sticky top-6 space-y-6">
                        <section class="sf-card overflow-hidden">
                            <div class="border-b border-white/10 px-5 py-5">
                                <h3 class="text-lg font-semibold text-white">Resumo do agendamento</h3>
                                <p class="mt-1 text-sm text-[#c7d2e3]">Acompanhe serviços, bloco total e valor antes de confirmar.</p>
                            </div>

                            <div class="space-y-4 px-5 py-5">
                                <template x-if="hasSelectedServices()">
                                    <div class="space-y-4">
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
                                    </div>
                                </template>

                                <template x-if="!hasSelectedServices()">
                                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Serviço</p>
                                        <p class="mt-2 text-base font-semibold text-white">Escolha depois</p>
                                        <p class="mt-1 text-sm text-[#c7d2e3]">Veja a agenda primeiro e selecione o serviço antes de confirmar.</p>
                                    </div>
                                </template>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Profissional</p>
                                    <p class="mt-2 text-base font-semibold text-white">{{ $selectedUser?->name ?? '-' }}</p>
                                </div>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Data e horário</p>
                                    <p class="mt-2 text-base font-semibold text-white">{{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">{{ old('time', $selectedTime) ?: 'Selecione um horário' }}</p>
                                </div>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Bloco total</p>
                                    <p class="mt-2 text-2xl font-semibold text-white" x-text="hasSelectedServices() ? totalDuration() + ' min' : '30 min estimado'">{{ $usingEstimatedDuration ? '30 min estimado' : $totalDurationMinutes . ' min' }}</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Tempo total reservado para os serviços escolhidos.</p>
                                </div>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Valor total</p>
                                    <p class="mt-2 text-2xl font-semibold text-white" x-text="hasSelectedServices() ? 'R$ ' + formattedTotalPrice() : 'A definir'">{{ $usingEstimatedDuration ? 'A definir' : 'R$ ' . number_format($totalPrice, 2, ',', '.') }}</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Total estimado dos serviços selecionados.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        </main>
    </body>
</html>
