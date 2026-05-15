<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Minha agenda</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                    {{ $isAdminView ? 'Agenda de ' . $targetUser->name : 'Disponibilidade por data' }}
                </h2>
                <p class="mt-2 text-sm sf-text-muted">
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

        <div class="grid gap-4 xl:grid-cols-[minmax(560px,1fr)_minmax(320px,360px)]">
            <section class="sf-card p-4 sm:p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Calendário</p>
                        <h3 class="mt-2 text-2xl font-semibold text-[var(--text-main)]">{{ $selectedMonthLabel }}</h3>
                        <p class="mt-2 text-sm sf-text-muted">
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

                <div class="mt-6 grid grid-cols-7 gap-1.5 text-center text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">
                    @foreach ($weekdayHeaders as $weekdayHeader)
                        <div class="rounded-xl border border-white/6 bg-[var(--input-bg)] px-2 py-2">{{ $weekdayHeader }}</div>
                    @endforeach
                </div>

                <div class="mt-2 grid gap-1.5">
                    @foreach ($calendarWeeks as $week)
                        <div class="grid grid-cols-7 gap-1.5">
                            @foreach ($week as $day)
                                @php
                                    $buttonClasses = 'group relative min-h-[72px] overflow-hidden rounded-xl border p-2.5 text-left transition duration-150';

                                    if (! $day['is_current_month']) {
                                        $buttonClasses .= ' border-white/5 bg-[var(--input-bg)]/55 sf-text-muted hover:border-white/10 hover:bg-[var(--input-bg)]/80';
                                    } elseif ($day['is_selected']) {
                                        $buttonClasses .= ' border-[color-mix(in_srgb,var(--brand-primary)_45%,transparent)] bg-[var(--brand-primary)]/12 text-[var(--text-main)] shadow-[0_14px_28px_color-mix(in_srgb,var(--brand-primary)_22%,transparent)]';
                                    } else {
                                        $buttonClasses .= ' border-white/8 bg-[var(--input-bg)] text-[var(--text-main)] hover:border-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)] hover:bg-[color-mix(in_srgb,var(--input-bg)_75%,black)]';
                                    }
                                @endphp

                                <a
                                    href="{{ request()->fullUrlWithQuery(['month' => $selectedMonth, 'date' => $day['date']]) }}"
                                    class="{{ $buttonClasses }}"
                                >
                                    @if ($day['is_today'])
                                        <span
                                            class="absolute right-2 top-2 h-2.5 w-2.5 rounded-full bg-[var(--brand-primary)] shadow-[0_0_0_4px_color-mix(in_srgb,var(--brand-primary)_18%,transparent)]"
                                            title="Hoje"
                                        >
                                        </span>
                                    @endif

                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-[10px] uppercase tracking-[0.14em] {{ $day['is_current_month'] ? 'sf-text-muted' : 'text-[color-mix(in_srgb,var(--text-muted)_72%,var(--text-main))]' }}">
                                                {{ $day['label'] }}
                                            </p>
                                            <p class="mt-1.5 text-lg font-semibold leading-none">{{ $day['day_number'] }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-1.5">
                                        @if ($day['status'] === 'configured')
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-[var(--brand-primary)] shadow-[0_0_0_4px_color-mix(in_srgb,var(--brand-primary)_16%,transparent)]"></span>
                                            <span class="text-[11px] leading-4 sf-text-muted">Horários salvos</span>
                                        @elseif ($day['status'] === 'day_off')
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-rose-300 shadow-[0_0_0_4px_rgba(251,113,133,0.12)]"></span>
                                            <span class="text-[11px] leading-4 sf-text-muted">Folga</span>
                                        @else
                                            <span class="text-[11px] leading-4 sf-text-muted">Escala base</span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>

            <aside class="space-y-4">
                <section class="sf-card p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Dia selecionado</p>
                            <h3 class="mt-1.5 text-xl font-semibold text-[var(--text-main)]">{{ $selectedDateLabel }}</h3>
                            <p class="mt-2 text-sm sf-text-muted">
                                Configure os turnos reais desse dia. Ex: 08:00-11:00 e 13:00-19:00.
                            </p>
                        </div>

                        @if ($hasSpecificConfiguration)
                            <span class="rounded-full border border-[color-mix(in_srgb,var(--brand-primary)_20%,transparent)] bg-[var(--brand-primary)]/12 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] brand-text">
                                Configurado
                            </span>
                        @else
                            <span class="rounded-full border border-white/10 bg-[var(--input-bg)] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] sf-text-muted">
                                Escala semanal
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <div class="rounded-xl border border-white/8 bg-[var(--input-bg)] px-3 py-3">
                            <p class="text-sm sf-text-muted">Dias configurados no mês</p>
                            <p class="mt-1.5 text-xl font-semibold text-[var(--text-main)]">{{ $configuredDaysCount }}</p>
                        </div>
                        <div class="rounded-xl border border-white/8 bg-[var(--input-bg)] px-3 py-3">
                            <p class="text-sm sf-text-muted">Folgas no mês</p>
                            <p class="mt-1.5 text-xl font-semibold text-[var(--text-main)]">{{ $dayOffCount }}</p>
                        </div>
                    </div>
                </section>

                <form method="POST" action="{{ $updateRoute }}" class="sf-card p-4 sm:p-5">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="date" value="{{ $selectedDate }}">
                    <input type="hidden" name="works_this_day" x-model="worksThisDay">

                    <div>
                        <h3 class="text-lg font-semibold text-[var(--text-main)]">Configuração do dia</h3>
                        <p class="mt-2 text-sm sf-text-muted">
                            Adicione até dois turnos. Para folga total, marque o dia como folga.
                        </p>
                    </div>

                    <div class="mt-4 grid gap-2">
                        <button
                            type="button"
                            class="flex items-center justify-between rounded-xl border px-3 py-3 text-left transition"
                            :class="worksThisDay === '1' ? 'border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)] bg-[var(--brand-primary)]/12 text-[var(--text-main)]' : 'border-white/8 bg-[var(--input-bg)] sf-text-muted'"
                            @click="worksThisDay = '1'"
                        >
                            <span>
                                <span class="block text-sm font-semibold">Trabalha neste dia</span>
                                <span class="mt-1 block text-xs sf-text-muted">Libera horários apenas dentro dos turnos salvos.</span>
                            </span>
                            <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="worksThisDay === '1' ? 'bg-[var(--brand-primary)] text-[var(--brand-on-primary)]' : 'bg-white/8 sf-text-muted'">
                                Sim
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex items-center justify-between rounded-xl border px-3 py-3 text-left transition"
                            :class="worksThisDay === '0' ? 'border-rose-300/25 bg-rose-400/10 text-[var(--text-main)]' : 'border-white/8 bg-[var(--input-bg)] sf-text-muted'"
                            @click="worksThisDay = '0'"
                        >
                            <span>
                                <span class="block text-sm font-semibold">Folga neste dia</span>
                                <span class="mt-1 block text-xs sf-text-muted">Nenhum horário será exibido no agendamento online.</span>
                            </span>
                            <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="worksThisDay === '0' ? 'bg-rose-300 text-[var(--brand-on-primary)]' : 'bg-white/8 sf-text-muted'">
                                Não
                            </span>
                        </button>
                    </div>

                    <div x-show="worksThisDay === '1'" x-cloak class="mt-4 space-y-3">
                        <div class="rounded-xl border border-white/8 bg-[var(--input-bg)] p-3">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-[var(--text-main)]">Turno 1</p>
                                    <p class="mt-1 text-xs sf-text-muted">Obrigatorio quando houver atendimento no dia.</p>
                                </div>
                                <span class="rounded-full border border-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)] bg-[var(--brand-primary)]/12 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] brand-text">
                                    Principal
                                </span>
                            </div>

                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-[var(--text-main)]">Início do turno 1</label>
                                    <input type="time" name="intervals[0][start_time]" value="{{ old('intervals.0.start_time', $currentIntervals[0]['start_time'] ?? '') }}" class="sf-input mt-2 block w-full" :disabled="worksThisDay === '0'">
                                    @error('intervals.0.start_time')
                                        <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-[var(--text-main)]">Fim do turno 1</label>
                                    <input type="time" name="intervals[0][end_time]" value="{{ old('intervals.0.end_time', $currentIntervals[0]['end_time'] ?? '') }}" class="sf-input mt-2 block w-full" :disabled="worksThisDay === '0'">
                                    @error('intervals.0.end_time')
                                        <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/8 bg-[var(--input-bg)] p-3">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-[var(--text-main)]">Turno 2</p>
                                    <p class="mt-1 text-xs sf-text-muted">Opcional para pausar almoco ou abrir horário noturno.</p>
                                </div>
                                <span class="rounded-full border border-white/10 bg-[var(--app-shell-bg)] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] sf-text-muted">
                                    Opcional
                                </span>
                            </div>

                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-[var(--text-main)]">Início do turno 2</label>
                                    <input type="time" name="intervals[1][start_time]" value="{{ old('intervals.1.start_time', $currentIntervals[1]['start_time'] ?? '') }}" class="sf-input mt-2 block w-full" :disabled="worksThisDay === '0'">
                                    @error('intervals.1.start_time')
                                        <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-[var(--text-main)]">Fim do turno 2</label>
                                    <input type="time" name="intervals[1][end_time]" value="{{ old('intervals.1.end_time', $currentIntervals[1]['end_time'] ?? '') }}" class="sf-input mt-2 block w-full" :disabled="worksThisDay === '0'">
                                    @error('intervals.1.end_time')
                                        <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="worksThisDay === '0'" x-cloak class="mt-4 rounded-xl border border-rose-300/18 bg-rose-400/10 px-3 py-3 text-sm text-rose-100">
                        Este dia será tratado como folga total e não exibirá horários no agendamento online.
                    </div>

                    <div class="mt-4">
                        <label class="text-sm font-medium text-[var(--text-main)]">Observações</label>
                        <textarea name="notes" rows="3" class="sf-input mt-2 block w-full" placeholder="Ex.: horário reduzido, atendimento externo, plantão especial">{{ old('notes', $selectedDayState['notes'] ?? '') }}</textarea>
                        @error('notes')
                            <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('date')
                        <p class="mt-4 text-sm text-rose-200">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 flex flex-col gap-2">
                        <button type="submit" class="sf-button-primary w-full">
                            Salvar dia
                        </button>
                        <button type="submit" class="sf-button-secondary w-full !border-rose-300/15 !bg-rose-400/10 !text-rose-100 hover:!bg-rose-400/15" @click="worksThisDay = '0'">
                            Marcar como folga
                        </button>
                    </div>
                </form>

                <section class="sf-card p-4 sm:p-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">Escala semanal de base</h3>
                    <p class="mt-2 text-sm sf-text-muted">
                        Se este dia não tiver configuração própria, o autoagendamento usa estes blocos base.
                    </p>

                    <div class="mt-3 space-y-2">
                        @forelse ($weeklyFallbackBlocks as $block)
                            <div class="rounded-xl border border-white/8 bg-[var(--input-bg)] px-3 py-3">
                                <p class="text-sm font-semibold text-[var(--text-main)]">{{ $block['start_time'] }} as {{ $block['end_time'] }}</p>
                                <p class="mt-1 text-xs sf-text-muted">Escala semanal existente</p>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-white/10 bg-[var(--input-bg)] px-4 py-5 text-sm sf-text-muted">
                                Nenhum turno semanal cadastrado para este dia. Se limpar a configuração, ele ficará sem horários.
                            </div>
                        @endforelse
                    </div>

                    @if ($hasSpecificConfiguration)
                        <form method="POST" action="{{ $clearRoute }}" class="mt-5">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="date" value="{{ $selectedDate }}">

                            <button type="submit" class="sf-button-ghost w-full !border-white/10 !sf-text-muted hover:!border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)] hover:!text-[var(--text-main)]">
                                Limpar configuração do dia
                            </button>
                        </form>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
