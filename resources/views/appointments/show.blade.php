@php
    $backToAgendaUrl = route('appointments.index');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">{{ __('Appointments') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    {{ __('Appointment') }}
                </h2>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                        {{ $appointment->statusLabel() }}
                    </span>
                    @if ($appointment->status === 'completed')
                        <span class="inline-flex items-center rounded-full border border-emerald-400/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-100">
                            Atendimento concluido
                        </span>
                    @endif
                    @if ($appointment->payment)
                        <span class="inline-flex items-center rounded-full border border-[#d4af37]/30 bg-[#d4af37]/10 px-2.5 py-1 text-xs font-semibold text-[#f6e7b3]">
                            Pagamento registrado
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ $backToAgendaUrl }}" class="sf-button-ghost">
                    Voltar para agenda
                </a>

                <a href="{{ route('appointments.create') }}" class="sf-button-primary">
                    Novo agendamento
                </a>

                @if ($appointment->status === 'completed' && ! $appointment->payment && (auth()->user()->isAdmin() || auth()->id() === $appointment->user_id))
                    <a href="{{ route('appointments.payments.create', $appointment) }}" class="sf-button-secondary">
                        Registrar pagamento
                    </a>
                @elseif ($appointment->status !== 'completed' && $appointment->status !== 'cancelled' && ! $appointment->payment && (auth()->user()->isAdmin() || auth()->id() === $appointment->user_id))
                    <a href="{{ route('appointments.payments.create', $appointment) }}" class="sf-button-primary">
                        Concluir atendimento
                    </a>
                @endif

                @if (auth()->user()->isAdmin())
                    <a href="{{ route('appointments.edit', $appointment) }}" class="sf-button-secondary">
                        {{ __('Edit') }}
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="sf-card p-6 sm:p-7">
            <dl class="grid gap-6 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-[#c7d2e3]">{{ __('Client') }}</dt>
                    <dd class="mt-1 text-sm text-white">{{ $appointment->client->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-[#c7d2e3]">{{ __('Service') }}</dt>
                    <dd class="mt-1 text-sm text-white">{{ $appointment->service->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-[#c7d2e3]">{{ __('Staff') }}</dt>
                    <dd class="mt-1 text-sm text-white">{{ $appointment->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-[#c7d2e3]">{{ __('Status') }}</dt>
                    <dd class="mt-1 text-sm text-white">{{ $appointment->statusLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-[#c7d2e3]">{{ __('Source') }}</dt>
                    <dd class="mt-1 text-sm text-white">{{ __(str_replace('_', ' ', ucfirst($appointment->source))) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-[#c7d2e3]">{{ __('Start') }}</dt>
                    <dd class="mt-1 text-sm text-white">{{ $appointment->start_time->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-[#c7d2e3]">{{ __('End') }}</dt>
                    <dd class="mt-1 text-sm text-white">{{ $appointment->end_time->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-[#c7d2e3]">{{ __('Notes') }}</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-white">{{ $appointment->notes ?: '-' }}</dd>
                </div>
            </dl>

            <div class="mt-8 flex flex-col gap-4 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ $backToAgendaUrl }}" class="sf-button-secondary">
                    Voltar para agenda
                </a>

                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('appointments.destroy', $appointment) }}">
                        @csrf
                        @method('DELETE')
                        <x-danger-button onclick="return confirm('{{ __('Delete this appointment?') }}')">
                            {{ __('Delete') }}
                        </x-danger-button>
                    </form>
                @endif
            </div>
        </div>

        <aside class="sf-card p-6">
            <h3 class="text-base font-semibold text-white">Pagamento</h3>
            @if ($appointment->payment)
                <dl class="mt-5 space-y-4">
                    <div>
                        <dt class="text-sm text-[#c7d2e3]">Forma de pagamento</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">{{ ucfirst($appointment->payment->payment_method) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[#c7d2e3]">Valor bruto</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">R$ {{ number_format((float) $appointment->payment->gross_amount, 2, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[#c7d2e3]">Comissão</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">R$ {{ number_format((float) $appointment->payment->commission_amount, 2, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[#c7d2e3]">Liquido da empresa</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">R$ {{ number_format((float) $appointment->payment->net_amount, 2, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[#c7d2e3]">Pago em</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">{{ $appointment->payment->paid_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            @else
                <p class="mt-4 text-sm leading-6 text-[#c7d2e3]">
                    Ainda não há pagamento registrado para este atendimento.
                </p>

                @if ($appointment->status === 'completed' && (auth()->user()->isAdmin() || auth()->id() === $appointment->user_id))
                    <div class="mt-5">
                        <a href="{{ route('appointments.payments.create', $appointment) }}" class="sf-button-primary">
                            Registrar pagamento
                        </a>
                    </div>
                @endif
            @endif
        </aside>
    </div>
</x-app-layout>
