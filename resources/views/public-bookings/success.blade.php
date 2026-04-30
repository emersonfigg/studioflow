<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Agendamento confirmado - {{ $company->name }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#1b335b] font-sans text-white antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-5xl items-center justify-center px-4 py-6 sm:px-6 lg:px-8">
            <section class="w-full overflow-hidden rounded-[28px] border border-white/10 bg-[#223d69] shadow-[0_24px_56px_rgba(9,20,45,0.38)]">
                <div class="border-b border-white/10 bg-[#132746] px-5 py-6 text-white sm:px-6">
                    <div class="inline-flex items-center rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">
                        Agendamento confirmado
                    </div>
                    <div class="mt-4 flex items-center gap-4">
                        @if ($company->logo_url)
                            <img src="{{ $company->logo_url }}" alt="Logo de {{ $company->name }}" class="h-14 w-14 rounded-3xl object-cover ring-1 ring-white/10">
                        @else
                            <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-[#d4af37]/12 text-[#d4af37] ring-1 ring-white/10">
                                <x-application-logo class="h-7 w-7" />
                            </div>
                        @endif
                        <div>
                            <h1 class="text-3xl font-semibold tracking-tight text-white">{{ $company->name }}</h1>
                            <p class="mt-1 text-sm text-[#c7d2e3]">{{ $company->description ?: 'Seu bloco de atendimento foi confirmado com sucesso.' }}</p>
                        </div>
                    </div>
                    <p class="mt-2 max-w-2xl text-sm leading-7 text-[#c7d2e3]">
                        Seu bloco de atendimento foi confirmado com sucesso. Confira abaixo todos os detalhes do agendamento.
                    </p>
                </div>

                <div class="grid gap-6 px-5 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="space-y-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Cliente</p>
                                <p class="mt-2 text-sm font-semibold text-white">{{ $appointment->client->name }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">{{ $appointment->client->phone }}</p>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Status</p>
                                <p class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                    {{ $appointment->statusLabel() }}
                                </p>
                                <p class="mt-2 text-sm text-[#c7d2e3]">Origem: Agendamento publico</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Servicos selecionados</p>
                            <div class="mt-4 space-y-3">
                                @foreach ($appointment->bookedServices() as $service)
                                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-[#1b335b] px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-white">{{ $service->name }}</p>
                                            <p class="mt-1 text-xs text-[#c7d2e3]">{{ $service->pivot->duration_snapshot ?? $service->duration_minutes }} min</p>
                                        </div>
                                        <p class="text-sm font-semibold text-[#d4af37]">
                                            R$ {{ number_format((float) ($service->pivot->price_snapshot ?? $service->price), 2, ',', '.') }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-[#132746]">
                            <dl class="grid gap-0 sm:grid-cols-2">
                                <div class="border-b border-white/10 px-4 py-4 sm:border-b-0 sm:border-r">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Profissional</dt>
                                    <dd class="mt-2 text-sm font-semibold text-white">{{ $appointment->user->name }}</dd>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Bloco total: {{ $appointment->totalDurationMinutes() }} minutos</p>
                                </div>
                                <div class="px-4 py-4">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Valor total</dt>
                                    <dd class="mt-2 text-sm font-semibold text-white">R$ {{ number_format($appointment->totalPriceAmount(), 2, ',', '.') }}</dd>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Resumo dos servicos selecionados no agendamento.</p>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Detalhes do horario</p>
                            <p class="mt-2 text-sm text-white">Data: {{ $appointment->start_time->format('d/m/Y') }}</p>
                            <p class="mt-1 text-sm text-white">Inicio: {{ $appointment->start_time->format('H:i') }}</p>
                            <p class="mt-1 text-sm text-white">Fim: {{ $appointment->end_time->format('H:i') }}</p>
                            @if ($appointment->notes)
                                <p class="mt-3 text-sm text-[#c7d2e3]">Observacoes: {{ $appointment->notes }}</p>
                            @endif
                        </div>
                    </div>

                    <aside class="space-y-4">
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-5 py-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Resumo</p>
                            <p class="mt-3 text-sm text-[#c7d2e3]">Tudo certo para o seu atendimento na {{ $company->name }}.</p>
                            <div class="mt-5 space-y-3">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#c7d2e3]">Servicos</span>
                                    <span class="text-sm font-semibold text-white">{{ $appointment->bookedServices()->count() }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#c7d2e3]">Profissional</span>
                                    <span class="text-sm font-semibold text-white">{{ $appointment->user->name }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#c7d2e3]">Horario</span>
                                    <span class="text-sm font-semibold text-white">{{ $appointment->start_time->format('d/m H:i') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#c7d2e3]">Duracao total</span>
                                    <span class="text-sm font-semibold text-white">{{ $appointment->totalDurationMinutes() }} min</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#c7d2e3]">Valor total</span>
                                    <span class="text-sm font-semibold text-[#d4af37]">R$ {{ number_format($appointment->totalPriceAmount(), 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        <a href="{{ route('public-bookings.create', $company) }}" class="sf-button-primary w-full min-h-[56px] text-base">
                            Agendar outro horario
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
