@php
    $isPayOnSite = $appointment->payment_status === 'unpaid' || $appointment->payment_gateway === 'on_site';
    $successCopy = $isPayOnSite
        ? 'Seu horario foi confirmado. O pagamento sera feito diretamente no estabelecimento.'
        : 'Seu bloco de atendimento foi confirmado com sucesso. Confira abaixo todos os detalhes do agendamento.';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ ($publicBranding['theme_light'] ?? false) ? 'light' : 'dark' }}" style="{{ $publicBranding['root_style'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Agendamento confirmado - {{ $company->name }}</title>

        @if (! empty($publicFaviconHref))
            <link rel="icon" href="{{ $publicFaviconHref }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-booking-page min-h-screen font-sans text-white antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-5xl items-center justify-center px-4 py-6 sm:px-6 lg:px-8">
            <section class="w-full overflow-hidden rounded-[28px] border border-[color:color-mix(in_srgb,var(--brand-primary)_18%,transparent)] bg-[color-mix(in_srgb,var(--brand-secondary)_92%,black)] shadow-[0_24px_56px_color-mix(in_srgb,black_42%,transparent)]">
                <div class="border-b border-[color:color-mix(in_srgb,var(--brand-primary)_14%,transparent)] bg-[color-mix(in_srgb,var(--brand-accent)_78%,black)] px-5 py-6 text-white sm:px-6">
                    <div class="inline-flex items-center rounded-full border border-emerald-400/25 bg-emerald-400/12 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200 shadow-[var(--shadow-soft)]">
                        Agendamento confirmado
                    </div>
                    <div class="mt-4 flex items-center gap-4">
                        @if (! empty($publicBranding['logo_url']))
                            <img src="{{ $publicBranding['logo_url'] }}" alt="Logo de {{ $company->name }}" class="h-14 w-14 rounded-3xl object-cover ring-1 ring-[color:color-mix(in_srgb,white_12%,transparent)]" loading="lazy" decoding="async">
                        @else
                            <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-[color-mix(in_srgb,var(--brand-primary)_14%,var(--brand-surface))] text-[var(--brand-primary)] ring-1 ring-[color:color-mix(in_srgb,white_12%,transparent)]">
                                <x-application-logo class="h-7 w-7" />
                            </div>
                        @endif
                        <div>
                            <h1 class="sf-page-title text-3xl text-white">{{ $publicBranding['hero_title'] }}</h1>
                            <p class="sf-page-subtitle mt-1 brand-muted">{{ $publicBranding['hero_subtitle'] ?? ($publicBranding['description_fallback'] ?: 'Seu bloco de atendimento foi confirmado com sucesso.') }}</p>
                        </div>
                    </div>
                    <p class="mt-2 max-w-2xl text-sm leading-7 brand-muted">{{ $successCopy }}</p>
                </div>

                <div class="grid gap-6 px-5 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="space-y-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="booking-summary-row px-4 py-4">
                            <p class="sf-label">Cliente</p>
                                <p class="mt-2 text-sm font-semibold text-white">{{ $appointment->client->name }}</p>
                                <p class="mt-1 text-sm brand-muted">{{ $appointment->client->phone }}</p>
                            </div>

                            <div class="booking-summary-row px-4 py-4">
                            <p class="sf-label">Status</p>
                                <p class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                    {{ $appointment->statusLabel() }}
                                </p>
                                <p class="mt-2 text-sm brand-muted">Origem: Agendamento público</p>
                            </div>
                        </div>

                        <div class="booking-summary-row px-4 py-4">
                            <p class="sf-label">Serviços selecionados</p>
                            <div class="mt-4 space-y-3">
                                @foreach ($appointment->bookedServices() as $service)
                                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_12%,transparent)] bg-[var(--brand-surface)] px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-white">{{ $service->name }}</p>
                                            <p class="mt-1 text-xs brand-muted">{{ $service->pivot->duration_snapshot ?? $service->duration_minutes }} min</p>
                                        </div>
                                        <p class="text-sm font-semibold text-[var(--brand-primary)]">
                                            R$ {{ number_format((float) ($service->pivot->price_snapshot ?? $service->price), 2, ',', '.') }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="booking-summary-row overflow-hidden">
                            <dl class="grid gap-0 sm:grid-cols-2">
                                <div class="border-b border-[color:color-mix(in_srgb,white_10%,transparent)] px-4 py-4 sm:border-b-0 sm:border-r sm:border-[color:color-mix(in_srgb,white_10%,transparent)]">
                                    <dt class="sf-label">Profissional</dt>
                                    <dd class="mt-2 text-sm font-semibold text-white">{{ $appointment->user->name }}</dd>
                                    <p class="mt-1 text-sm brand-muted">Bloco total: {{ $appointment->totalDurationMinutes() }} minutos</p>
                                </div>
                                <div class="px-4 py-4">
                                    <dt class="sf-label">Valor total</dt>
                                    <dd class="mt-2 text-sm font-semibold text-white">R$ {{ number_format($appointment->totalPriceAmount(), 2, ',', '.') }}</dd>
                                    <p class="mt-1 text-sm brand-muted">Resumo dos serviços selecionados no agendamento.</p>
                                </div>
                            </dl>
                        </div>

                        <div class="booking-summary-row px-4 py-4">
                            <p class="sf-label">Detalhes do horário</p>
                            <p class="mt-2 text-sm text-white">Data: {{ $appointment->start_time->format('d/m/Y') }}</p>
                            <p class="mt-1 text-sm text-white">Início: {{ $appointment->start_time->format('H:i') }}</p>
                            <p class="mt-1 text-sm text-white">Fim: {{ $appointment->end_time->format('H:i') }}</p>
                            @if ($appointment->notes)
                                <p class="mt-3 text-sm brand-muted">Observações: {{ $appointment->notes }}</p>
                            @endif
                        </div>
                    </div>

                    <aside class="space-y-4">
                        <div class="booking-summary-row px-5 py-5">
                            <p class="sf-label">Resumo</p>
                            <p class="mt-3 text-sm brand-muted">Tudo certo para o seu atendimento na {{ $company->name }}.</p>
                            <div class="mt-5 space-y-3">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Serviços</span>
                                    <span class="text-sm font-semibold text-white">{{ $appointment->bookedServices()->count() }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Profissional</span>
                                    <span class="text-sm font-semibold text-white">{{ $appointment->user->name }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Horário</span>
                                    <span class="text-sm font-semibold text-white">{{ $appointment->start_time->format('d/m H:i') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Duração total</span>
                                    <span class="text-sm font-semibold text-white">{{ $appointment->totalDurationMinutes() }} min</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Valor total</span>
                                    <span class="text-sm font-semibold booking-summary-total">R$ {{ number_format($appointment->totalPriceAmount(), 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        <a href="{{ route('public-bookings.create', $company) }}" class="brand-cta w-full min-h-[56px] text-base">
                            Agendar outro horário
                        </a>

                        <a href="{{ $whatsAppUrl }}" class="sf-button-secondary w-full min-h-[56px] text-base" target="_blank" rel="noreferrer">
                            Chamar no WhatsApp
                        </a>
                    </aside>
                </div>
            </section>
        </main>
    </body>
</html>
