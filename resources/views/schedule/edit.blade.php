<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Minha agenda</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    {{ $isAdminView ? 'Disponibilidade de ' . $targetUser->name : 'Minha disponibilidade' }}
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">
                    Configure sua escala semanal e ajuste excecoes de datas especificas para o agendamento online.
                </p>
            </div>

            @if ($isAdminView)
                <a href="{{ route('team.index') }}" class="sf-button-secondary">
                    Voltar para equipe
                </a>
            @endif
        </div>
    </x-slot>

    <div
        x-data="{
            weekdayOptions: @js($weekdayOptions),
            workingHours: @js($workingHours),
            overrides: @js($overrides),
            addWorkingHour() {
                this.workingHours.push({ weekday: 1, start_time: '08:00', end_time: '18:00', active: true });
            },
            removeWorkingHour(index) {
                this.workingHours.splice(index, 1);
            },
            addOverride() {
                this.overrides.push({ date: '', is_day_off: false, start_time: '', end_time: '', notes: '' });
            },
            removeOverride(index) {
                this.overrides.splice(index, 1);
            }
        }"
        class="space-y-6"
    >
        @if (session('status') === 'availability-updated')
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                Disponibilidade atualizada com sucesso.
            </div>
        @endif

        <form method="POST" action="{{ $isAdminView ? route('team.availability.update', $targetUser) : route('schedule.update') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <section class="sf-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Escala semanal</h3>
                            <p class="mt-1 text-sm text-[#c7d2e3]">Adicione quantos intervalos quiser em cada dia da semana.</p>
                        </div>

                        <button type="button" class="sf-button-primary" @click="addWorkingHour()">
                            Adicionar horario
                        </button>
                    </div>

                    <div class="mt-5 space-y-4">
                        <template x-if="workingHours.length === 0">
                            <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-5 text-sm text-[#c7d2e3]">
                                Nenhum horario semanal configurado ainda.
                            </div>
                        </template>

                        <template x-for="(workingHour, index) in workingHours" :key="'working-hour-' + index">
                            <div class="rounded-2xl border border-white/10 bg-[#132746] p-4">
                                <div class="grid gap-4 md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_auto]">
                                    <div>
                                        <label class="text-sm font-medium text-white">Dia da semana</label>
                                        <select class="sf-select mt-2 block w-full" :name="`working_hours[${index}][weekday]`" x-model="workingHour.weekday">
                                            <template x-for="weekday in weekdayOptions" :key="weekday.value">
                                                <option :value="weekday.value" x-text="weekday.label"></option>
                                            </template>
                                        </select>
                                        @error('working_hours.*.weekday')
                                            <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium text-white">Inicio</label>
                                        <input type="time" class="sf-input mt-2 block w-full" :name="`working_hours[${index}][start_time]`" x-model="workingHour.start_time">
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium text-white">Fim</label>
                                        <input type="time" class="sf-input mt-2 block w-full" :name="`working_hours[${index}][end_time]`" x-model="workingHour.end_time">
                                    </div>

                                    <div class="flex items-end">
                                        <button type="button" class="inline-flex min-h-[46px] items-center justify-center rounded-xl border border-rose-300/15 bg-rose-400/10 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-rose-100 transition hover:bg-rose-400/15" @click="removeWorkingHour(index)">
                                            Remover
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        @foreach ($errors->get('working_hours.*.start_time') as $messages)
                            @foreach ($messages as $message)
                                <p class="text-sm text-rose-200">{{ $message }}</p>
                            @endforeach
                        @endforeach
                        @foreach ($errors->get('working_hours.*.end_time') as $messages)
                            @foreach ($messages as $message)
                                <p class="text-sm text-rose-200">{{ $message }}</p>
                            @endforeach
                        @endforeach
                    </div>
                </section>

                <section class="sf-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Excecoes por data</h3>
                            <p class="mt-1 text-sm text-[#c7d2e3]">Use para folgas ou horarios especiais em dias especificos.</p>
                        </div>

                        <button type="button" class="sf-button-secondary" @click="addOverride()">
                            Nova excecao
                        </button>
                    </div>

                    <div class="mt-5 space-y-4">
                        <template x-if="overrides.length === 0">
                            <div class="rounded-2xl border border-dashed border-white/10 bg-[#132746] px-4 py-5 text-sm text-[#c7d2e3]">
                                Nenhuma excecao cadastrada.
                            </div>
                        </template>

                        <template x-for="(override, index) in overrides" :key="'override-' + index">
                            <div class="rounded-2xl border border-white/10 bg-[#132746] p-4">
                                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)_minmax(0,0.8fr)_auto]">
                                    <div>
                                        <label class="text-sm font-medium text-white">Data</label>
                                        <input type="date" class="sf-input mt-2 block w-full" :name="`overrides[${index}][date]`" x-model="override.date">
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium text-white">Inicio especial</label>
                                        <input type="time" class="sf-input mt-2 block w-full" :name="`overrides[${index}][start_time]`" x-model="override.start_time" :disabled="override.is_day_off">
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium text-white">Fim especial</label>
                                        <input type="time" class="sf-input mt-2 block w-full" :name="`overrides[${index}][end_time]`" x-model="override.end_time" :disabled="override.is_day_off">
                                    </div>

                                    <div class="flex items-end">
                                        <button type="button" class="inline-flex min-h-[46px] items-center justify-center rounded-xl border border-rose-300/15 bg-rose-400/10 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-rose-100 transition hover:bg-rose-400/15" @click="removeOverride(index)">
                                            Remover
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-4 lg:grid-cols-[auto_minmax(0,1fr)] lg:items-center">
                                    <label class="inline-flex items-center gap-3 rounded-xl border border-white/10 bg-[#1b335b] px-4 py-3 text-sm text-white">
                                        <input type="hidden" :name="`overrides[${index}][is_day_off]`" value="0">
                                        <input type="checkbox" class="h-4 w-4 rounded border-white/20 bg-[#132746] text-[#d4af37] focus:ring-[#d4af37]" :name="`overrides[${index}][is_day_off]`" value="1" x-model="override.is_day_off">
                                        Marcar como folga
                                    </label>

                                    <div>
                                        <label class="text-sm font-medium text-white">Observacoes</label>
                                        <textarea rows="2" class="sf-input mt-2 block w-full" :name="`overrides[${index}][notes]`" x-model="override.notes" placeholder="Ex.: feriado, evento especial, plantao reduzido"></textarea>
                                    </div>
                                </div>
                            </div>
                        </template>

                        @foreach ($errors->get('overrides.*.date') as $messages)
                            @foreach ($messages as $message)
                                <p class="text-sm text-rose-200">{{ $message }}</p>
                            @endforeach
                        @endforeach
                        @foreach ($errors->get('overrides.*.end_time') as $messages)
                            @foreach ($messages as $message)
                                <p class="text-sm text-rose-200">{{ $message }}</p>
                            @endforeach
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Resumo</p>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-sm text-[#c7d2e3]">Profissional</p>
                            <p class="mt-2 text-base font-semibold text-white">{{ $targetUser->name }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-sm text-[#c7d2e3]">Intervalos semanais</p>
                            <p class="mt-2 text-2xl font-semibold text-white" x-text="workingHours.length"></p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-sm text-[#c7d2e3]">Excecoes futuras</p>
                            <p class="mt-2 text-2xl font-semibold text-white" x-text="overrides.length"></p>
                        </div>
                    </div>
                </section>

                <section class="sf-card p-5">
                    <h3 class="text-base font-semibold text-white">Como funciona</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-[#c7d2e3]">
                        <li>Use varios intervalos no mesmo dia para almoco ou turnos diferentes.</li>
                        <li>Marque folga para bloquear todo o dia no autoagendamento.</li>
                        <li>Horario especial substitui a escala semanal naquela data.</li>
                    </ul>
                </section>

                <button type="submit" class="sf-button-primary w-full">
                    Salvar disponibilidade
                </button>
            </aside>
        </form>
    </div>
</x-app-layout>
