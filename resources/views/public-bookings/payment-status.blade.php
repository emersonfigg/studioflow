@php
    $isPaid = $bookingPayment->status === \App\Models\BookingPayment::STATUS_PAID;
    $isPending = $bookingPayment->status === \App\Models\BookingPayment::STATUS_PENDING;
    $isFailed = in_array($bookingPayment->status, [\App\Models\BookingPayment::STATUS_FAILED, \App\Models\BookingPayment::STATUS_EXPIRED], true);
    $statusBadgeClasses = match (true) {
        $isPaid => 'border-emerald-400/25 bg-emerald-400/12 text-emerald-200',
        $isFailed => 'border-rose-400/25 bg-rose-400/12 text-rose-100',
        default => 'border-amber-400/25 bg-amber-400/12 text-amber-100',
    };
    $statusPanelClasses = match (true) {
        $isPaid => 'border-emerald-400/20 bg-emerald-500/10',
        $isFailed => 'border-rose-400/20 bg-rose-500/10',
        default => 'border-amber-400/20 bg-amber-500/10',
    };
    $statusIcon = match (true) {
        $isPaid => '✓',
        $isFailed => '!',
        default => '...',
    };
    $summaryTitle = match (true) {
        $isPaid => 'Pagamento confirmado',
        $screen === 'failure' || $isFailed => 'Pagamento nao concluido',
        default => 'Aguardando pagamento',
    };
    $summaryCopy = match (true) {
        $isPaid => 'Recebemos a confirmacao do Mercado Pago e seu horario ja esta reservado.',
        $screen === 'success' => 'O retorno do Mercado Pago foi recebido. Agora estamos aguardando a confirmacao final do pagamento pela integracao.',
        $screen === 'failure' || $isFailed => 'O pagamento nao foi confirmado. Voce pode tentar novamente enquanto o prazo da reserva estiver ativo.',
        default => 'Seu horario ficara reservado temporariamente enquanto aguardamos a confirmacao do pagamento online.',
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ ($publicBranding['theme_light'] ?? false) ? 'light' : 'dark' }}" style="{{ $publicBranding['root_style'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $summaryTitle }} - {{ $company->name }}</title>
        @if (! empty($publicFaviconHref))
            <link rel="icon" href="{{ $publicFaviconHref }}">
        @endif
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-booking-page min-h-screen font-sans text-white antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
            <section class="w-full overflow-hidden rounded-[28px] border border-[color:color-mix(in_srgb,var(--brand-primary)_18%,transparent)] bg-[color-mix(in_srgb,var(--brand-secondary)_92%,black)] shadow-[var(--shadow-elevated)]">
                <div class="border-b border-[color:color-mix(in_srgb,var(--brand-primary)_14%,transparent)] bg-[color-mix(in_srgb,var(--brand-accent)_78%,black)] px-5 py-6 text-white sm:px-6">
                    <div class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $statusBadgeClasses }}">
                        {{ $summaryTitle }}
                    </div>
                    <h1 class="sf-page-title mt-4 text-3xl text-white">{{ $company->publicDisplayHeadline() }}</h1>
                    <p class="sf-page-subtitle mt-2 max-w-2xl brand-muted">{{ $summaryCopy }}</p>
                </div>

                <div class="grid gap-6 px-5 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="space-y-4">
                        <div class="rounded-3xl border px-4 py-4 shadow-[var(--shadow-soft)] {{ $statusPanelClasses }}">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-lg font-bold text-white">
                                    {{ $statusIcon }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">Status da reserva</p>
                                    <p class="mt-1 text-lg font-semibold text-white">{{ $bookingPayment->statusLabel() }}</p>
                                    <p class="mt-2 text-sm leading-6 text-white/80">
                                        @if ($isPaid)
                                            Pagamento validado pelo Mercado Pago. Seu horario ja pode ser tratado como confirmado.
                                        @elseif ($isFailed)
                                            O pagamento nao foi concluido. Enquanto o prazo da reserva estiver ativo, voce pode tentar novamente.
                                        @else
                                            Estamos aguardando a confirmacao oficial do pagamento. O retorno do navegador sozinho nao confirma a reserva.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="booking-summary-row px-4 py-4">
                            <p class="sf-label">Resumo da reserva</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <p class="text-xs brand-muted">Cliente</p>
                                    <p class="mt-1 text-sm font-semibold text-white">{{ $appointment->client->name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs brand-muted">Profissional</p>
                                    <p class="mt-1 text-sm font-semibold text-white">{{ $appointment->user->name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs brand-muted">Data</p>
                                    <p class="mt-1 text-sm font-semibold text-white">{{ $appointment->start_time->format('d/m/Y') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs brand-muted">Horario</p>
                                    <p class="mt-1 text-sm font-semibold text-white">{{ $appointment->start_time->format('H:i') }} - {{ $appointment->end_time->format('H:i') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="booking-summary-row px-4 py-4">
                            <p class="sf-label">Serviços</p>
                            <div class="mt-3 space-y-2">
                                @foreach ($appointment->bookedServices() as $service)
                                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-[color:color-mix(in_srgb,var(--brand-primary)_12%,transparent)] bg-[var(--brand-surface)] px-4 py-3">
                                        <div>
                                            <p class="text-sm font-semibold text-white">{{ $service->name }}</p>
                                            <p class="mt-1 text-xs brand-muted">{{ $service->pivot->duration_snapshot ?? $service->duration_minutes }} min</p>
                                        </div>
                                        <p class="text-sm font-semibold text-[var(--brand-primary)]">
                                            R$ {{ number_format((float) ($service->pivot->price_snapshot ?? $service->price), 2, ',', '.') }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="booking-summary-row px-4 py-4">
                            <p class="sf-label">Orientacao</p>
                            <p class="mt-3 text-sm leading-7 brand-muted">
                                @if ($isPaid)
                                    Pagamento confirmado. O StudioFlow ja reservou seu horario.
                                @elseif ($isFailed)
                                    O pagamento ainda nao foi confirmado. Se o prazo ainda estiver ativo, voce pode abrir o checkout novamente e concluir a reserva.
                                @else
                                    Seu horario ficara reservado por {{ (int) ($appointment->company->booking_payment_expiration_minutes ?: 15) }} minutos. Se o pagamento nao for confirmado nesse prazo, o horario podera ser liberado.
                                @endif
                            </p>
                        </div>

                        <div class="booking-summary-row px-4 py-4">
                            <p class="sf-label">Etapas</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-2xl border border-white/10 bg-[var(--brand-surface)] px-4 py-3">
                                    <p class="text-xs uppercase tracking-[0.16em] brand-muted">1</p>
                                    <p class="mt-2 text-sm font-semibold text-white">Horario escolhido</p>
                                </div>
                                <div class="rounded-2xl border px-4 py-3 {{ $isPending ? 'border-amber-400/20 bg-amber-400/10' : 'border-white/10 bg-[var(--brand-surface)]' }}">
                                    <p class="text-xs uppercase tracking-[0.16em] brand-muted">2</p>
                                    <p class="mt-2 text-sm font-semibold text-white">Pagamento enviado</p>
                                </div>
                                <div class="rounded-2xl border px-4 py-3 {{ $isPaid ? 'border-emerald-400/20 bg-emerald-400/10' : ($isFailed ? 'border-rose-400/20 bg-rose-400/10' : 'border-white/10 bg-[var(--brand-surface)]') }}">
                                    <p class="text-xs uppercase tracking-[0.16em] brand-muted">3</p>
                                    <p class="mt-2 text-sm font-semibold text-white">{{ $isPaid ? 'Reserva confirmada' : ($isFailed ? 'Pagamento falhou' : 'Confirmacao final') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="space-y-4">
                        <div class="booking-summary-row px-5 py-5">
                            <p class="sf-label">Pagamento</p>
                            <div class="mt-4 space-y-3">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Status</span>
                                    <span class="text-sm font-semibold text-white">{{ $bookingPayment->statusLabel() }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Valor total</span>
                                    <span class="text-sm font-semibold text-white">R$ {{ number_format((float) $appointment->amount_total, 2, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">{{ $bookingPayment->payment_type === 'full' ? 'Pagamento antecipado' : 'Sinal' }}</span>
                                    <span class="text-sm font-semibold booking-summary-total">R$ {{ number_format((float) $bookingPayment->amount, 2, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Restante no salao</span>
                                    <span class="text-sm font-semibold text-white">R$ {{ number_format(max(0, (float) $appointment->amount_total - (float) $bookingPayment->amount), 2, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Referencia</span>
                                    <span class="text-sm font-semibold text-white">{{ $bookingPayment->external_reference }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm brand-muted">Valido ate</span>
                                    <span class="text-sm font-semibold text-white">{{ $bookingPayment->expires_at?->format('d/m H:i') ?? '—' }}</span>
                                </div>
                            </div>
                        </div>

                        @if (! $isPaid && $bookingPayment->checkout_url)
                            <a href="{{ $bookingPayment->checkout_url }}" target="_blank" rel="noreferrer" class="brand-cta w-full min-h-[56px] text-base">
                                {{ $isFailed ? 'Tentar pagamento novamente' : 'Pagar sinal e reservar horario' }}
                            </a>
                        @endif

                        @if ($isPaid)
                            <a href="{{ route('public-bookings.success', ['company' => $company, 'appointment' => $appointment]) }}" class="brand-cta w-full min-h-[56px] text-base">
                                Ver agendamento confirmado
                            </a>
                        @endif

                        <a href="{{ route('public-bookings.create', $company) }}" class="sf-button-secondary w-full min-h-[56px] text-base">
                            Voltar ao agendamento
                        </a>
                    </aside>
                </div>
            </section>
        </main>
    </body>
</html>
