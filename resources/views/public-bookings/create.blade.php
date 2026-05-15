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
    $shareTitle = 'Agendar horário - '.$company->name;
    $shareDescription = 'Agende seu horário online com '.$company->name.' pelo StudioFlow.';
    $shareUrl = request()->fullUrl();
    $fallbackShareImage = 'https://placehold.co/1200x630/png?text=StudioFlow';
    $companyLogo = $publicBranding['logo_url'] ?? $company->logo_url;
    $absoluteLogoUrl = $companyLogo
        ? (str_starts_with($companyLogo, 'http://') || str_starts_with($companyLogo, 'https://') ? $companyLogo : url($companyLogo))
        : $fallbackShareImage;

    $ogImage = ! empty($publicBranding['cover_url'])
        ? (str_starts_with((string) $publicBranding['cover_url'], 'http://') || str_starts_with((string) $publicBranding['cover_url'], 'https://')
            ? $publicBranding['cover_url']
            : url($publicBranding['cover_url']))
        : $absoluteLogoUrl;

    if (app()->environment('production')) {
        $shareUrl = preg_replace('/^http:\/\//i', 'https://', $shareUrl) ?? $shareUrl;
        $absoluteLogoUrl = preg_replace('/^http:\/\//i', 'https://', $absoluteLogoUrl) ?? $absoluteLogoUrl;
        $ogImage = preg_replace('/^http:\/\//i', 'https://', (string) $ogImage) ?? $ogImage;
    }

    $hasBookingServices = $selectedServiceIds->isNotEmpty();
    $hasBookingPro = (bool) $selectedUserId;
    $hasBookingDate = filled($selectedDate);
    $bookingTimeEffective = old('time', $selectedTime);
    $hasBookingTime = filled($bookingTimeEffective);
    $hasBookingClient = (bool) $identifiedClient
        || (filled(old('client_name')) && filled(old('client_phone')) && filled(old('client_email')));
    $bookingStepDone = [
        $hasBookingServices,
        $hasBookingServices && $hasBookingPro,
        $hasBookingServices && $hasBookingPro && $hasBookingDate,
        $hasBookingServices && $hasBookingPro && $hasBookingDate && $hasBookingTime,
        $hasBookingServices && $hasBookingPro && $hasBookingDate && $hasBookingTime && $hasBookingClient,
        false,
    ];
    $bookingActiveStep = 0;
    for ($i = 0; $i < 6; $i++) {
        if (! ($bookingStepDone[$i] ?? false)) {
            $bookingActiveStep = $i;
            break;
        }
    }
    if ($bookingStepDone[0] && $bookingStepDone[1] && $bookingStepDone[2] && $bookingStepDone[3] && $bookingStepDone[4]) {
        $bookingActiveStep = 5;
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ ($publicBranding['theme_light'] ?? false) ? 'light' : 'dark' }}" style="{{ $publicBranding['root_style'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta property="og:title" content="{{ $shareTitle }}">
        <meta property="og:description" content="{{ $shareDescription }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:url" content="{{ $shareUrl }}">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $shareTitle }}">
        <meta name="twitter:description" content="{{ $shareDescription }}">
        <meta name="twitter:image" content="{{ $ogImage }}">

        <title>{{ $shareTitle }}</title>

        @if (! empty($publicFaviconHref))
            <link rel="icon" href="{{ $publicFaviconHref }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-booking-page min-h-screen font-sans text-white antialiased">
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
                    <header class="booking-hero overflow-hidden px-4 py-5 sm:px-6">
                        @if (! empty($publicBranding['cover_url']))
                            <div class="-mx-4 -mt-5 mb-5 h-36 bg-[var(--brand-secondary)] bg-cover bg-center sm:-mx-6 sm:-mt-5 sm:mb-6 sm:h-44" style="background-image: url({{ \Illuminate\Support\Js::from($publicBranding['cover_url']) }});"></div>
                        @endif
                        <div class="flex items-start gap-4">
                            @if (! empty($publicBranding['logo_url']))
                                <img src="{{ $publicBranding['logo_url'] }}" alt="Logo de {{ $company->name }}" class="h-14 w-14 rounded-2xl object-cover ring-1 ring-white/10" loading="lazy" decoding="async">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--brand-primary)]/12 text-[var(--brand-primary)] ring-1 ring-white/10">
                                    <x-application-logo class="h-7 w-7" />
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="inline-flex items-center rounded-full border border-[color:color-mix(in_srgb,var(--brand-primary)_35%,transparent)] bg-[color:color-mix(in_srgb,var(--brand-primary)_12%,transparent)] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)] shadow-[var(--shadow-soft)]">
                                    Agendamento · {{ $company->name }}
                                </div>
                                <h1 class="sf-page-title mt-3 text-white sm:text-3xl">
                                    {{ $publicBranding['hero_title'] }}
                                </h1>
                                <p class="sf-page-subtitle mt-2 max-w-2xl brand-muted">
                                    {{ $publicBranding['hero_subtitle'] ?? ($publicBranding['description_fallback'] ?: 'Escolha serviços, profissional, data e horário em poucos passos.') }}
                                </p>
                                @if (! empty($publicBranding['welcome_message']))
                                    <p class="mt-3 max-w-2xl rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_15%,transparent)] bg-[color:color-mix(in_srgb,var(--brand-accent)_55%,transparent)] px-3 py-2 text-sm leading-6 brand-muted">
                                        {{ $publicBranding['welcome_message'] }}
                                    </p>
                                @endif
                                @if ($company->instagram)
                                    <p class="mt-2 text-sm font-semibold text-[var(--brand-primary)]">{{ $company->instagram }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-3 gap-2 text-center text-[11px] font-semibold uppercase tracking-[0.12em] brand-muted sm:grid-cols-6">
                            @foreach (['Serviços', 'Profissional', 'Data', 'Horário', 'Dados', 'Confirmar'] as $index => $step)
                                @php
                                    $stepDone = $bookingStepDone[$index] ?? false;
                                    $stepActive = $index === $bookingActiveStep;
                                    $stepTone = $stepDone ? 'booking-step--complete' : ($stepActive ? 'booking-step--active' : 'booking-step--pending');
                                @endphp
                                <div class="booking-step rounded-2xl px-2 py-3 {{ $stepTone }}">
                                    <span class="booking-step-num block font-semibold">{{ $index + 1 }}</span>
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
                                    <p class="sf-page-eyebrow">1. Serviços</p>
                                    <h2 class="sf-section-title mt-1 text-white">Escolha um ou mais serviços</h2>
                                    <p class="mt-1 text-sm brand-muted">A busca filtra sem recarregar a página.</p>
                                </div>
                                <span class="hidden rounded-full bg-[color-mix(in_srgb,var(--brand-primary)_14%,var(--brand-surface))] px-3 py-1 text-xs font-semibold brand-muted sm:inline-flex" x-text="`${selectedServiceIds.length} selecionado(s)`"></span>
                            </div>

                            <div x-show="hasSelectedServices()" class="mt-4 rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)] p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--brand-primary)]">Selecionados</p>
                                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                                    <template x-for="service in selectedServices()" :key="service.id">
                                        <div class="flex min-w-[190px] items-center justify-between gap-3 rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_18%,transparent)] bg-[var(--brand-surface)] px-3 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-white" x-text="service.name"></p>
                                                <p class="mt-1 text-xs brand-muted" x-text="`${service.duration} min · R$ ${service.price}`"></p>
                                            </div>
                                            <button type="button" class="text-xs font-semibold text-[var(--brand-primary)]" @click="selectedServiceIds = selectedServiceIds.filter((id) => id !== String(service.id)); $nextTick(() => applyFilters())">Remover</button>
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
                                        <span class="booking-service-selectable {{ $checked ? 'booking-service--selected' : '' }} flex w-full items-center gap-3 rounded-[20px] p-3 transition">
                                            <span class="h-14 w-14 shrink-0 overflow-hidden rounded-2xl border border-white/10 bg-[var(--brand-surface)]">
                                                @if ($service->image_url)
                                                    <img src="{{ $service->image_url }}" alt="Imagem de {{ $service->name }}" class="h-full w-full object-cover">
                                                @else
                                                    <span class="flex h-full w-full items-center justify-center text-[var(--brand-primary)]">
                                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2 1.586-1.586a2 2 0 012.828 0L20 14m-9-5h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </span>
                                                @endif
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-semibold text-white">{{ $service->name }}</span>
                                                @if (filled($service->description))
                                                    <span class="mt-0.5 block line-clamp-2 text-[11px] leading-snug brand-muted opacity-90">{{ $service->description }}</span>
                                                @endif
                                                <span class="mt-1 flex flex-wrap gap-2 text-xs brand-muted">
                                                    <span>{{ $service->duration_minutes }} min</span>
                                                    <span class="font-semibold text-[var(--brand-primary)]">R$ {{ number_format((float) $service->price, 2, ',', '.') }}</span>
                                                </span>
                                            </span>
                                            <span class="{{ $checked ? 'bg-[var(--brand-primary)] text-[var(--brand-on-primary)]' : 'bg-[var(--brand-surface)] brand-muted' }} rounded-full px-3 py-2 text-xs font-semibold">
                                                {{ $checked ? 'Remover' : 'Selecionar' }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach

                                <div x-show="filteredServices().length === 0" x-cloak class="rounded-2xl border border-dashed border-[color:color-mix(in_srgb,var(--brand-primary)_22%,transparent)] bg-[var(--brand-surface)] px-4 py-6 text-center text-sm brand-muted">
                                    Nenhum serviço encontrado.
                                </div>
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('service_ids')" />
                            <x-input-error class="mt-2" :messages="$errors->get('service_ids.*')" />
                        </section>

                        <section class="sf-card p-4 sm:p-5" :class="!hasSelectedServices() ? 'opacity-70' : ''">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">2</span>
                                <div>
                                    <h2 class="sf-section-title text-white">Escolha o profissional</h2>
                                    <p class="mt-1 text-sm brand-muted">A relação serviço/profissional ainda não existe; por enquanto todos os profissionais ativos aparecem.</p>
                                </div>
                            </div>

                            <div x-show="!hasSelectedServices()" class="mt-4 rounded-2xl border border-dashed border-[color:color-mix(in_srgb,var(--brand-primary)_22%,transparent)] bg-[var(--brand-surface)] px-4 py-4 text-sm brand-muted">
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
                                        <span class="{{ $selected ? 'border-[var(--brand-primary)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] ring-2 ring-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)]' : 'border-[color:color-mix(in_srgb,white_10%,transparent)] bg-[var(--brand-surface)] hover:border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)]' }} flex items-center justify-between gap-3 rounded-[20px] border p-3 transition">
                                            <span class="flex min-w-0 items-center gap-3">
                                                @if ($user->photo_url)
                                                    <img src="{{ $user->photo_url }}" alt="Foto de {{ $user->name }}" class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-white/10">
                                                @else
                                                    <span class="{{ $selected ? 'bg-[var(--brand-primary)] text-[var(--brand-on-primary)]' : 'bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-[var(--brand-primary)]' }} flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-base font-semibold">
                                                        {{ $user->avatar_initial }}
                                                    </span>
                                                @endif
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                                                    <span class="mt-1 block text-xs brand-muted">Disponível para seleção</span>
                                                </span>
                                            </span>
                                            @if ($selected)
                                                <span class="rounded-full bg-[var(--brand-primary)] px-3 py-1 text-xs font-semibold text-[var(--brand-on-primary)]">Selecionado</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('user_id')" />
                        </section>

                        <section class="sf-card p-4 sm:p-5" :class="!hasSelectedServices() || !hasProfessional ? 'opacity-70' : ''">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">3</span>
                                <div>
                                    <h2 class="sf-section-title text-white">Escolha a data</h2>
                                    <p class="mt-1 text-sm brand-muted">Datas passadas ficam bloqueadas pelo calendário.</p>
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
                                        class="{{ $selected ? 'booking-date-tile booking-date-tile--on' : 'booking-date-tile brand-muted' }} px-2 py-3 text-center transition"
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
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">4</span>
                                <div>
                                    <h2 class="sf-section-title text-white">Escolha o horário</h2>
                                    <p class="mt-1 text-sm brand-muted">Horários são calculados pela duração total dos serviços.</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                @if ($selectedServiceIds->isEmpty() || ! $selectedUser)
                                    <div class="rounded-2xl border border-dashed border-[color:color-mix(in_srgb,var(--brand-primary)_22%,transparent)] bg-[var(--brand-surface)] px-4 py-5 text-sm brand-muted">
                                        Para carregar os horários da agenda real, selecione pelo menos um serviço e um profissional.
                                        @if ($selectedUser && $selectedServiceIds->isEmpty())
                                            O profissional {{ $selectedUser->name }} já está selecionado; falta escolher o serviço.
                                        @endif
                                    </div>
                                @elseif ($slotPeriods->isEmpty())
                                    <div class="rounded-2xl border border-dashed border-[color:color-mix(in_srgb,var(--brand-primary)_22%,transparent)] bg-[var(--brand-surface)] px-4 py-5 text-sm brand-muted">
                                        Nenhum horário disponível para esta data.
                                    </div>
                                @else
                                    <div class="space-y-4">
                                        @if ($nextAvailableSlot)
                                            <div class="flex flex-col gap-3 rounded-[20px] border border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] p-4 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--brand-primary)]">Pr&oacute;ximo hor&aacute;rio dispon&iacute;vel</p>
                                                    <p class="mt-1 text-2xl font-semibold text-white">{{ $nextAvailableSlot }}</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="brand-cta px-4 py-3 text-sm"
                                                    @click="selectedTime = @js($nextAvailableSlot)"
                                                >
                                                    Usar este hor&aacute;rio
                                                </button>
                                            </div>
                                        @endif

                                        <div class="rounded-[20px] border border-[color:color-mix(in_srgb,var(--brand-primary)_14%,transparent)] bg-[color-mix(in_srgb,var(--brand-surface)_92%,var(--brand-primary)_8%)] p-4">
                                            <label for="booking-time" class="text-sm font-medium text-white">Hor&aacute;rio do atendimento</label>
                                            <select id="booking-time" name="time" x-model="selectedTime" class="sf-select mt-2 block w-full @error('time') ring-2 ring-rose-500 ring-offset-2 ring-offset-[var(--brand-ring-offset)] @enderror" required>
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
                                                    @php
                                                        $slotChipOn = old('time', $selectedTime) === $slotOption['time'];
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        class="{{ $slotChipOn ? 'booking-pill-slot booking-pill-slot--on' : 'booking-pill-slot' }} px-3 py-2 text-xs font-semibold text-white transition"
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
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">5</span>
                                <div>
                                    <h2 class="sf-section-title text-white">Dados do cliente</h2>
                                    <p class="mt-1 text-sm brand-muted">Use Google se estiver disponível ou preencha manualmente.</p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-4">
                                @if ($identifiedClient)
                                    <div class="rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_30%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)] px-4 py-4">
                                        <p class="text-sm font-semibold text-white">Cliente identificado</p>
                                        <p class="mt-1 text-sm brand-muted">{{ $identifiedClient->name }}{{ $identifiedClient->email ? ' · '.$identifiedClient->email : '' }}</p>
                                    </div>
                                @elseif ($googleConfigured)
                                    <a href="{{ route('public-bookings.google.redirect', ['company' => $company, ...$bookingQuery]) }}" class="flex w-full items-center justify-center gap-3 rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_22%,transparent)] bg-white px-4 py-4 text-sm font-semibold text-[var(--brand-on-primary)] transition hover:bg-[color-mix(in_srgb,white_92%,var(--brand-primary)_8%)]">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#4285f4] text-xs font-bold text-white">G</span>
                                        Continuar com Google
                                    </a>
                                    <div class="flex items-center gap-3 text-xs uppercase tracking-[0.18em] brand-muted">
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
                                            :class="{ 'ring-2 ring-amber-400 ring-offset-2 ring-offset-[var(--brand-ring-offset)]': clientNameFieldHint() !== null && ! @js($errors->has('client_name')) }"
                                            class="sf-input mt-2 block w-full @error('client_name') ring-2 ring-rose-500 ring-offset-2 ring-offset-[var(--brand-ring-offset)] @enderror"
                                            required
                                            minlength="3"
                                        >
                                        <p class="mt-1 text-xs text-amber-100/90" x-show="clientNameFieldHint()" x-cloak x-text="clientNameFieldHint()"></p>
                                        <p class="mt-1 text-xs brand-muted" x-show="clientNameFieldHint() === null" x-cloak>Exemplo: Ana Paula Souza · nome e sobrenome com pelo menos 2 letras.</p>
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
                                                class="sf-input mt-2 block w-full @error('client_phone') ring-2 ring-rose-500 ring-offset-2 ring-offset-[var(--brand-ring-offset)] @enderror"
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
                                                class="sf-input mt-2 block w-full @error('client_email') ring-2 ring-rose-500 ring-offset-2 ring-offset-[var(--brand-ring-offset)] @enderror"
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
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">6</span>
                                <div>
                                    <h2 class="sf-section-title text-white">Confirmação</h2>
                                    <p class="mt-1 text-sm brand-muted">O horário será confirmado após o envio. A disponibilidade será validada novamente.</p>
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="brand-cta mt-5 w-full min-h-[60px] text-base transition"
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
                                    <p x-show="!blockedByIncompleteFullNameOnly()" x-cloak class="text-sm brand-muted">
                                        Complete as etapas anteriores para confirmar.
                                    </p>
                                </div>
                            </template>
                        </section>
                    </form>
                </section>

                <aside class="hidden xl:block">
                    <div class="sticky top-5 space-y-4">
                        <section class="booking-summary-panel overflow-hidden">
                            <div class="booking-summary-head px-5 py-5">
                                <h3 class="sf-section-title text-white">Resumo do agendamento</h3>
                                <p class="mt-1 text-sm brand-muted">Acompanhe tudo antes de confirmar.</p>
                            </div>

                            <div class="space-y-4 px-5 py-5">
                                <template x-if="hasSelectedServices()">
                                    <div class="space-y-3">
                                        <template x-for="service in selectedServices()" :key="service.id">
                                            <div class="booking-summary-row px-4 py-3">
                                                <div class="flex items-center justify-between gap-4">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-semibold text-white" x-text="service.name"></p>
                                                        <p class="mt-1 text-xs brand-muted" x-text="service.duration + ' min'"></p>
                                                    </div>
                                                    <p class="text-sm font-semibold text-[var(--brand-primary)]" x-text="'R$ ' + service.price"></p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="!hasSelectedServices()">
                                    <div class="booking-summary-row px-4 py-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Serviços</p>
                                        <p class="mt-2 text-base font-semibold text-white">Escolha os serviços</p>
                                    </div>
                                </template>

                                <div class="booking-summary-row px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Profissional</p>
                                    <p class="mt-2 text-base font-semibold text-white">{{ $selectedUser?->name ?? '-' }}</p>
                                </div>

                                <div class="booking-summary-row px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Data e horário</p>
                                    <p class="mt-2 text-base font-semibold text-white">{{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}</p>
                                    <p class="mt-1 text-sm brand-muted" x-text="selectedTime || 'Selecione um horário'">{{ old('time', $selectedTime) ?: 'Selecione um horário' }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="booking-summary-row px-4 py-4">
                                        <p class="text-xs brand-muted">Duração</p>
                                        <p class="mt-1 text-lg font-semibold text-white" x-text="hasSelectedServices() ? totalDuration() + ' min' : '0 min'">{{ $usingEstimatedDuration ? '0 min' : $totalDurationMinutes . ' min' }}</p>
                                    </div>
                                    <div class="booking-summary-row px-4 py-4">
                                        <p class="text-xs brand-muted">Total</p>
                                        <p class="mt-1 text-lg booking-summary-total" x-text="hasSelectedServices() ? 'R$ ' + formattedTotalPrice() : 'A definir'">{{ $usingEstimatedDuration ? 'A definir' : 'R$ ' . number_format($totalPrice, 2, ',', '.') }}</p>
                                    </div>
                                </div>

                                @unless ($identifiedClient)
                                    <p x-show="clientNameTokens().length && !fullNameStrongOk()" x-cloak class="rounded-2xl border border-amber-500/35 bg-amber-500/10 px-4 py-2 text-xs text-amber-50">
                                        Nome incompleto: informe também o sobrenome (mínimo 2 letras em cada parte).
                                    </p>
                                @endunless
                                <div class="booking-summary-row px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Cliente</p>
                                    <p class="mt-2 text-base font-semibold text-white" x-text="clientName || 'Preencha seus dados'">{{ $identifiedClient?->name ?? 'Preencha seus dados' }}</p>
                                    <p class="mt-1 text-sm brand-muted" x-text="clientPhone || clientEmail || '-'">{{ $identifiedClient?->email ?? '-' }}</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        </main>

        <div class="booking-sticky-bar fixed inset-x-0 bottom-0 z-30 px-4 py-3 backdrop-blur xl:hidden">
            <div class="mx-auto flex w-full max-w-full items-center justify-between gap-3 sm:max-w-none lg:max-w-7xl">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white">
                        {{ $selectedServiceIds->isNotEmpty() ? $selectedServiceIds->count().' serviço(s) · '.$totalDurationMinutes.' min' : 'Escolha os serviços' }}
                    </p>
                    <p class="mt-1 text-xs brand-muted">
                        {{ $selectedServiceIds->isNotEmpty() ? 'R$ '.number_format($totalPrice, 2, ',', '.') : 'Total a definir' }}
                    </p>
                </div>
                <a href="#booking-filters" class="brand-cta shrink-0 px-4 py-3 text-sm">
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
