@php
    $selectedServiceIdStrings = $selectedServiceIds->map(fn ($id) => (string) $id)->all();
    $today = \Carbon\CarbonImmutable::today()->toDateString();
    $availableSlotOptions = collect($slotOptions ?? [])
        ->where('available', true)
        ->map(fn (array $slot): array => [
            'time' => $slot['time'],
            'available' => true,
            'public_label' => $slot['public_label'] ?? null,
        ])
        ->values();
    $slotPeriods = $availableSlotOptions->groupBy(function (array $slot): string {
        $hour = (int) substr($slot['time'], 0, 2);

        return match (true) {
            $hour < 12 => 'Manha',
            $hour < 18 => 'Tarde',
            default => 'Noite',
        };
    });
    $nextAvailableSlot = $availableSlotOptions->first()['time'] ?? null;
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
    $requestedVisualStep = request('visual_step');
    $validBookingSteps = ['services', 'professionals', 'date', 'time', 'data', 'confirm'];
    $initialBookingStep = match (true) {
        $errors->has('client_name') || $errors->has('client_phone') || $errors->has('client_email') || $errors->has('notes') || $errors->has('payment_choice') => 'data',
        $errors->has('time') => 'time',
        $errors->has('date') => 'date',
        ! $hasBookingPro => 'professionals',
        ! $hasBookingServices => 'services',
        ! $hasBookingDate => 'date',
        ! $hasBookingTime => 'date',
        ! $hasBookingClient => 'data',
        default => 'confirm',
    };
    if (in_array($requestedVisualStep, $validBookingSteps, true)) {
        $initialBookingStep = match ($requestedVisualStep) {
            'professionals' => 'professionals',
            'date' => $hasBookingServices && $hasBookingPro ? 'date' : ($hasBookingServices ? 'professionals' : 'services'),
            'time' => $hasBookingServices && $hasBookingPro && $hasBookingDate ? 'time' : ($hasBookingServices && $hasBookingPro ? 'date' : ($hasBookingServices ? 'professionals' : 'services')),
            'data' => $hasBookingServices && $hasBookingPro && $hasBookingDate && $hasBookingTime ? 'data' : $initialBookingStep,
            'confirm' => $hasBookingServices && $hasBookingPro && $hasBookingDate && $hasBookingTime && $hasBookingClient ? 'confirm' : $initialBookingStep,
            default => 'services',
        };
    }
    $initialAssistantStep = $initialBookingStep;
    $brandingService = app(\App\Services\BrandingService::class);
    $bookingPrimaryRaw = $brandingService->sanitizeColors($company->primary_color ?? null)
        ?? $brandingService->sanitizeColors($publicBranding['primary'] ?? null)
        ?? '#0F3D3A';
    $bookingSecondary = $brandingService->sanitizeColors($company->secondary_color ?? null)
        ?? $brandingService->sanitizeColors($publicBranding['secondary'] ?? null)
        ?? '#0F172A';
    $bookingAccent = $brandingService->sanitizeColors($company->accent_color ?? null)
        ?? $brandingService->sanitizeColors($publicBranding['accent'] ?? null)
        ?? $bookingPrimaryRaw;
    $bookingPrimaryAction = $brandingService->relativeLuminance($bookingPrimaryRaw) > 0.58
        ? $brandingService->mixColor($bookingPrimaryRaw, '#000000', 36)
        : $bookingPrimaryRaw;
    $bookingPrimaryDark = $brandingService->mixColor($bookingPrimaryAction, '#000000', 28);
    $bookingPrimarySoft = $brandingService->mixColor($bookingPrimaryAction, '#FFFFFF', 88);
    $bookingOnPrimary = $brandingService->contrastingForegroundOnPrimary($bookingPrimaryAction);
    $bookingHeroText = $brandingService->readableTextColor($bookingSecondary);
    $bookingSurface = 'color-mix(in srgb, var(--booking-secondary) 88%, white 12%)';
    $bookingCardBase = 'color-mix(in srgb, var(--booking-secondary) 82%, white 18%)';
    $bookingCardSoft = 'color-mix(in srgb, var(--booking-secondary) 74%, white 26%)';
    $bookingText = '#FFFFFF';
    $bookingMuted = 'rgba(255, 255, 255, 0.68)';
    $bookingBorder = 'rgba(255, 255, 255, 0.14)';
    $bookingThemeStyle = collect([
        '--booking-primary' => $bookingPrimaryRaw,
        '--booking-primary-action' => $bookingPrimaryAction,
        '--booking-primary-dark' => $bookingPrimaryDark,
        '--booking-primary-soft' => $bookingPrimarySoft,
        '--booking-secondary' => $bookingSecondary,
        '--booking-accent' => $bookingAccent,
        '--company-primary' => $bookingPrimaryRaw,
        '--company-secondary' => $bookingSecondary,
        '--company-accent' => $bookingAccent,
        '--booking-bg' => 'var(--booking-secondary)',
        '--booking-surface' => $bookingSurface,
        '--booking-card-base' => $bookingCardBase,
        '--booking-card' => $bookingCardBase,
        '--booking-card-soft' => $bookingCardSoft,
        '--booking-card-strong' => $bookingCardSoft,
        '--booking-text' => $bookingText,
        '--booking-muted' => $bookingMuted,
        '--booking-border' => $bookingBorder,
        '--booking-on-primary' => $bookingOnPrimary,
        '--booking-hero-text' => $bookingHeroText,
        '--brand-primary' => 'var(--booking-primary-action)',
        '--brand-on-primary' => 'var(--booking-on-primary)',
        '--btn-primary-bg' => 'var(--booking-primary-action)',
        '--btn-primary-text' => 'var(--booking-on-primary)',
    ])->map(fn ($value, $key) => $key.': '.$value)->implode('; ');
    $publicDescription = $publicBranding['hero_subtitle'] ?? ($publicBranding['description_fallback'] ?: $company->safeDescription());
    $configuredBookingCoverImages = $company->bookingCoverImages()
        ->get()
        ->map(fn ($media) => $media->url)
        ->filter(fn ($image) => filled($image))
        ->values();
    $bookingCoverImages = ($configuredBookingCoverImages->isNotEmpty() ? $configuredBookingCoverImages : collect([$publicBranding['cover_url'] ?? null]))
        ->filter(fn ($image) => filled($image))
        ->unique()
        ->take(5)
        ->values();
    $weekdayNames = [0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sab'];
    $weeklyHours = $users
        ->flatMap(fn ($user) => $user->workingHours)
        ->groupBy('weekday')
        ->map(function ($hours, $weekday) use ($weekdayNames) {
            return [
                'day' => $weekdayNames[(int) $weekday] ?? (string) $weekday,
                'intervals' => $hours
                    ->map(fn ($hour) => substr((string) $hour->start_time, 0, 5).' - '.substr((string) $hour->end_time, 0, 5))
                    ->unique()
                    ->values(),
            ];
        })
        ->sortKeys();
    $hasAboutDetails = filled($company->safeDescription())
        || filled($publicBranding['welcome_message'] ?? null)
        || filled($company->address)
        || filled($company->phone)
        || filled($company->instagram)
        || $weeklyHours->isNotEmpty();
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
    <body class="public-booking-page public-booking-v3 min-h-screen font-sans text-white antialiased" style="{{ $bookingThemeStyle }}">
        <main class="public-booking-app mx-auto min-h-screen w-full max-w-full overflow-hidden px-0 pb-28 lg:pb-10" style="{{ $bookingThemeStyle }}">
            <div
                x-data="{
                    selectedServiceIds: @js($selectedServiceIdStrings),
                    selectedProfessionalId: @js($selectedUserId ? (string) $selectedUserId : null),
                    serviceSearch: '',
                    selectedDate: @js($selectedDate),
                    selectedTime: @js(old('time', $selectedTime)),
                    paymentChoice: @js($selectedPaymentChoice),
                    currentStep: @js($initialAssistantStep),
                    stepOrder: ['services', 'professionals', 'date', 'time', 'data', 'confirm'],
                    bookingActiveTabStyle: @js('background-color: '.$bookingPrimaryAction.'; color: '.$bookingOnPrimary.'; box-shadow: inset 0 0 0 999px '.$bookingPrimaryAction.';'),
                    hasProfessional: @js((bool) $selectedUser),
                    clientName: @js(old('client_name', $identifiedClient?->name)),
                    clientPhone: @js(old('client_phone', $identifiedClient?->phone)),
                    clientEmail: @js(old('client_email', $identifiedClient?->email)),
                    catalog: @js($servicesCatalog),
                    categories: ['Todos', 'Serviços'],
                    selectedCategory: 'Todos',
                    slotOptions: @js($availableSlotOptions),
                    loadingTimes: false,
                    slotsLoaded: @js(! empty($slotOptions ?? [])),
                    slotsError: null,
                    slotsEndpoint: @js(route('public-bookings.slots', $company)),
                    visibleSlotLimits: { Manha: 8, Tarde: 8, Noite: 8 },
                    showMoreSlots(period) {
                        this.visibleSlotLimits[period] = (this.visibleSlotLimits[period] || 8) + 8;
                    },
                    markSlotsDirty() {
                        this.selectedTime = '';
                        this.slotOptions = [];
                        this.slotsLoaded = false;
                        this.slotsError = null;
                    },
                    selectService(serviceId) {
                        const normalized = String(serviceId);
                        if (this.selectedServiceIds.includes(normalized)) {
                            this.selectedServiceIds = this.selectedServiceIds.filter((id) => id !== normalized);
                        } else {
                            this.selectedServiceIds.push(normalized);
                        }
                        this.markSlotsDirty();
                    },
                    selectProfessional(userId) {
                        this.selectedProfessionalId = String(userId);
                        this.hasProfessional = true;
                        this.markSlotsDirty();
                    },
                    selectDate(date) {
                        this.selectedDate = date;
                        this.markSlotsDirty();
                    },
                    visualStepAfterFilter() {
                        return this.currentStep;
                    },
                    preserveScroll(callback) {
                        const currentY = window.scrollY;
                        callback();
                        this.$nextTick(() => window.scrollTo({ top: currentY, left: 0, behavior: 'auto' }));
                    },
                    scrollWizardTop() {
                        document.querySelector('.public-booking-shell')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    },
                    tabIsActive(tab) {
                        return tab === 'date' ? ['date', 'time'].includes(this.currentStep) : this.currentStep === tab;
                    },
                    canOpenStep(step) {
                        if (step === 'services') {
                            return true;
                        }
                        if (step === 'professionals') {
                            return true;
                        }
                        if (step === 'date') {
                            return this.hasSelectedServices() && this.hasProfessional;
                        }
                        if (step === 'time') {
                            return this.readyForSlots();
                        }
                        if (step === 'data') {
                            return this.readyForSlots() && Boolean(this.selectedTime);
                        }
                        if (step === 'confirm') {
                            return this.readyToConfirm();
                        }

                        return false;
                    },
                    goToStep(step) {
                        if (!this.canOpenStep(step)) {
                            return;
                        }
                        this.currentStep = step;
                        this.afterStepChange();
                    },
                    previousStep() {
                        const previous = {
                            services: 'professionals',
                            professionals: 'professionals',
                            date: this.hasProfessional ? 'professionals' : 'services',
                            time: 'date',
                            data: 'time',
                            confirm: 'data',
                        }[this.currentStep] || 'professionals';

                        this.currentStep = previous;
                        this.afterStepChange();
                    },
                    canContinueCurrentStep() {
                        if (this.currentStep === 'services') {
                            return this.hasSelectedServices();
                        }
                        if (this.currentStep === 'professionals') {
                            return this.hasProfessional;
                        }
                        if (this.currentStep === 'date') {
                            return this.readyForSlots();
                        }
                        if (this.currentStep === 'time') {
                            return Boolean(this.selectedTime);
                        }
                        if (this.currentStep === 'data') {
                            return this.readyToConfirm();
                        }

                        return false;
                    },
                    continueLabel() {
                        if (this.currentStep === 'data') {
                            return 'Revisar agendamento';
                        }

                        return 'Continuar';
                    },
                    continueWizard() {
                        if (!this.canContinueCurrentStep()) {
                            if (this.currentStep === 'data' && this.blockedByIncompleteFullNameOnly()) {
                                this.focusClientNameField();
                            }
                            return;
                        }
                        const next = {
                            services: this.hasProfessional ? 'date' : 'professionals',
                            professionals: 'services',
                            date: 'time',
                            time: 'data',
                            data: 'confirm',
                        }[this.currentStep];
                        if (!next) {
                            return;
                        }
                        this.currentStep = next;
                        this.afterStepChange();
                    },
                    afterStepChange() {
                        this.$nextTick(() => {
                            this.scrollWizardTop();
                            this.scrollActiveTabIntoView();
                        });
                        if (this.currentStep === 'time') {
                            this.loadAvailableTimes();
                        }
                    },
                    scrollActiveTabIntoView() {
                        const visibleTabs = Array.from(document.querySelectorAll('.booking-browser-tabs'))
                            .find((tabs) => tabs.offsetParent !== null);
                        const activeTab = visibleTabs?.querySelector('.booking-browser-tab.is-active');

                        if (!visibleTabs || !activeTab) {
                            return;
                        }

                        activeTab.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest',
                            inline: 'center',
                        });

                        window.requestAnimationFrame(() => {
                            const activeRect = activeTab.getBoundingClientRect();
                            const tabsRect = visibleTabs.getBoundingClientRect();
                            const rightOverflow = activeRect.right - (tabsRect.right - 12);
                            const leftOverflow = (tabsRect.left + 12) - activeRect.left;

                            if (rightOverflow > 0) {
                                visibleTabs.scrollLeft += rightOverflow;
                            } else if (leftOverflow > 0) {
                                visibleTabs.scrollLeft -= leftOverflow;
                            }
                        });
                    },
                    stepIsComplete(step) {
                        if (step === 'services') {
                            return this.hasSelectedServices();
                        }
                        if (step === 'professionals') {
                            return this.hasProfessional;
                        }
                        if (step === 'date') {
                            return this.readyForSlots() && Boolean(this.selectedTime);
                        }
                        if (step === 'time') {
                            return this.readyForSlots() && Boolean(this.selectedTime);
                        }
                        if (step === 'data') {
                            return this.readyToConfirm();
                        }

                        return false;
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
                        return this.hasSelectedServices() && Boolean(this.selectedProfessionalId) && this.selectedDate;
                    },
                    readyToConfirm() {
                        if (! this.readyForSlots() || ! this.selectedTime) {
                            return false;
                        }
                        if (@js($bookingPaymentRequirement === 'optional' && $canOfferOnlineBookingPayment) && !this.paymentChoice) {
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
                    },
                    selectedProfessionalName() {
                        const option = document.querySelector(`input[name='user_id'][value='${this.selectedProfessionalId}']`);

                        return option?.dataset?.professionalName || '-';
                    },
                    availableSlots() {
                        return this.slotOptions.filter((slot) => slot.available);
                    },
                    slotPeriods() {
                        return this.availableSlots().reduce((periods, slot) => {
                            const hour = Number(String(slot.time).slice(0, 2));
                            const period = hour < 12 ? 'Manha' : (hour < 18 ? 'Tarde' : 'Noite');
                            periods[period] = periods[period] || [];
                            periods[period].push(slot);

                            return periods;
                        }, {});
                    },
                    periodLabel(period) {
                        return period === 'Manha' ? 'Manhã' : period;
                    },
                    nextAvailableSlot() {
                        return this.availableSlots()[0]?.time || null;
                    },
                    async loadAvailableTimes() {
                        if (!this.readyForSlots()) {
                            return;
                        }
                        if (this.slotsLoaded) {
                            return;
                        }

                        this.loadingTimes = true;
                        this.slotsError = null;

                        const params = new URLSearchParams();
                        this.selectedServiceIds.forEach((id) => params.append('service_ids[]', id));
                        params.set('user_id', this.selectedProfessionalId);
                        params.set('date', this.selectedDate);

                        try {
                            const response = await fetch(`${this.slotsEndpoint}?${params.toString()}`, {
                                headers: { 'Accept': 'application/json' },
                            });

                            if (!response.ok) {
                                throw new Error('Falha ao carregar horarios.');
                            }

                            const payload = await response.json();
                            this.slotOptions = Array.isArray(payload.slots) ? payload.slots : [];
                            this.slotsLoaded = true;
                        } catch (error) {
                            this.slotOptions = [];
                            this.slotsError = 'Não foi possível carregar os horários agora. Tente novamente.';
                        } finally {
                            this.loadingTimes = false;
                        }
                    }
                }"
                class="public-booking-shell grid min-w-0 w-full max-w-full gap-5"
            >
                <section class="min-w-0 space-y-0 lg:space-y-5">
                    <header class="booking-hero booking-profile-header">
                        @if ($bookingCoverImages->isNotEmpty())
                            <div class="booking-hero-slider {{ $bookingCoverImages->count() > 1 ? 'has-carousel' : '' }}" style="--slide-count: {{ max(1, $bookingCoverImages->count()) }};">
                                @foreach ($bookingCoverImages as $coverIndex => $coverImage)
                                    <div
                                        class="booking-hero-slide {{ $coverIndex === 0 ? 'active' : '' }}"
                                        role="img"
                                        aria-label="Imagem de capa de {{ $company->name }}"
                                        style="--slide-index: {{ $coverIndex }}; background-image: url('{{ $coverImage }}');"
                                    ></div>
                                @endforeach
                            </div>
                        @endif

                        <div class="booking-hero-overlay"></div>

                        <div class="booking-hero-content flex min-w-0 items-start gap-4">
                            @if (! empty($publicBranding['logo_url']))
                                <img src="{{ $publicBranding['logo_url'] }}" alt="Logo de {{ $company->name }}" class="h-14 w-14 rounded-2xl object-cover ring-1 ring-white/10" loading="lazy" decoding="async">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--brand-primary)]/12 text-[var(--brand-primary)] ring-1 ring-white/10">
                                    <x-application-logo class="h-7 w-7" />
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="inline-flex max-w-full items-center rounded-full border border-[color:color-mix(in_srgb,var(--brand-primary)_35%,transparent)] bg-[color:color-mix(in_srgb,var(--brand-primary)_12%,transparent)] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)] shadow-[var(--shadow-soft)]">
                                    Agendamento · {{ $company->name }}
                                </div>
                                <h1 class="sf-page-title mt-3 text-white sm:text-3xl">
                                    {{ $publicBranding['hero_title'] ?? $company->name }}
                                </h1>
                                @if (filled($publicDescription))
                                    <p class="sf-page-subtitle mt-2 max-w-2xl brand-muted">
                                        {{ $publicDescription }}
                                    </p>
                                @endif
                                @if (! empty($publicBranding['welcome_message']))
                                    <p class="mt-3 max-w-2xl rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_15%,transparent)] bg-[color:color-mix(in_srgb,var(--brand-accent)_55%,transparent)] px-3 py-2 text-sm leading-6 brand-muted">
                                        {{ $publicBranding['welcome_message'] }}
                                    </p>
                                @endif
                                @if ($company->instagram)
                                    <p class="mt-2 text-sm font-semibold text-[var(--brand-primary)]">{{ $company->instagram }}</p>
                                @endif
                                @if (($reviewSummary['count'] ?? 0) > 0)
                                    <div class="booking-rating-badge">
                                        <span>★</span>
                                        <strong>{{ number_format((float) $reviewSummary['avg_rating'], 1, ',', '.') }}</strong>
                                        <span>{{ $reviewSummary['count'] }} avaliações</span>
                                    </div>
                                @endif
                                <div class="booking-profile-chips">
                                    @if ($company->instagram)
                                        <span>{{ $company->instagram }}</span>
                                    @endif
                                    @if (filled($company->phone))
                                        <span>{{ $company->phone }}</span>
                                    @endif
                                    @if (filled($company->address))
                                        <span>{{ \Illuminate\Support\Str::limit($company->address, 34) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 grid min-w-0 grid-cols-3 gap-2 text-center text-[11px] font-semibold uppercase tracking-[0.12em] brand-muted sm:grid-cols-6">
                            @foreach (['Serviços', 'Profissional', 'Data', 'Horário', 'Dados', 'Confirmar'] as $index => $step)
                                @php
                                    $stepDone = $bookingStepDone[$index] ?? false;
                                    $stepActive = $index === $bookingActiveStep;
                                    $stepTone = $stepDone ? 'booking-step--complete' : ($stepActive ? 'booking-step--active' : 'booking-step--pending');
                                @endphp
                                <div class="booking-step min-w-0 rounded-2xl px-2 py-3 {{ $stepTone }}">
                                    <span class="booking-step-num block font-semibold">{{ $index + 1 }}</span>
                                    <span class="mt-1 block">{{ $step }}</span>
                                </div>
                            @endforeach
                        </div>
                    </header>

                    <form id="booking-filters" x-ref="bookingFilters" class="booking-app-main-card mx-3 -mt-8 w-auto max-w-full space-y-5 p-4 sm:mx-5 sm:p-5 lg:mx-0 lg:p-6" x-show="['professionals', 'services', 'date'].includes(currentStep)" x-cloak @submit.prevent>
                        @if (false)
                        <section class="booking-assistant-home" x-show="currentStep === 'overview'" x-cloak>
                            <div class="booking-assistant-intro">
                                <p class="sf-page-eyebrow">Agendamento online</p>
                                <h2>Selecione os detalhes do seu agendamento</h2>
                            </div>

                            <div class="booking-assistant-list">
                                <button type="button" class="booking-assistant-row" @click="goToStep('professionals')">
                                    <span class="booking-assistant-icon">1</span>
                                    <span class="min-w-0 flex-1">
                                        <strong>Selecione um profissional</strong>
                                        <small x-text="selectedProfessionalId ? selectedProfessionalName() : 'Escolha quem vai atender'">{{ $selectedUser?->name ?? 'Escolha quem vai atender' }}</small>
                                    </span>
                                    <span class="booking-assistant-status" :class="hasProfessional ? 'is-done' : ''" x-text="hasProfessional ? 'Concluido' : 'Pendente'"></span>
                                </button>

                                <button type="button" class="booking-assistant-row" @click="goToStep('services')">
                                    <span class="booking-assistant-icon">2</span>
                                    <span class="min-w-0 flex-1">
                                        <strong>Selecione os serviços</strong>
                                        <small x-text="hasSelectedServices() ? `${selectedServiceIds.length} serviço(s) selecionado(s)` : 'Escolha um ou mais serviços'"></small>
                                    </span>
                                    <span class="booking-assistant-status" :class="hasSelectedServices() ? 'is-done' : ''" x-text="hasSelectedServices() ? 'Concluido' : 'Pendente'"></span>
                                </button>

                                <button type="button" class="booking-assistant-row" :class="{ 'is-locked': !canOpenStep('date') }" :disabled="!canOpenStep('date')" @click="goToStep(selectedTime ? 'time' : 'date')">
                                    <span class="booking-assistant-icon">3</span>
                                    <span class="min-w-0 flex-1">
                                        <strong>Selecione data e horario</strong>
                                        <small x-text="selectedTime ? `${selectedDate} - ${selectedTime}` : 'Veja os horarios disponiveis'"></small>
                                    </span>
                                    <span class="booking-assistant-status" :class="selectedTime ? 'is-done' : ''" x-text="selectedTime ? 'Concluido' : 'Pendente'"></span>
                                </button>

                                <button type="button" class="booking-assistant-row" :class="{ 'is-locked': !canOpenStep('data') }" :disabled="!canOpenStep('data')" @click="goToStep('data')">
                                    <span class="booking-assistant-icon">4</span>
                                    <span class="min-w-0 flex-1">
                                        <strong>Informe seus dados</strong>
                                        <small x-text="readyToConfirm() ? 'Dados preenchidos' : 'Nome, WhatsApp e contato'"></small>
                                    </span>
                                    <span class="booking-assistant-status" :class="readyToConfirm() ? 'is-done' : ''" x-text="readyToConfirm() ? 'Concluido' : 'Pendente'"></span>
                                </button>
                            </div>
                        </section>
                        @endif

                        <div class="booking-stage-head hidden" x-show="false" x-cloak>
                            <button type="button" class="booking-back-button" @click="previousStep()">Voltar</button>
                            <span x-show="currentStep === 'professionals'">Profissional</span>
                            <span x-show="currentStep === 'services'">Serviços</span>
                            <span x-show="currentStep === 'date' || currentStep === 'time'">Data e horario</span>
                            <span x-show="currentStep === 'data'">Dados do cliente</span>
                            <span x-show="currentStep === 'confirm'">Confirmacao</span>
                        </div>

                        <div class="booking-browser-tabs-wrap">
                            <nav class="booking-browser-tabs" aria-label="Navegacao do agendamento">
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('professionals'), 'is-complete': stepIsComplete('professionals'), 'is-disabled': !canOpenStep('professionals') }" :disabled="!canOpenStep('professionals')" @click="goToStep('professionals')">
                                    <span>Profissional</span>
                                </button>
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('services'), 'is-complete': stepIsComplete('services'), 'is-disabled': !canOpenStep('services') }" :disabled="!canOpenStep('services')" @click="goToStep('services')">
                                    <span>Servi&ccedil;os</span>
                                </button>
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('date'), 'is-complete': stepIsComplete('time'), 'is-disabled': !canOpenStep('date') }" :disabled="!canOpenStep('date')" @click="goToStep(selectedTime ? 'time' : 'date')">
                                    <span>Data</span>
                                </button>
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('data'), 'is-complete': stepIsComplete('data'), 'is-disabled': !canOpenStep('data') }" :disabled="!canOpenStep('data')" @click="goToStep('data')">
                                    <span>Dados</span>
                                </button>
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('confirm'), 'is-complete': stepIsComplete('confirm'), 'is-disabled': !canOpenStep('confirm') }" :disabled="!canOpenStep('confirm')" @click="goToStep('confirm')">
                                    <span>Confirmar</span>
                                </button>
                            </nav>
                        </div>

                        @if (false)
                        <nav class="booking-app-tabs" aria-label="Navegacao do agendamento antigo" hidden>
                            <button type="button" class="booking-app-tab" :class="{ 'booking-app-tab--active': currentStep === 'services' }" :style="currentStep === 'services' ? bookingActiveTabStyle : ''" @click="goToStep('services')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10" /></svg>
                                <span>Serviços</span>
                            </button>
                            <button type="button" class="booking-app-tab" :class="{ 'booking-app-tab--active': currentStep === 'professionals', 'opacity-45': !canOpenStep('professionals') }" :style="currentStep === 'professionals' ? bookingActiveTabStyle : ''" :disabled="!canOpenStep('professionals')" @click="goToStep('professionals')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM5 20a7 7 0 0 1 14 0" /></svg>
                                <span>Profissionais</span>
                            </button>
                            <button type="button" class="booking-app-tab" :class="{ 'booking-app-tab--active': currentStep === 'date', 'opacity-45': !canOpenStep('date') }" :style="currentStep === 'date' ? bookingActiveTabStyle : ''" :disabled="!canOpenStep('date')" @click="goToStep('date')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 17v-5M12 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                <span>Data</span>
                            </button>
                            <button type="button" class="booking-app-tab" :class="{ 'booking-app-tab--active': currentStep === 'time', 'opacity-45': !canOpenStep('time') }" :style="currentStep === 'time' ? bookingActiveTabStyle : ''" :disabled="!canOpenStep('time')" @click="goToStep('time')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14v11H5zM8 8V6a4 4 0 0 1 8 0v2" /></svg>
                                <span>Horario</span>
                            </button>
                            <button type="button" class="booking-app-tab" :class="{ 'booking-app-tab--active': currentStep === 'data', 'opacity-45': !canOpenStep('data') }" :style="currentStep === 'data' ? bookingActiveTabStyle : ''" :disabled="!canOpenStep('data')" @click="goToStep('data')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM5 20a7 7 0 0 1 14 0" /></svg>
                                <span>Dados</span>
                            </button>
                            <button type="button" class="booking-app-tab" :class="{ 'booking-app-tab--active': currentStep === 'confirm', 'opacity-45': !canOpenStep('confirm') }" :style="currentStep === 'confirm' ? bookingActiveTabStyle : ''" :disabled="!canOpenStep('confirm')" @click="goToStep('confirm')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" /></svg>
                                <span>Confirmar</span>
                            </button>
                        </nav>
                        @endif

                        <section class="sf-card booking-browser-panel booking-services-panel p-4 sm:p-5" x-show="currentStep === 'services'" x-cloak>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="sf-page-eyebrow">1. Serviços</p>
                                    <h2 class="sf-section-title mt-1 text-white">Escolha um ou mais serviços</h2>
                                    <p class="mt-1 text-sm brand-muted">Escolha o que deseja agendar.</p>
                                </div>
                                <span class="hidden rounded-full bg-[color-mix(in_srgb,var(--brand-primary)_14%,var(--brand-surface))] px-3 py-1 text-xs font-semibold brand-muted sm:inline-flex" x-text="`${selectedServiceIds.length} selecionado(s)`"></span>
                            </div>

                            <div x-show="hasSelectedServices()" class="mt-4 rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)] p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--brand-primary)]">Selecionados</p>
                                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                                    <template x-for="service in selectedServices()" :key="service.id">
                                        <div class="flex min-w-[190px] max-w-[calc(100vw-4.5rem)] items-center justify-between gap-3 rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_18%,transparent)] bg-[var(--brand-surface)] px-3 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-white" x-text="service.name"></p>
                                                <p class="mt-1 text-xs brand-muted" x-text="`${service.duration} min · R$ ${service.price}`"></p>
                                            </div>
                                            <button type="button" class="text-xs font-semibold text-[var(--brand-primary)]" @click="preserveScroll(() => selectService(service.id))">Remover</button>
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

                            <div class="booking-services-list mt-4 space-y-3 pr-1">
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
                                            value="{{ $service->id }}"
                                            class="peer sr-only"
                                            :checked="selectedServiceIds.includes('{{ $service->id }}')"
                                            @change="preserveScroll(() => selectService('{{ $service->id }}'))"
                                            @checked($checked)
                                        >
                                        <span class="booking-service-selectable flex w-full items-center gap-3 rounded-[20px] p-3 transition" :class="{ 'booking-service--selected': selectedServiceIds.includes('{{ $service->id }}') }">
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
                                            <span class="rounded-full px-3 py-2 text-xs font-semibold" :class="selectedServiceIds.includes('{{ $service->id }}') ? 'bg-[var(--brand-primary)] text-[var(--brand-on-primary)]' : 'bg-[var(--brand-surface)] brand-muted'" x-text="selectedServiceIds.includes('{{ $service->id }}') ? 'Remover' : 'Selecionar'">
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

                        <section class="sf-card booking-browser-panel p-4 sm:p-5" x-show="currentStep === 'professionals'" x-cloak>
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">2</span>
                                <div>
                                    <h2 class="sf-section-title text-white">Escolha o profissional</h2>
                                    <p class="mt-1 text-sm brand-muted">Escolha com quem deseja ser atendido.</p>
                                </div>
                            </div>

                            <div x-show="!hasSelectedServices()" class="mt-4 rounded-2xl border border-dashed border-[color:color-mix(in_srgb,var(--brand-primary)_22%,transparent)] bg-[var(--brand-surface)] px-4 py-4 text-sm brand-muted">
                                @if ($selectedUser)
                                    O profissional {{ $selectedUser->name }} já está selecionado; falta escolher o serviço.
                                @else
                                    Você pode escolher o profissional agora e selecionar os serviços em seguida.
                                @endif
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                @foreach ($users as $user)
                                    @php
                                        $selected = $selectedUserId === $user->id;
                                    @endphp
                                    <label class="block cursor-pointer">
                                        <input
                                            type="radio"
                                            name="user_id"
                                            value="{{ $user->id }}"
                                            data-professional-name="{{ $user->name }}"
                                            class="peer sr-only"
                                            :checked="selectedProfessionalId === '{{ $user->id }}'"
                                            @change="preserveScroll(() => selectProfessional('{{ $user->id }}'))"
                                            @checked($selected)
                                        >
                                        <span class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-[20px] border p-3 transition" :class="selectedProfessionalId === '{{ $user->id }}' ? 'border-[var(--brand-primary)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] ring-2 ring-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)]' : 'border-[color:color-mix(in_srgb,white_10%,transparent)] bg-[var(--brand-surface)] hover:border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)]'">
                                            <span class="flex min-w-0 items-center gap-3">
                                                @if ($user->photo_url)
                                                    <img src="{{ $user->photo_url }}" alt="Foto de {{ $user->name }}" class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-white/10">
                                                @else
                                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-base font-semibold" :class="selectedProfessionalId === '{{ $user->id }}' ? 'bg-[var(--brand-primary)] text-[var(--brand-on-primary)]' : 'bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-[var(--brand-primary)]'">
                                                        {{ $user->avatar_initial }}
                                                    </span>
                                                @endif
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                                                    <span class="mt-1 block text-xs brand-muted">Disponível para seleção</span>
                                                </span>
                                            </span>
                                            <span x-show="selectedProfessionalId === '{{ $user->id }}'" x-cloak class="rounded-full bg-[var(--brand-primary)] px-3 py-1 text-xs font-semibold text-[var(--brand-on-primary)]">Selecionado</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('user_id')" />
                        </section>

                        @if (false)
                        <section class="sf-card booking-app-tab-panel p-4 sm:p-5" x-show="false" x-cloak>
                            <div>
                                <p class="sf-page-eyebrow">Sobre</p>
                                <h2 class="sf-section-title mt-1 text-white">{{ $company->name }}</h2>
                            </div>

                            @if (! empty($publicBranding['cover_url']))
                                <div class="mt-4 h-32 overflow-hidden rounded-[24px] bg-[var(--brand-surface)] bg-cover bg-center" style="background-image: url({{ \Illuminate\Support\Js::from($publicBranding['cover_url']) }});"></div>
                            @endif

                            @if ($hasAboutDetails)
                                <div class="mt-4 space-y-3">
                                    @if (filled($company->safeDescription()))
                                        <p class="booking-info-tile text-sm leading-6">{{ $company->safeDescription() }}</p>
                                    @endif
                                    @if (! empty($publicBranding['welcome_message']))
                                        <p class="booking-info-tile text-sm leading-6">{{ $publicBranding['welcome_message'] }}</p>
                                    @endif
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        @if (filled($company->address))
                                            <div class="booking-info-tile">
                                                <span>Endereço</span>
                                                <strong>{{ $company->address }}</strong>
                                            </div>
                                        @endif
                                        @if (filled($company->phone))
                                            <div class="booking-info-tile">
                                                <span>Telefone</span>
                                                <strong>{{ $company->phone }}</strong>
                                            </div>
                                        @endif
                                        @if (filled($company->instagram))
                                            <div class="booking-info-tile">
                                                <span>Instagram</span>
                                                <strong>{{ $company->instagram }}</strong>
                                            </div>
                                        @endif
                                    </div>
                                    @if ($weeklyHours->isNotEmpty())
                                        <div class="booking-info-tile">
                                            <span>Horários</span>
                                            <div class="mt-2 space-y-1">
                                                @foreach ($weeklyHours as $row)
                                                    <p class="flex justify-between gap-3 text-sm">
                                                        <strong>{{ $row['day'] }}</strong>
                                                        <span class="text-right">{{ $row['intervals']->join(', ') }}</span>
                                                    </p>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                                    As informações da empresa serão exibidas aqui em breve.
                                </div>
                            @endif
                        </section>

                        <section class="sf-card booking-app-tab-panel p-4 sm:p-5" x-show="false" x-cloak>
                            <div>
                                <p class="sf-page-eyebrow">Planos</p>
                                <h2 class="sf-section-title mt-1 text-white">Assinaturas</h2>
                            </div>

                            <div class="mt-4 space-y-3">
                                @forelse ($membershipPlans as $plan)
                                    <article class="booking-membership-card">
                                        <div class="min-w-0">
                                            <p class="text-base font-semibold text-slate-950">{{ $plan->name }}</p>
                                            @if (filled($plan->description))
                                                <p class="mt-1 line-clamp-2 text-sm leading-6 text-slate-500">{{ $plan->description }}</p>
                                            @endif
                                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                                <span>{{ $plan->billing_cycle_label }}</span>
                                                @if ($plan->max_services_per_cycle)
                                                    <span>{{ $plan->max_services_per_cycle }} serviços/ciclo</span>
                                                @endif
                                                @if ($plan->max_service_discount_percent)
                                                    <span>{{ rtrim(rtrim(number_format((float) $plan->max_service_discount_percent, 2, ',', '.'), '0'), ',') }}% em serviços</span>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="shrink-0 text-right text-lg font-bold text-[var(--brand-primary)]">R$ {{ number_format((float) $plan->price, 2, ',', '.') }}</p>
                                    </article>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                        Nenhuma assinatura disponível no momento.
                                    </div>
                                @endforelse
                            </div>
                        </section>
                        @endif

                        <section class="sf-card booking-browser-panel p-4 sm:p-5" x-show="currentStep === 'date'" x-cloak :class="!hasSelectedServices() || !selectedProfessionalId ? 'opacity-70' : ''">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">3</span>
                                <div>
                                    <h2 class="sf-section-title text-white">Escolha a data</h2>
                                    <p class="mt-1 text-sm brand-muted">Escolha o melhor dia para o atendimento.</p>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-7">
                                @foreach ($quickDates as $quickDate)
                                    @php
                                        $selected = $selectedDate === $quickDate['value'];
                                    @endphp
                                    <button
                                        type="button"
                                        @click="preserveScroll(() => selectDate('{{ $quickDate['value'] }}'))"
                                        class="booking-date-tile px-2 py-3 text-center transition"
                                        :class="selectedDate === '{{ $quickDate['value'] }}' ? 'booking-date-tile--on' : 'brand-muted'"
                                    >
                                        <span class="block text-sm font-semibold">{{ $quickDate['label'] }}</span>
                                        <span class="mt-1 block text-xs">{{ $quickDate['subtitle'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <div class="mt-4" x-show="false" x-cloak>
                                <label for="public-date" class="text-sm font-medium text-white">Escolher outra data</label>
                                <input
                                    id="public-date"
                                    type="date"
                                    min="{{ $today }}"
                                    :value="selectedDate"
                                    class="sf-input mt-2 block w-full"
                                    @change="preserveScroll(() => selectDate($event.target.value))"
                                >
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('date')" />
                        </section>

                        <div class="hidden justify-end sm:flex" x-show="['services', 'professionals', 'date'].includes(currentStep)" x-cloak>
                            <button
                                type="button"
                                class="brand-cta min-w-44 px-5 py-3 text-sm"
                                :class="{ 'cursor-not-allowed opacity-60': !canContinueCurrentStep() }"
                                :disabled="!canContinueCurrentStep()"
                                @click="continueWizard()"
                            >
                                <span x-text="continueLabel()">Continuar</span>
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('public-bookings.store', $company) }}" class="booking-app-main-card mx-3 -mt-8 w-auto max-w-full space-y-5 p-4 sm:mx-5 sm:p-5 lg:mx-0 lg:p-6" x-show="['time', 'data', 'confirm'].includes(currentStep)" x-cloak @submit="bookingSubmitIntercept($event)">
                        @csrf
                        <template x-for="serviceId in selectedServiceIds" :key="serviceId">
                            <input type="hidden" name="service_ids[]" :value="serviceId">
                        </template>
                        <input type="hidden" name="user_id" :value="selectedProfessionalId">
                        <input type="hidden" name="date" :value="selectedDate">
                        @if ($bookingPaymentRequirement === 'optional' && ! $canOfferOnlineBookingPayment)
                            <input type="hidden" name="payment_choice" :value="paymentChoice || 'on_site'">
                        @endif

                        <div class="booking-browser-tabs-wrap">
                            <nav class="booking-browser-tabs" aria-label="Navegacao do agendamento">
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('professionals'), 'is-complete': stepIsComplete('professionals'), 'is-disabled': !canOpenStep('professionals') }" :disabled="!canOpenStep('professionals')" @click="goToStep('professionals')">
                                    <span>Profissional</span>
                                </button>
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('services'), 'is-complete': stepIsComplete('services'), 'is-disabled': !canOpenStep('services') }" :disabled="!canOpenStep('services')" @click="goToStep('services')">
                                    <span>Servi&ccedil;os</span>
                                </button>
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('date'), 'is-complete': stepIsComplete('time'), 'is-disabled': !canOpenStep('date') }" :disabled="!canOpenStep('date')" @click="goToStep(selectedTime ? 'time' : 'date')">
                                    <span>Data</span>
                                </button>
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('data'), 'is-complete': stepIsComplete('data'), 'is-disabled': !canOpenStep('data') }" :disabled="!canOpenStep('data')" @click="goToStep('data')">
                                    <span>Dados</span>
                                </button>
                                <button type="button" class="booking-browser-tab" :class="{ 'is-active': tabIsActive('confirm'), 'is-complete': stepIsComplete('confirm'), 'is-disabled': !canOpenStep('confirm') }" :disabled="!canOpenStep('confirm')" @click="goToStep('confirm')">
                                    <span>Confirmar</span>
                                </button>
                            </nav>
                        </div>

                        <div class="booking-stage-head hidden" x-show="false">
                            <button type="button" class="booking-back-button" @click="previousStep()">Voltar</button>
                            <span x-show="currentStep === 'time'">Data e horario</span>
                            <span x-show="currentStep === 'data'">Dados do cliente</span>
                            <span x-show="currentStep === 'confirm'">Confirmacao</span>
                        </div>

                        <section class="sf-card booking-browser-panel p-4 sm:p-5" x-show="currentStep === 'time'" x-cloak :class="!readyForSlots() ? 'opacity-70' : ''">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">4</span>
                                <div>
                                    <h2 class="sf-section-title text-white">Escolha o horário</h2>
                                    <p class="mt-1 text-sm brand-muted">Escolha um horário disponível.</p>
                                </div>
                            </div>

                            @if (false)
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
                                            @click="preserveScroll(() => selectedTime = @js($nextAvailableSlot))"
                                                >
                                                    Usar este hor&aacute;rio
                                                </button>
                                            </div>
                                        @endif

                                        <div class="rounded-[20px] border border-[color:color-mix(in_srgb,var(--brand-primary)_14%,transparent)] bg-[color-mix(in_srgb,var(--brand-surface)_92%,var(--brand-primary)_8%)] p-4">
                                            <label for="booking-time" class="text-sm font-medium text-white">Hor&aacute;rio do atendimento</label>
                                            <select id="booking-time-legacy" x-model="selectedTime" class="sf-select mt-2 block w-full @error('time') ring-2 ring-rose-500 ring-offset-2 ring-offset-[var(--brand-ring-offset)] @enderror" disabled>
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
                                                        @click="preserveScroll(() => selectedTime = @js($slotOption['time']))"
                                                    >
                                                        {{ $slotOption['time'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            @endif

                            <div class="mt-4">
                                <div x-show="!readyForSlots()" x-cloak class="rounded-2xl border border-dashed border-[color:color-mix(in_srgb,var(--brand-primary)_22%,transparent)] bg-[var(--brand-surface)] px-4 py-5 text-sm brand-muted">
                                    Para carregar os horários da agenda real, selecione pelo menos um serviço e um profissional.
                                </div>

                                <div x-show="readyForSlots() && loadingTimes" x-cloak class="rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_18%,transparent)] bg-[var(--brand-surface)] px-4 py-5 text-sm brand-muted">
                                    Carregando hor&aacute;rios dispon&iacute;veis...
                                </div>

                                <div x-show="readyForSlots() && slotsError" x-cloak class="rounded-2xl border border-rose-400/25 bg-rose-500/10 px-4 py-5 text-sm text-rose-100">
                                    <span x-text="slotsError"></span>
                                    <button type="button" class="mt-3 block text-xs font-semibold text-[var(--brand-primary)]" @click="slotsLoaded = false; loadAvailableTimes()">Tentar novamente</button>
                                </div>

                                <div x-show="readyForSlots() && !loadingTimes && !slotsError && slotsLoaded && availableSlots().length === 0" x-cloak class="rounded-2xl border border-dashed border-[color:color-mix(in_srgb,var(--brand-primary)_22%,transparent)] bg-[var(--brand-surface)] px-4 py-5 text-sm brand-muted">
                                    Nenhum horário disponível para esta data.
                                </div>

                                <div x-show="readyForSlots() && !loadingTimes && !slotsError && availableSlots().length > 0" x-cloak class="space-y-4">
                                    <div x-show="nextAvailableSlot()" x-cloak class="flex flex-col gap-3 rounded-[20px] border border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] p-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--brand-primary)]">Pr&oacute;ximo hor&aacute;rio dispon&iacute;vel</p>
                                            <p class="mt-1 text-2xl font-semibold text-white" x-text="nextAvailableSlot()"></p>
                                        </div>
                                        <button
                                            type="button"
                                            class="brand-cta px-4 py-3 text-sm"
                                            @click="preserveScroll(() => selectedTime = nextAvailableSlot())"
                                        >
                                            Usar este hor&aacute;rio
                                        </button>
                                    </div>

                                    <div class="rounded-[20px] border border-[color:color-mix(in_srgb,var(--brand-primary)_14%,transparent)] bg-[color-mix(in_srgb,var(--brand-surface)_92%,var(--brand-primary)_8%)] p-4">
                                        <label for="booking-time" class="text-sm font-medium text-white">Hor&aacute;rio do atendimento</label>
                                        <select id="booking-time" name="time" x-model="selectedTime" class="sf-select mt-2 block w-full @error('time') ring-2 ring-rose-500 ring-offset-2 ring-offset-[var(--brand-ring-offset)] @enderror" required>
                                            <option value="">Selecione um hor&aacute;rio</option>
                                            <template x-for="slot in availableSlots()" :key="slot.time">
                                                <option :value="slot.time" x-text="slot.public_label ? `${slot.time} - ${slot.public_label}` : slot.time"></option>
                                            </template>
                                        </select>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <template x-for="slot in availableSlots().slice(0, 12)" :key="slot.time">
                                                <button
                                                    type="button"
                                                    class="booking-pill-slot px-3 py-2 text-xs font-semibold text-white transition"
                                                    :class="{ 'booking-pill-slot--on': selectedTime === slot.time }"
                                                    @click="preserveScroll(() => selectedTime = slot.time)"
                                                >
                                                    <span x-text="slot.time"></span>
                                                    <template x-if="slot.public_label">
                                                        <span class="ml-1 rounded-full bg-white/10 px-1.5 py-0.5 text-[10px] uppercase tracking-wide" x-text="slot.public_label"></span>
                                                    </template>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <x-input-error class="mt-3" :messages="$errors->get('time')" />
                        </section>

                        <section class="sf-card booking-browser-panel p-4 sm:p-5" x-show="currentStep === 'data'" x-cloak :class="!selectedTime ? 'opacity-70' : ''">
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

                                @if ($onlineBookingPaymentEnabled)
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="booking-summary-row px-4 py-4">
                                            <p class="text-xs brand-muted">{{ $bookingPaymentMode === 'full' ? 'Pagamento online' : 'Sinal' }}</p>
                                            <p class="mt-1 text-lg font-semibold text-[var(--brand-primary)]">{{ $depositSummaryText }}</p>
                                        </div>
                                        <div class="booking-summary-row px-4 py-4">
                                            <p class="text-xs brand-muted">Restante</p>
                                            <p class="mt-1 text-lg font-semibold text-white">R$ {{ number_format($remainingAmount, 2, ',', '.') }}</p>
                                        </div>
                                    </div>
                                    <p class="rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_18%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)] px-4 py-3 text-xs brand-muted">
                                        Seu horario ficara reservado por {{ $bookingPaymentExpirationMinutes }} minutos ate a confirmacao do Mercado Pago.
                                    </p>
                                @endif

                                @if ($canOfferOnlineBookingPayment && $bookingPaymentRequirement === 'optional')
                                    <div class="rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_18%,transparent)] bg-[color:color-mix(in_srgb,var(--brand-accent)_55%,transparent)] px-4 py-4">
                                        <p class="text-sm font-semibold text-white">Como deseja confirmar seu horario?</p>
                                        <p class="mt-1 text-xs brand-muted">Escolha entre pagar agora pelo Mercado Pago ou pagar diretamente no estabelecimento.</p>
                                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                            <label class="cursor-pointer rounded-2xl border px-4 py-4 transition"
                                                :class="paymentChoice === 'online' ? 'border-[var(--brand-primary)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)] shadow-[var(--shadow-glow-brand)]' : 'border-white/10 bg-[var(--brand-surface)] hover:border-[color:color-mix(in_srgb,var(--brand-primary)_18%,transparent)]'">
                                                <input type="radio" name="payment_choice" value="online" class="sr-only" x-model="paymentChoice">
                                                <p class="text-sm font-semibold text-white">Pagar agora e garantir minha reserva</p>
                                                <p class="mt-2 text-xs leading-6 brand-muted">Voce paga o {{ $bookingPaymentMode === 'full' ? 'valor total' : 'sinal' }} agora pelo Mercado Pago. O horario sera confirmado apos aprovacao do pagamento.</p>
                                            </label>
                                            <label class="cursor-pointer rounded-2xl border px-4 py-4 transition"
                                                :class="paymentChoice === 'on_site' ? 'border-[var(--brand-primary)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)] shadow-[var(--shadow-soft)]' : 'border-white/10 bg-[var(--brand-surface)] hover:border-[color:color-mix(in_srgb,var(--brand-primary)_18%,transparent)]'">
                                                <input type="radio" name="payment_choice" value="on_site" class="sr-only" x-model="paymentChoice">
                                                <p class="text-sm font-semibold text-white">Pagar no ato do servico</p>
                                                <p class="mt-2 text-xs leading-6 brand-muted">Seu horario sera confirmado agora e o pagamento sera feito diretamente no estabelecimento.</p>
                                            </label>
                                        </div>
                                        <x-input-error class="mt-3" :messages="$errors->get('payment_choice')" />
                                    </div>
                                @elseif ($shouldRequireOnlineBookingPayment)
                                    <input type="hidden" name="payment_choice" value="online">
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

                        <div class="hidden justify-end sm:flex" x-show="['time', 'data'].includes(currentStep)" x-cloak>
                            <button
                                type="button"
                                class="brand-cta min-w-44 px-5 py-3 text-sm"
                                :class="{ 'cursor-not-allowed opacity-60': !canContinueCurrentStep() }"
                                :disabled="!canContinueCurrentStep()"
                                @click="continueWizard()"
                            >
                                <span x-text="continueLabel()">Continuar</span>
                            </button>
                        </div>

                        <section class="sf-card booking-browser-panel booking-confirm-card p-4 sm:p-5" x-show="currentStep === 'confirm'" x-cloak>
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">6</span>
                                <div>
                                    <h2 class="sf-section-title text-white">Confirmação</h2>
                                    <p class="mt-1 text-sm brand-muted">
                                        @if ($shouldRequireOnlineBookingPayment)
                                            Seu horário será confirmado após a aprovação do pagamento online. A disponibilidade será validada novamente antes da reserva final.
                                        @elseif ($canOfferOnlineBookingPayment && $bookingPaymentRequirement === 'optional')
                                            Se escolher pagar agora, o horario sera confirmado apos a aprovacao do pagamento. Se preferir pagar no local, o agendamento sera confirmado imediatamente.
                                        @else
                                            O horário será confirmado após o envio. A disponibilidade será validada novamente.
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="booking-confirm-summary mt-5 space-y-3">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="booking-confirm-tile">
                                        <span>Profissional</span>
                                        <strong x-text="selectedProfessionalName()">{{ $selectedUser?->name ?? '-' }}</strong>
                                    </div>
                                    <div class="booking-confirm-tile">
                                        <span>Data e horario</span>
                                        <strong x-text="`${selectedDate || '-'}${selectedTime ? ' - ' + selectedTime : ''}`">{{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}{{ $bookingTimeEffective ? ' - '.$bookingTimeEffective : '' }}</strong>
                                    </div>
                                </div>

                                <div class="booking-confirm-tile">
                                    <span>Serviços</span>
                                    <div class="mt-3 space-y-2">
                                        <template x-for="service in selectedServices()" :key="service.id">
                                            <div class="flex items-center justify-between gap-3">
                                                <p class="min-w-0 truncate text-sm font-semibold" x-text="service.name"></p>
                                                <p class="shrink-0 text-sm font-semibold text-[var(--brand-primary)]" x-text="'R$ ' + service.price"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div class="booking-confirm-total">
                                    <span>Total</span>
                                    <strong x-text="'R$ ' + formattedTotalPrice()">R$ {{ number_format($totalPrice, 2, ',', '.') }}</strong>
                                </div>
                            </div>

                            @if ($onlineBookingPaymentEnabled)
                                <div class="mt-5 rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_20%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)] px-4 py-4 text-sm">
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.16em] brand-muted">Valor total</p>
                                            <p class="mt-1 font-semibold text-white">R$ {{ number_format($totalPrice, 2, ',', '.') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.16em] brand-muted">{{ $bookingPaymentMode === 'full' ? 'Pagamento online' : 'Sinal' }}</p>
                                            <p class="mt-1 font-semibold text-[var(--brand-primary)]">{{ $depositSummaryText }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.16em] brand-muted">Restante no salao</p>
                                            <p class="mt-1 font-semibold text-white">R$ {{ number_format($remainingAmount, 2, ',', '.') }}</p>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-xs brand-muted">Seu horario ficara reservado por {{ $bookingPaymentExpirationMinutes }} minutos enquanto aguardamos a confirmacao do Mercado Pago.</p>
                                </div>
                            @endif

                            @if ($errors->has('payment'))
                                <div class="mt-4 rounded-2xl border border-rose-400/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                                    {{ $errors->first('payment') }}
                                </div>
                            @endif

                            <button
                                id="booking-confirm-submit"
                                type="submit"
                                class="brand-cta mt-5 w-full min-h-[60px] text-base transition"
                                :class="{ 'cursor-not-allowed opacity-60': !readyToConfirm() }"
                                :aria-disabled="!readyToConfirm()"
                            >
                                @if ($shouldRequireOnlineBookingPayment)
                                    {{ $bookingPaymentMode === 'full' ? 'Pagar e confirmar horario' : 'Pagar sinal e reservar horario' }}
                                @elseif ($canOfferOnlineBookingPayment && $bookingPaymentRequirement === 'optional')
                                    <span x-show="paymentChoice === 'online'" x-cloak>{{ $bookingPaymentMode === 'full' ? 'Pagar e confirmar horario' : 'Pagar sinal e reservar horario' }}</span>
                                    <span x-show="paymentChoice === 'on_site'" x-cloak>Confirmar agendamento e pagar no local</span>
                                    <span x-show="!paymentChoice" x-cloak>Escolha como deseja confirmar</span>
                                @else
                                    Confirmar agendamento
                                @endif
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

                <div class="booking-sticky-bar fixed inset-x-0 bottom-0 z-30 px-4 py-3 backdrop-blur xl:hidden">
                    <div class="mx-auto flex w-full max-w-full items-center justify-between gap-3 sm:max-w-none lg:max-w-7xl">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">
                                {{ $selectedServiceIds->isNotEmpty() ? $selectedServiceIds->count().' Serviços · '.$totalDurationMinutes.' min' : 'Escolha os Serviços' }}
                            </p>
                            <p class="mt-1 text-xs brand-muted">
                                {{ $selectedServiceIds->isNotEmpty() ? 'R$ '.number_format($totalPrice, 2, ',', '.') : 'Total a definir' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="brand-cta shrink-0 px-4 py-3 text-sm"
                            x-show="currentStep !== 'confirm'"
                            x-cloak
                            :class="{ 'cursor-not-allowed opacity-60': !canContinueCurrentStep() }"
                            :disabled="!canContinueCurrentStep()"
                            @click="continueWizard()"
                        >
                            <span x-text="continueLabel()">Continuar</span>
                        </button>
                        <button
                            type="button"
                            class="brand-cta shrink-0 px-4 py-3 text-sm"
                            x-show="currentStep === 'confirm'"
                            x-cloak
                            @click="document.getElementById('booking-confirm-submit')?.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                        >
                            Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </main>

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
