@php
    $selectedServiceIdStrings = $selectedServiceIds->map(fn ($id) => (string) $id)->all();
    $today = \Carbon\CarbonImmutable::today()->toDateString();
    $slotPeriods = collect($slotOptions ?? [])->where('available', true)->groupBy(function (array $slot): string {
        $hour = (int) substr($slot['time'], 0, 2);

        return match (true) {
            $hour < 12 => 'Manha',
            $hour < 18 => 'Tarde',
            default => 'Noite',
        };
    });
    $nextAvailableSlot = collect($slotOptions ?? [])->firstWhere('available', true)['time'] ?? null;
    $bookingQuery = [
        'service_ids' => $selectedServiceIds->all(),
        'user_id' => $selectedUserId,
        'date' => $selectedDate,
        'filters_submitted' => 1,
    ];
@endphp

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
        <main class="mx-auto min-h-screen w-full max-w-full px-4 pb-28 pt-4 sm:px-5 md:max-w-none lg:max-w-7xl lg:px-8 lg:pb-10">
            <div
                x-data="{
                    selectedServiceIds: @js($selectedServiceIdStrings),
                    serviceSearch: '',
                    selectedDate: @js($selectedDate),
                    selectedTime: @js(old('time', $selectedTime)),
                    hasProfessional: @js((bool) $selectedUser),
                    clientName: @js(old('client_name', $identifiedClient?->name)),
                    clientPhone: @js(old('client_phone', $identifiedClient?->phone)),
                    clientEmail: @js(old('client_email', $identifiedClient?->email)),
                    catalog: @js($servicesCatalog),
                    categories: ['Todos', 'Serviços'],
                    selectedCategory: 'Todos',
                    visibleSlotLimits: { Manha: 8, Tarde: 8, Noite: 8 },
                    showMoreSlots(period) {
                        this.visibleSlotLimits[period] = (this.visibleSlotLimits[period] || 8) + 8;
                    },
                    applyFilters() {
                        sessionStorage.setItem('publicBookingScrollY', String(window.scrollY));
                        this.$nextTick(() => {
                            if (this.$refs.bookingFilters) {
                                this.$refs.bookingFilters.submit();
                            }
                        });
                    },
                    clientNameTokens() {
                        return String(this.clientName || '')
                            .trim()
                            .split(/\s+/u)
                            .filter(Boolean);
                    },
                    fullNameStrongOk() {
                        const meaningful = this.clientNameTokens().filter((w) => w.length >= 2);

                        return meaningful.length >= 2;
                    },
                    clientNameFieldHint() {
                        if (@js((bool) $identifiedClient)) {
                            return null;
                        }
                        const raw = String(this.clientName || '').trim();
                        if (raw === '') {
                            return 'Informe seu nome completo.';
                        }
                        const tokens = this.clientNameTokens();
                        const meaningful = tokens.filter((w) => w.length >= 2);
                        if (meaningful.length >= 2) {
                            return null;
                        }
                        if (tokens.length <= 1) {
                            return 'Informe também seu sobrenome para continuar.';
                        }

                        return 'Informe nome e sobrenome com pelo menos 2 letras cada.';
                    },
                    blockedByIncompleteFullNameOnly() {
                        if (@js((bool) $identifiedClient)) {
                            return false;
                        }

                        return this.readyForSlots()
                            && Boolean(this.selectedTime)
                            && String(this.clientPhone || '').trim().length > 0
                            && String(this.clientEmail || '').trim().length > 0
                            && !this.fullNameStrongOk();
                    },
                    focusClientNameField() {
                        const el = document.getElementById('client_name');
                        if (!el) {
                            return;
                        }
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        try {
                            el.focus({ preventScroll: true });
                        } catch (_) {}
                    },
                    bookingSubmitIntercept(e) {
                        if (@js((bool) $identifiedClient)) {
                            return;
                        }
                        if (this.readyToConfirm()) {
                            return;
                        }
                        e.preventDefault();
                        this.$nextTick(() => {
                            if (this.blockedByIncompleteFullNameOnly()) {
                                this.focusClientNameField();
                            }
                        });
                    },
                    selectedServices() {
                        return this.catalog.filter((service) => this.selectedServiceIds.includes(String(service.id)));
                    },
                    filteredServices() {
                        const term = this.serviceSearch.trim().toLowerCase();

                        return this.catalog.filter((service) => {
                            const matchesCategory = this.selectedCategory === 'Todos' || service.category === this.selectedCategory;
                            const searchable = `${service.name} ${service.description || ''}`.toLowerCase();

                            return matchesCategory && (!term || searchable.includes(term));
                        });
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
                    readyForSlots() {
                        return this.hasSelectedServices() && this.hasProfessional && this.selectedDate;
                    },
                    readyToConfirm() {
                        if (! this.readyForSlots() || ! this.selectedTime) {
                            return false;
                        }
                        if (@js((bool) $identifiedClient)) {
                            return true;
                        }

                        return this.fullNameStrongOk()
                            && String(this.clientPhone || '').trim().length > 0
                            && String(this.clientEmail || '').trim().length > 0;
                    },
                    formattedTotalPrice() {
                        return this.totalPrice().toFixed(2).replace('.', ',');
                    }
                }"
                class="grid w-full max-w-full gap-5 xl:grid-cols-[minmax(0,1fr)_360px]"
            >
                <section class="space-y-5">
                    <header class="overflow-hidden rounded-[24px] border border-white/10 bg-[#203d6b] px-4 py-5 shadow-[0_18px_48px_rgba(8,20,42,0.24)] sm:px-6">
                        <div class="flex items-start gap-4">
                            @if ($company->logo_url)
                                <img src="{{ $company->logo_url }}" alt="Logo de {{ $company->name }}" class="h-14 w-14 rounded-2xl object-cover ring-1 ring-white/10">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-[#d4af37] ring-1 ring-white/10">
                                    <x-application-logo class="h-7 w-7" />
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="inline-flex items-center rounded-full border border-[#d4af37]/20 bg-[#d4af37]/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#d4af37]">
                                    Agendamento StudioFlow
                                </div>
                                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                                    {{ $company->name }}
                                </h1>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#c7d2e3]">
                                    {{ $company->description ?: 'Escolha serviços, profissional, data e horário em poucos passos.' }}
                                </p>
                                @if ($company->instagram)
                                    <p class="mt-2 text-sm font-semibold text-[#d4af37]">{{ $company->instagram }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-3 gap-2 text-center text-[11px] font-semibold uppercase tracking-[0.12em] text-[#c7d2e3] sm:grid-cols-6">
                            @foreach (['Serviços', 'Profissional', 'Data', 'Horário', 'Dados', 'Confirmar'] as $index => $step)
                                <div class="rounded-2xl border {{ $index === 0 ? 'border-[#d4af37]/50 bg-[#d4af37]/12 text-white' : 'border-white/10 bg-[#132746]' }} px-2 py-3">
                                    <span class="block text-[#d4af37]">{{ $index + 1 }}</span>
                                    <span class="mt-1 block">{{ $step }}</span>
                                </div>
                            @endforeach
                        </div>
                    </header>

                    <form id="booking-filters" x-ref="bookingFilters" method="GET" action="{{ route('public-bookings.create', $company) }}" class="w-full max-w-full space-y-5">
                        <input type="hidden" name="filters_submitted" value="1">

                        <section class="sf-card p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">1. Serviços</p>
                                    <h2 class="mt-1 text-xl font-semibold text-white">Escolha um ou mais serviços</h2>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">A busca filtra sem recarregar a página.</p>
                                </div>
                                <span class="hidden rounded-full bg-[#132746] px-3 py-1 text-xs font-semibold text-[#c7d2e3] sm:inline-flex" x-text="`${selectedServiceIds.length} selecionado(s)`"></span>
                            </div>

                            <div x-show="hasSelectedServices()" class="mt-4 rounded-2xl border border-[#d4af37]/25 bg-[#d4af37]/10 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Selecionados</p>
                                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                                    <template x-for="service in selectedServices()" :key="service.id">
                                        <div class="flex min-w-[190px] items-center justify-between gap-3 rounded-2xl border border-white/10 bg-[#132746] px-3 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-white" x-text="service.name"></p>
                                                <p class="mt-1 text-xs text-[#c7d2e3]" x-text="`${service.duration} min · R$ ${service.price}`"></p>
                                            </div>
                                            <button type="button" class="text-xs font-semibold text-[#d4af37]" @click="selectedServiceIds = selectedServiceIds.filter((id) => id !== String(service.id)); $nextTick(() => applyFilters())">Remover</button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px]">
                                <div>
                                    <label for="service-search" class="sr-only">Buscar serviço</label>
                                    <input id="service-search" x-model="serviceSearch" type="search" class="sf-input block w-full" placeholder="Buscar por nome do serviço">
                                </div>
                                <select x-model="selectedCategory" class="sf-select block w-full" aria-label="Categoria">
                                    <template x-for="category in categories" :key="category">
                                        <option x-text="category"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="mt-4 max-h-[520px] space-y-3 overflow-y-auto pr-1">
                                @foreach ($services as $service)
                                    @php
                                        $checked = $selectedServiceIds->contains($service->id);
                                    @endphp
                                    <label
                                        class="block cursor-pointer"
                                        x-show="filteredServices().some((item) => Number(item.id) === {{ $service->id }})"
                                        x-cloak
                                    >
                                        <input
                                            type="checkbox"
                                            name="service_ids[]"
                                            value="{{ $service->id }}"
                                            class="peer sr-only"
                                            x-model="selectedServiceIds"
                                            @change="$nextTick(() => applyFilters())"
                                            @checked($checked)
                                        >
                                        <span class="{{ $checked ? 'border-[#d4af37]/50 bg-[#d4af37]/12' : 'border-white/10 bg-[#132746] hover:border-[#d4af37]/35 hover:bg-[#183157]' }} flex w-full items-center gap-3 rounded-[20px] border p-3 transition peer-checked:border-[#d4af37]/50 peer-checked:bg-[#d4af37]/12">
                                            <span class="h-14 w-14 shrink-0 overflow-hidden rounded-2xl border border-white/10 bg-[#1b335b]">
                                                @if ($service->image_url)
                                                    <img src="{{ $service->image_url }}" alt="Imagem de {{ $service->name }}" class="h-full w-full object-cover">
                                                @else
                                                    <span class="flex h-full w-full items-center justify-center text-[#d4af37]">
                                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2 1.586-1.586a2 2 0 012.828 0L20 14m-9-5h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </span>
                                                @endif
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-semibold text-white">{{ $service->name }}</span>
                                                <span class="mt-1 flex flex-wrap gap-2 text-xs text-[#c7d2e3]">
                                                    <span>{{ $service->duration_minutes }} min</span>
                                                    <span class="font-semibold text-[#d4af37]">R$ {{ number_format((float) $service->price, 2, ',', '.') }}</span>
                                                </span>
                                            </span>
                                            <span class="{{ $checked ? 'bg-[#d4af37] text-[#132746]' : 'bg-[#1b335b] text-[#c7d2e3]' }} rounded-full px-3 py-2 text-xs font-semibold">
                                                {{ $checked ? 'Remover' : 'Selecionar' }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach

                                <div x-show="filteredServices().length === 0" x-cloak class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-6 text-center text-sm text-[#c7d2e3]">
                                    Nenhum serviço encontrado.
                                </div>
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('service_ids')" />
                            <x-input-error class="mt-2" :messages="$errors->get('service_ids.*')" />
                        </section>

                        <section class="sf-card p-4 sm:p-5" :class="!hasSelectedServices() ? 'opacity-70' : ''">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">2</span>
                                <div>
                                    <h2 class="text-xl font-semibold text-white">Escolha o profissional</h2>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">A relação serviço/profissional ainda não existe; por enquanto todos os profissionais ativos aparecem.</p>
                                </div>
                            </div>

                            <div x-show="!hasSelectedServices()" class="mt-4 rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-4 text-sm text-[#c7d2e3]">
                                Selecione pelo menos um serviço para seguir.
                            </div>

                            <div x-show="hasSelectedServices()" class="mt-4 grid gap-3 sm:grid-cols-2" x-cloak>
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
                                            @change="$nextTick(() => applyFilters())"
                                            @checked($selected)
                                        >
                                        <span class="{{ $selected ? 'border-[#d4af37] bg-[#d4af37]/12 ring-2 ring-[#d4af37]/35' : 'border-white/10 bg-[#132746] hover:border-[#d4af37]/35' }} flex items-center justify-between gap-3 rounded-[20px] border p-3 transition">
                                            <span class="flex min-w-0 items-center gap-3">
                                                @if ($user->photo_url)
                                                    <img src="{{ $user->photo_url }}" alt="Foto de {{ $user->name }}" class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-white/10">
                                                @else
                                                    <span class="{{ $selected ? 'bg-[#d4af37] text-[#132746]' : 'bg-[#d4af37]/12 text-[#d4af37]' }} flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-base font-semibold">
                                                        {{ $user->avatar_initial }}
                                                    </span>
                                                @endif
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                                                    <span class="mt-1 block text-xs text-[#c7d2e3]">Disponível para seleção</span>
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

                        <section class="sf-card p-4 sm:p-5" :class="!hasSelectedServices() || !hasProfessional ? 'opacity-70' : ''">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">3</span>
                                <div>
                                    <h2 class="text-xl font-semibold text-white">Escolha a data</h2>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Datas passadas ficam bloqueadas pelo calendário.</p>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-7">
                                @foreach ($quickDates as $quickDate)
                                    @php
                                        $selected = $selectedDate === $quickDate['value'];
                                    @endphp
                                    <button
                                        type="button"
                                        @click="selectedDate = '{{ $quickDate['value'] }}'; $nextTick(() => applyFilters())"
                                        class="{{ $selected ? 'border-[#d4af37]/50 bg-[#d4af37]/12 text-white' : 'border-white/10 bg-[#132746] text-[#c7d2e3] hover:border-[#d4af37]/35 hover:bg-[#183157]' }} rounded-2xl border px-2 py-3 text-center transition"
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
                                    min="{{ $today }}"
                                    x-model="selectedDate"
                                    class="sf-input mt-2 block w-full"
                                    @change="$nextTick(() => applyFilters())"
                                >
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('date')" />
                        </section>
                    </form>

                    <form method="POST" action="{{ route('public-bookings.store', $company) }}" class="space-y-5" @submit="bookingSubmitIntercept($event)">
                        @csrf
                        @foreach ($selectedServiceIds as $serviceId)
                            <input type="hidden" name="service_ids[]" value="{{ $serviceId }}">
                        @endforeach
                        <input type="hidden" name="user_id" value="{{ $selectedUserId }}">
                        <input type="hidden" name="date" value="{{ $selectedDate }}">

                        <section class="sf-card p-4 sm:p-5" :class="!readyForSlots() ? 'opacity-70' : ''">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">4</span>
                                <div>
                                    <h2 class="text-xl font-semibold text-white">Escolha o horário</h2>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Horários são calculados pela duração total dos serviços.</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                @if ($selectedServiceIds->isEmpty() || ! $selectedUser)
                                    <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-5 text-sm text-[#c7d2e3]">
                                        Para carregar os horários da agenda real, selecione pelo menos um serviço e um profissional.
                                        @if ($selectedUser && $selectedServiceIds->isEmpty())
                                            O profissional {{ $selectedUser->name }} já está selecionado; falta escolher o serviço.
                                        @endif
                                    </div>
                                @elseif ($slotPeriods->isEmpty())
                                    <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-5 text-sm text-[#c7d2e3]">
                                        Nenhum horário disponível para esta data.
                                    </div>
                                @else
                                    <div class="space-y-4">
                                        @if ($nextAvailableSlot)
                                            <div class="flex flex-col gap-3 rounded-[20px] border border-[#d4af37]/35 bg-[#d4af37]/12 p-4 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Pr&oacute;ximo hor&aacute;rio dispon&iacute;vel</p>
                                                    <p class="mt-1 text-2xl font-semibold text-white">{{ $nextAvailableSlot }}</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="rounded-2xl bg-[#d4af37] px-4 py-3 text-sm font-semibold text-[#132746]"
                                                    @click="selectedTime = @js($nextAvailableSlot)"
                                                >
                                                    Usar este hor&aacute;rio
                                                </button>
                                            </div>
                                        @endif

                                        <div class="rounded-[20px] border border-white/10 bg-[#132746]/70 p-4">
                                            <label for="booking-time" class="text-sm font-medium text-white">Hor&aacute;rio do atendimento</label>
                                            <select id="booking-time" name="time" x-model="selectedTime" class="sf-select mt-2 block w-full @error('time') ring-2 ring-rose-500 ring-offset-2 ring-offset-[#132746] @enderror" required>
                                                <option value="">Selecione um hor&aacute;rio</option>
                                                @foreach (['Manha', 'Tarde', 'Noite'] as $period)
                                                    @continue(! $slotPeriods->has($period))
                                                    <optgroup label="{!! $period === 'Manha' ? 'Manh&atilde;' : e($period) !!}">
                                                        @foreach ($slotPeriods->get($period) as $slotOption)
                                                            <option value="{{ $slotOption['time'] }}" @selected(old('time', $selectedTime) === $slotOption['time'])>
                                                                {{ $slotOption['time'] }}
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>

                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach (collect($slotOptions ?? [])->where('available', true)->take(6) as $slotOption)
                                                    <button
                                                        type="button"
                                                        class="rounded-full border border-white/10 bg-[#102744] px-3 py-2 text-xs font-semibold text-white transition hover:border-[#d4af37]/35 hover:bg-[#183157]"
                                                        @click="selectedTime = @js($slotOption['time'])"
                                                    >
                                                        {{ $slotOption['time'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('time')" />
                        </section>

                        <section class="sf-card p-4 sm:p-5" :class="!selectedTime ? 'opacity-70' : ''">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">5</span>
                                <div>
                                    <h2 class="text-xl font-semibold text-white">Dados do cliente</h2>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Use Google se estiver disponível ou preencha manualmente.</p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-4">
                                @if ($identifiedClient)
                                    <div class="rounded-2xl border border-[#d4af37]/30 bg-[#d4af37]/10 px-4 py-4">
                                        <p class="text-sm font-semibold text-white">Cliente identificado</p>
                                        <p class="mt-1 text-sm text-[#c7d2e3]">{{ $identifiedClient->name }}{{ $identifiedClient->email ? ' · '.$identifiedClient->email : '' }}</p>
                                    </div>
                                @elseif ($googleConfigured)
                                    <a href="{{ route('public-bookings.google.redirect', ['company' => $company, ...$bookingQuery]) }}" class="flex w-full items-center justify-center gap-3 rounded-2xl border border-white/10 bg-white px-4 py-4 text-sm font-semibold text-[#132746] transition hover:bg-[#f3f6fb]">
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
                                        <label for="client_name" class="text-sm font-medium text-white">Nome completo</label>
                                        <input
                                            id="client_name"
                                            name="client_name"
                                            type="text"
                                            x-model="clientName"
                                            value="{{ old('client_name') }}"
                                            autocomplete="name"
                                            :class="{ 'ring-2 ring-amber-400 ring-offset-2 ring-offset-[#1b335b]': clientNameFieldHint() !== null && ! @js($errors->has('client_name')) }"
                                            class="sf-input mt-2 block w-full @error('client_name') ring-2 ring-rose-500 ring-offset-2 ring-offset-[#1b335b] @enderror"
                                            required
                                            minlength="3"
                                        >
                                        <p class="mt-1 text-xs text-amber-100/90" x-show="clientNameFieldHint()" x-cloak x-text="clientNameFieldHint()"></p>
                                        <p class="mt-1 text-xs text-[#c7d2e3]" x-show="clientNameFieldHint() === null" x-cloak>Exemplo: Ana Paula Souza · nome e sobrenome com pelo menos 2 letras.</p>
                                        <x-input-error class="mt-2" :messages="$errors->get('client_name')" />
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label for="client_phone" class="text-sm font-medium text-white">Telefone/WhatsApp</label>
                                            <input
                                                id="client_phone"
                                                name="client_phone"
                                                type="text"
                                                x-model="clientPhone"
                                                value="{{ old('client_phone') }}"
                                                class="sf-input mt-2 block w-full @error('client_phone') ring-2 ring-rose-500 ring-offset-2 ring-offset-[#1b335b] @enderror"
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
                                                x-model="clientEmail"
                                                value="{{ old('client_email') }}"
                                                class="sf-input mt-2 block w-full @error('client_email') ring-2 ring-rose-500 ring-offset-2 ring-offset-[#1b335b] @enderror"
                                                required
                                            >
                                            <x-input-error class="mt-2" :messages="$errors->get('client_email')" />
                                        </div>
                                    </div>
                                @endunless

                                <div>
                                    <label for="notes" class="text-sm font-medium text-white">Observações</label>
                                    <textarea id="notes" name="notes" rows="3" class="sf-input mt-2 block w-full">{{ old('notes') }}</textarea>
                                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                                </div>
                            </div>
                        </section>

                        <section class="sf-card p-4 sm:p-5">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">6</span>
                                <div>
                                    <h2 class="text-xl font-semibold text-white">Confirmação</h2>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">O horário será confirmado após o envio. A disponibilidade será validada novamente.</p>
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="sf-button-primary mt-5 w-full min-h-[60px] text-base transition"
                                :class="{ 'cursor-not-allowed opacity-60': !readyToConfirm() }"
                                :aria-disabled="!readyToConfirm()"
                            >
                                Confirmar agendamento
                            </button>
                            <template x-if="!readyToConfirm()">
                                <div class="mt-3 space-y-1 text-center">
                                    <p x-show="blockedByIncompleteFullNameOnly()" x-cloak class="text-sm font-medium text-amber-100">
                                        Para confirmar, informe seu nome e sobrenome.
                                    </p>
                                    <p x-show="!blockedByIncompleteFullNameOnly()" x-cloak class="text-sm text-[#c7d2e3]">
                                        Complete as etapas anteriores para confirmar.
                                    </p>
                                </div>
                            </template>
                        </section>
                    </form>
                </section>

                <aside class="hidden xl:block">
                    <div class="sticky top-5 space-y-4">
                        <section class="sf-card overflow-hidden">
                            <div class="border-b border-white/10 px-5 py-5">
                                <h3 class="text-lg font-semibold text-white">Resumo do agendamento</h3>
                                <p class="mt-1 text-sm text-[#c7d2e3]">Acompanhe tudo antes de confirmar.</p>
                            </div>

                            <div class="space-y-4 px-5 py-5">
                                <template x-if="hasSelectedServices()">
                                    <div class="space-y-3">
                                        <template x-for="service in selectedServices()" :key="service.id">
                                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-3">
                                                <div class="flex items-center justify-between gap-4">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-semibold text-white" x-text="service.name"></p>
                                                        <p class="mt-1 text-xs text-[#c7d2e3]" x-text="service.duration + ' min'"></p>
                                                    </div>
                                                    <p class="text-sm font-semibold text-[#d4af37]" x-text="'R$ ' + service.price"></p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="!hasSelectedServices()">
                                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Serviços</p>
                                        <p class="mt-2 text-base font-semibold text-white">Escolha os serviços</p>
                                    </div>
                                </template>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Profissional</p>
                                    <p class="mt-2 text-base font-semibold text-white">{{ $selectedUser?->name ?? '-' }}</p>
                                </div>

                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Data e horário</p>
                                    <p class="mt-2 text-base font-semibold text-white">{{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]" x-text="selectedTime || 'Selecione um horário'">{{ old('time', $selectedTime) ?: 'Selecione um horário' }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                        <p class="text-xs text-[#c7d2e3]">Duração</p>
                                        <p class="mt-1 text-lg font-semibold text-white" x-text="hasSelectedServices() ? totalDuration() + ' min' : '0 min'">{{ $usingEstimatedDuration ? '0 min' : $totalDurationMinutes . ' min' }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                        <p class="text-xs text-[#c7d2e3]">Total</p>
                                        <p class="mt-1 text-lg font-semibold text-[#d4af37]" x-text="hasSelectedServices() ? 'R$ ' + formattedTotalPrice() : 'A definir'">{{ $usingEstimatedDuration ? 'A definir' : 'R$ ' . number_format($totalPrice, 2, ',', '.') }}</p>
                                    </div>
                                </div>

                                @unless ($identifiedClient)
                                    <p x-show="clientNameTokens().length && !fullNameStrongOk()" x-cloak class="rounded-2xl border border-amber-500/35 bg-amber-500/10 px-4 py-2 text-xs text-amber-50">
                                        Nome incompleto: informe também o sobrenome (mínimo 2 letras em cada parte).
                                    </p>
                                @endunless
                                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Cliente</p>
                                    <p class="mt-2 text-base font-semibold text-white" x-text="clientName || 'Preencha seus dados'">{{ $identifiedClient?->name ?? 'Preencha seus dados' }}</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]" x-text="clientPhone || clientEmail || '-'">{{ $identifiedClient?->email ?? '-' }}</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        </main>

        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-white/10 bg-[#132746]/95 px-4 py-3 shadow-[0_-18px_40px_rgba(8,20,42,0.35)] backdrop-blur xl:hidden">
            <div class="mx-auto flex w-full max-w-full items-center justify-between gap-3 sm:max-w-none lg:max-w-7xl">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white">
                        {{ $selectedServiceIds->isNotEmpty() ? $selectedServiceIds->count().' serviço(s) · '.$totalDurationMinutes.' min' : 'Escolha os serviços' }}
                    </p>
                    <p class="mt-1 text-xs text-[#c7d2e3]">
                        {{ $selectedServiceIds->isNotEmpty() ? 'R$ '.number_format($totalPrice, 2, ',', '.') : 'Total a definir' }}
                    </p>
                </div>
                <a href="#booking-filters" class="shrink-0 rounded-2xl bg-[#d4af37] px-4 py-3 text-sm font-semibold text-[#132746]">
                    Continuar
                </a>
            </div>
        </div>

        <script>
            (function () {
                var raw = sessionStorage.getItem('publicBookingScrollY');
                if (raw === null) {
                    return;
                }
                sessionStorage.removeItem('publicBookingScrollY');
                var y = parseInt(raw, 10);
                if (Number.isNaN(y)) {
                    return;
                }
                window.addEventListener(
                    'load',
                    function () {
                        requestAnimationFrame(function () {
                            window.scrollTo(0, y);
                        });
                    },
                    { once: true }
                );
            })();
        </script>

        @if ($errors->any())
            @php
                $scrollToId = match (true) {
                    $errors->has('client_name') => 'client_name',
                    $errors->has('client_phone') => 'client_phone',
                    $errors->has('client_email') => 'client_email',
                    $errors->has('time') => 'booking-time',
                    $errors->has('date') => 'public-date',
                    default => null,
                };
            @endphp
            @if ($scrollToId)
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var el = document.getElementById(@json($scrollToId));
                        if (! el) {
                            return;
                        }
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        try {
                            el.focus({ preventScroll: true });
                        } catch (e) {}
                    });
                </script>
            @endif
        @endif
    </body>
</html>
