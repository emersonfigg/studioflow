<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Minha agenda</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    {{ $isAdminView ? 'Agenda de ' . $targetUser->name : 'Disponibilidade por data' }}
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">
                    Clique em um dia do calendário para configurar os turnos daquele atendimento. Quando não houver configuração específica, a agenda continua usando a escala semanal existente como base.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                @if ($isAdminView)
                    <a href="{{ route('team.index') }}" class="sf-button-secondary">
                        Voltar para equipe
                    </a>
                @endif
                <a href="{{ route('public-bookings.create', ['company' => $targetUser->company_id, 'user_id' => $targetUser->id, 'date' => $selectedDate, 'filters_submitted' => 1]) }}" class="sf-button-secondary">
                    Ver no agendamento público
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $weekdayHeaders = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'];
        $updateRoute = $isAdminView
            ? route('team.availability.update', ['team' => $targetUser, 'month' => $selectedMonth, 'date' => $selectedDate])
            : route('schedule.update', ['month' => $selectedMonth, 'date' => $selectedDate]);
        $clearRoute = $isAdminView
            ? route('team.availability.clear', ['team' => $targetUser, 'month' => $selectedMonth, 'date' => $selectedDate])
            : route('schedule.clear', ['month' => $selectedMonth, 'date' => $selectedDate]);
        $currentIntervals = $selectedDayState['intervals'] ?? [
            ['start_time' => '', 'end_time' => ''],
            ['start_time' => '', 'end_time' => ''],
        ];
    @endphp

    <div x-data="{ worksThisDay: '{{ $selectedDayState['works_this_day'] ? '1' : '0' }}' }" class="space-y-6">
        @if (session('status') === 'availability-updated')
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                Dia atualizado com sucesso.
            </div>
        @endif

        @if (session('status') === 'availability-cleared')
            <div class="rounded-2xl border border-sky-300/20 bg-sky-500/10 px-5 py-4 text-sm text-sky-100">
                Configuração removida. Este dia voltou a usar a escala semanal como base.
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_420px]">
            <section class="sf-card p-5 sm:p-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Calendário</p>
                        <h3 class="mt-2 text-2xl font-semibold text-white">{{ $selectedMonthLabel }}</h3>
                        <p class="mt-2 text-sm text-[#c7d2e3]">
                            Dias dourados possuem horários configurados. Dias vermelhos/cinza estáo marcados como folga.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="{{ request()->fullUrlWithQuery(['month' => $previousMonth, 'date' => \Carbon\CarbonImmutable::parse($previousMonth . '-01')->startOfMonth()->toDateString()]) }}" class="sf-button-secondary !px-4">
                            Mês anterior
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['month' => $nextMonth, 'date' => \Carbon\CarbonImmutable::parse($nextMonth . '-01')->startOfMonth()->toDateString()]) }}" class="sf-button-secondary !px-4">
                            Próximo mês
                        </a>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">
                    @foreach ($weekdayHeaders as $weekdayHeader)
                        <div class="rounded-xl border border-white/6 bg-[#132746] px-2 py-3">{{ $weekdayHeader }}</div>
                    @endforeach
                </div>

                <div class="mt-3 grid gap-2">
                    @foreach ($calendarWeeks as $week)
                        <div class="grid grid-cols-7 gap-2">
                            @foreach ($week as $day)
                                @php
                                    $buttonClasses = 'group relative min-h-[88px] overflow-hidden rounded-2xl border p-3 text-left transition duration-150';

                                    if (! $day['is_current_month']) {
                                        $buttonClasses .= ' border-white/5 bg-[#132746]/55 text-[#7f94b4] hover:border-white/10 hover:bg-[#132746]/80';
                                    } elseif ($day['is_selected']) {
                                        $buttonClasses .= ' border-[#d4af37]/45 bg-[#d4af37]/12 text-white shadow-[0_14px_28px_rgba(212,175,55,0.14)]';
                                    } else {
                                        $buttonClasses .= ' border-white/8 bg-[#132746] text-white hover:border-[#d4af37]/25 hover:bg-[#183055]';
                                    }
                                @endphp

                                <a
                                    href="{{ request()->fullUrlWithQuery(['month' => $selectedMonth, 'date' => $day['date']]) }}"
                                    class="{{ $buttonClasses }}"
                                >
                                    @if ($day['is_today'])
                                        <span
                                            class="absolute right-2 top-2 h-2.5 w-2.5 rounded-full bg-[#d4af37] shadow-[0_0_0_4px_rgba(212,175,55,0.14)]"
                                            title="Hoje"
                                        >
                                        </span>
                                    @endif

                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-[11px] uppercase tracking-[0.18em] {{ $day['is_current_month'] ? 'text-[#c7d2e3]' : 'text-[#6f84a8]' }}">
                                                {{ $day['label'] }}
                                            </p>
                                            <p class="mt-2 text-xl font-semibold">{{ $day['day_number'] }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex items-center gap-2">
                                        @if ($day['status'] === 'configured')
                                            <span class="h-2.5 w-2.5 rounded-full bg-[#d4af37] shadow-[0_0_0_5px_rgba(212,175,55,0.12)]"></span>
                                            <span class="text-xs text-[#d8e1f1]">Horários salvos</span>
                                        @elseif ($day['status'] === 'day_off')
                                            <span class="h-2.5 w-2.5 rounded-full bg-rose-300 shadow-[0_0_0_5px_rgba(251,113,133,0.12)]"></span>
                                            <span class="text-xs text-[#d8e1f1]">Folga</span>
                                        @else
                                            <span class="text-xs text-[#8fa5c7]">Usando escala base</span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>

            <aside class="space-y-6">
                <section class="sf-card p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Dia selecionado</p>
                            <h3 class="mt-2 text-2xl font-semibold text-white">{{ $selectedDateLabel }}</h3>
                            <p class="mt-2 text-sm text-[#c7d2e3]">
                                Configure os turnos reais desse dia. Ex: 08:00-11:00 e 13:00-19:00.
                            </p>
                        </div>

                        @if ($hasSpecificConfiguration)
                            <span class="rounded-full border border-[#d4af37]/20 bg-[#d4af37]/12 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#d4af37]">
                                Configurado
                            </span>
                        @else
                            <span class="rounded-full border border-white/10 bg-[#132746] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">
                                Escala semanal
                            </span>
                        @endif
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/8 bg-[#132746] px-4 py-4">
                            <p class="text-sm text-[#c7d2e3]">Dias configurados no mês</p>
                            <p class="mt-2 text-2xl font-semibold text-white">{{ $configuredDaysCount }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/8 bg-[#132746] px-4 py-4">
                            <p class="text-sm text-[#c7d2e3]">Folgas no mês</p>
                            <p class="mt-2 text-2xl font-semibold text-white">{{ $dayOffCount }}</p>
                        </div>
                    </div>
                </section>

                <form method="POST" action="{{ $updateRoute }}" class="sf-card p-5 sm:p-6">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="date" value="{{ $selectedDate }}">
                    <input type="hidden" name="works_this_day" x-model="worksThisDay">

                    <div>
                        <h3 class="text-lg font-semibold text-white">Configuração do dia</h3>
                        <p class="mt-2 text-sm text-[#c7d2e3]">
                            Adicione até dois turnos. Para folga total, marque o dia como folga.
                        </p>
                    </div>

                    <div class="mt-5 grid gap-3">
                        <button
                            type="button"
                            class="flex items-center justify-between rounded-2xl border px-4 py-4 text-left transition"
                            :class="worksThisDay === '1' ? 'border-[#d4af37]/35 bg-[#d4af37]/12 text-white' : 'border-white/8 bg-[#132746] text-[#c7d2e3]'"
                            @click="worksThisDay = '1'"
                        >
                            <span>
                                <span class="block text-sm font-semibold">Trabalha neste dia</span>
                                <span class="mt-1 block text-xs text-[#c7d2e3]">Libera horários apenas dentro dos turnos salvos.</span>
                            </span>
                            <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="worksThisDay === '1' ? 'bg-[#d4af37] text-[#132746]' : 'bg-white/8 text-[#c7d2e3]'">
                                Sim
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex items-center justify-between rounded-2xl border px-4 py-4 text-left transition"
                            :class="worksThisDay === '0' ? 'border-rose-300/25 bg-rose-400/10 text-white' : 'border-white/8 bg-[#132746] text-[#c7d2e3]'"
                            @click="worksThisDay = '0'"
                        >
                            <span>
                                <span class="block text-sm font-semibold">Folga neste dia</span>
                                <span class="mt-1 block text-xs text-[#c7d2e3]">Nenhum horário será exibido no agendamento online.</span>
                            </span>
                            <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="worksThisDay === '0' ? 'bg-rose-300 text-[#132746]' : 'bg-white/8 text-[#c7d2e3]'">
                                Não
                            </span>
                        </button>
                    </div>

                    <div x-show="worksThisDay === '1'" x-cloak class="mt-6 space-y-4">
                        <div class="rounded-2xl border border-white/8 bg-[#132746] p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-white">Turno 1</p>
                                    <p class="mt-1 text-xs text-[#c7d2e3]">Obrigatorio quando houver atendimento no dia.</p>
                                </div>
                                <span class="rounded-full border border-[#d4af37]/25 bg-[#d4af37]/12 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[#d4af37]">
                                    Principal
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-white">Início do turno 1</label>
                                    <input type="time" name="intervals[0][start_time]" value="{{ old('intervals.0.start_time', $currentIntervals[0]['start_time'] ?? '') }}" class="sf-input mt-2 block w-full" :disabled="worksThisDay === '0'">
                                    @error('intervals.0.start_time')
                                        <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-white">Fim do turno 1</label>
                                    <input type="time" name="intervals[0][end_time]" value="{{ old('intervals.0.end_time', $currentIntervals[0]['end_time'] ?? '') }}" class="sf-input mt-2 block w-full" :disabled="worksThisDay === '0'">
                                    @error('intervals.0.end_time')
                                        <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-white/8 bg-[#132746] p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-white">Turno 2</p>
                                    <p class="mt-1 text-xs text-[#c7d2e3]">Opcional para pausar almoco ou abrir horário noturno.</p>
                                </div>
                                <span class="rounded-full border border-white/10 bg-[#1b335b] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">
                                    Opcional
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-white">Início do turno 2</label>
                                    <input type="time" name="intervals[1][start_time]" value="{{ old('intervals.1.start_time', $currentIntervals[1]['start_time'] ?? '') }}" class="sf-input mt-2 block w-full" :disabled="worksThisDay === '0'">
                                    @error('intervals.1.start_time')
                                        <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-white">Fim do turno 2</label>
                                    <input type="time" name="intervals[1][end_time]" value="{{ old('intervals.1.end_time', $currentIntervals[1]['end_time'] ?? '') }}" class="sf-input mt-2 block w-full" :disabled="worksThisDay === '0'">
                                    @error('intervals.1.end_time')
                                        <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="worksThisDay === '0'" x-cloak class="mt-6 rounded-2xl border border-rose-300/18 bg-rose-400/10 px-4 py-4 text-sm text-rose-100">
                        Este dia será tratado como folga total e não exibirá horários no agendamento online.
                    </div>

                    <div class="mt-6">
                        <label class="text-sm font-medium text-white">Observações</label>
                        <textarea name="notes" rows="3" class="sf-input mt-2 block w-full" placeholder="Ex.: horário reduzido, atendimento externo, plantão especial">{{ old('notes', $selectedDayState['notes'] ?? '') }}</textarea>
                        @error('notes')
                            <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('date')
                        <p class="mt-4 text-sm text-rose-200">{{ $message }}</p>
                    @enderror

                    <div class="mt-6 flex flex-col gap-3">
                        <button type="submit" class="sf-button-primary w-full">
                            Salvar dia
                        </button>
                        <button type="submit" class="sf-button-secondary w-full !border-rose-300/15 !bg-rose-400/10 !text-rose-100 hover:!bg-rose-400/15" @click="worksThisDay = '0'">
                            Marcar como folga
                        </button>
                    </div>
                </form>

                <section class="sf-card p-5 sm:p-6">
                    <h3 class="text-base font-semibold text-white">Escala semanal de base</h3>
                    <p class="mt-2 text-sm text-[#c7d2e3]">
                        Se este dia não tiver configuração própria, o autoagendamento usa estes blocos base.
                    </p>

                    <div class="mt-4 space-y-3">
                        @forelse ($weeklyFallbackBlocks as $block)
                            <div class="rounded-2xl border border-white/8 bg-[#132746] px-4 py-4">
                                <p class="text-sm font-semibold text-white">{{ $block['start_time'] }} as {{ $block['end_time'] }}</p>
                                <p class="mt-1 text-xs text-[#c7d2e3]">Escala semanal existente</p>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-5 text-sm text-[#c7d2e3]">
                                Nenhum turno semanal cadastrado para este dia. Se limpar a configuração, ele ficará sem horários.
                            </div>
                        @endforelse
                    </div>

                    @if ($hasSpecificConfiguration)
                        <form method="POST" action="{{ $clearRoute }}" class="mt-5">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="date" value="{{ $selectedDate }}">

                            <button type="submit" class="sf-button-ghost w-full !border-white/10 !text-[#c7d2e3] hover:!border-[#d4af37]/35 hover:!text-white">
                                Limpar configuração do dia
                            </button>
                        </form>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
