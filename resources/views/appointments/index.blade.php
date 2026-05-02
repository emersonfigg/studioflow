<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">{{ __('Appointments') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Agenda diaria
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">
                    Visual operacional com apenas os agendamentos existentes na data selecionada.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('appointments.index') }}" class="flex flex-1 flex-wrap items-center gap-3 xl:flex-none">
                    <input
                        type="date"
                        name="date"
                        value="{{ $selectedDate->format('Y-m-d') }}"
                        class="sf-input min-w-[180px]"
                    >

                    <select
                        name="user_id"
                        class="sf-select min-w-[220px]"
                    >
                        <option value="">{{ __('All Professionals') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($selectedUserId === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>

                    <button class="sf-button-ghost">
                        {{ __('Filter') }}
                    </button>
                </form>

                @if (auth()->user()->company_id)
                    <a href="{{ route('appointments.create') }}" class="sf-button-primary">
                        {{ __('New Appointment') }}
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                @if (session('status') === 'payment-created')
                    Pagamento registrado e atendimento concluido com sucesso.
                @else
                    {{ __('Appointment action completed successfully.') }}
                @endif
            </div>
        @endif

        @if ($appointments->isEmpty())
            <section class="sf-card px-6 py-16 text-center">
                <div class="mx-auto max-w-xl">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl border border-[#d4af37]/20 bg-[#d4af37]/10 text-[#d4af37]">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v11a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-2xl font-semibold text-white">Nenhum agendamento para está data.</h3>
                    <p class="mt-3 text-sm leading-7 text-[#c7d2e3]">
                        Ajuste a data, filtre por profissional ou crie um novo atendimento para preencher a agenda.
                    </p>
                    <div class="mt-6">
                        <a href="{{ route('appointments.create') }}" class="sf-button-primary">
                            Novo agendamento
                        </a>
                    </div>
                </div>
            </section>
        @else
            <section class="space-y-4">
                @foreach ($appointmentsByTime as $time => $groupedAppointments)
                    <section class="sf-card overflow-hidden">
                        <div class="border-b border-white/10 bg-[#132746] px-5 py-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Horário</p>
                                    <h3 class="mt-1 text-2xl font-semibold text-white">{{ $time }}</h3>
                                </div>
                                <p class="text-sm text-[#c7d2e3]">{{ $groupedAppointments->count() }} agendamento(s)</p>
                            </div>
                        </div>

                        <div class="divide-y divide-white/10">
                            @foreach ($groupedAppointments as $appointment)
                                @php
                                    $servicesLabel = $appointment->bookedServices()->pluck('name')->join(', ');
                                @endphp
                                <article class="px-5 py-5 transition hover:bg-white/[0.03]">
                                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-semibold text-white">
                                                    {{ $appointment->start_time->format('H:i') }} - {{ $appointment->end_time->format('H:i') }}
                                                </p>
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                                    {{ $appointment->statusLabel() }}
                                                </span>
                                                @if ($appointment->payment)
                                                    <span class="inline-flex items-center rounded-full border border-[#d4af37]/20 bg-[#d4af37]/10 px-2.5 py-1 text-xs font-medium text-[#d4af37]">
                                                        Pago
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">Cliente</p>
                                                    <p class="mt-1 text-sm font-semibold text-white">{{ $appointment->client->name }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">Profissional</p>
                                                    <p class="mt-1 text-sm font-semibold text-white">{{ $appointment->user->name }}</p>
                                                </div>
                                                <div class="lg:col-span-2">
                                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">Serviço(s)</p>
                                                    <p class="mt-1 text-sm text-white">{{ $servicesLabel !== '' ? $servicesLabel : $appointment->service->name }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-2 xl:max-w-[420px] xl:justify-end">
                                            <a href="{{ route('appointments.show', $appointment) }}" class="sf-button-ghost !px-3 !py-2 !text-xs">
                                                {{ __('View') }}
                                            </a>

                                            @if (auth()->user()->isAdmin())
                                                <a href="{{ route('appointments.edit', $appointment) }}" class="sf-button-secondary !px-3 !py-2 !text-xs">
                                                    {{ __('Quick Edit') }}
                                                </a>
                                            @endif

                                            @if ($appointment->status === 'scheduled')
                                                <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button class="rounded-xl border border-sky-300/20 bg-sky-400/10 px-3 py-2 text-xs font-semibold text-sky-50 transition hover:bg-sky-400/20">
                                                        {{ __('Quick Confirm') }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if (in_array($appointment->status, ['scheduled', 'confirmed'], true))
                                                <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="in_progress">
                                                    <button class="rounded-xl border border-amber-300/20 bg-amber-400/10 px-3 py-2 text-xs font-semibold text-amber-50 transition hover:bg-amber-400/20">
                                                        {{ __('Quick Start') }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($appointment->status !== 'cancelled' && ! $appointment->payment && (auth()->user()->isAdmin() || auth()->id() === $appointment->user_id))
                                                <a href="{{ route('appointments.payments.create', $appointment) }}" class="sf-button-primary !px-3 !py-2 !text-xs">
                                                    Concluir atendimento
                                                </a>
                                            @endif

                                            @if ($appointment->status !== 'cancelled')
                                                <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button class="rounded-xl border border-rose-300/20 bg-rose-400/10 px-3 py-2 text-xs font-semibold text-rose-50 transition hover:bg-rose-400/20">
                                                        {{ __('Quick Cancel') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </section>
        @endif
    </div>
</x-app-layout>
