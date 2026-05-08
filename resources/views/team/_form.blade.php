@php
    $member = $member ?? null;
    $selectedRole = old('role', $member?->role ?? 'staff');
    $selectedCommissionType = old('commission_type', $member?->commission_type ?? 'none');
    $selectedActive = old('active', $member ? (int) $member->active : 1);
    $selectedScheduleType = old('schedule_type', $member?->schedule_type ?? 'fixed');
    $existingWorkingHours = $member ? $member->workingHours()->orderBy('weekday')->orderBy('start_time')->get() : collect();
    $fixedWeekdays = old('fixed_weekdays', $existingWorkingHours->pluck('weekday')->unique()->values()->all() ?: [1, 2, 3, 4, 5, 6]);
    $fixedIntervals = old('fixed_intervals', $existingWorkingHours
        ->map(fn ($hour) => [
            'start_time' => substr((string) $hour->start_time, 0, 5),
            'end_time' => substr((string) $hour->end_time, 0, 5),
        ])
        ->unique(fn ($interval) => $interval['start_time'].'-'.$interval['end_time'])
        ->values()
        ->all() ?: [
            ['start_time' => '08:00', 'end_time' => '11:00'],
            ['start_time' => '13:00', 'end_time' => '18:00'],
        ]);
    $weekdayOptions = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 0 => 'Dom'];
@endphp

<div class="grid gap-5 lg:grid-cols-2" x-data="{ scheduleType: @js($selectedScheduleType) }">
    <div class="lg:col-span-2">
        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
            <div class="flex items-center gap-4">
                @if ($member?->photo_url)
                    <img src="{{ $member->photo_url }}" alt="Foto de {{ $member->name }}" class="h-20 w-20 rounded-2xl object-cover ring-1 ring-white/10">
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-2xl font-semibold text-[#d4af37]">
                        {{ $member?->avatar_initial ?? 'P' }}
                    </div>
                @endif

                <div>
                    <p class="text-sm font-medium text-white">Foto do profissional</p>
                    <p class="mt-1 text-sm text-[#c7d2e3]">Envie JPG, JPEG, PNG ou WEBP com até 2MB.</p>
                </div>
            </div>

            <div class="mt-4">
                <label for="photo" class="text-sm font-medium text-white">Upload da foto</label>
                <input id="photo" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="sf-input mt-2 block w-full px-3 py-3">
                <x-input-error class="mt-2" :messages="$errors->get('photo')" />
            </div>
        </div>
    </div>

    <div>
        <label for="name" class="text-sm font-medium text-white">Nome</label>
        <input id="name" name="name" type="text" value="{{ old('name', $member?->name) }}" class="sf-input mt-2 block w-full" required>
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <label for="email" class="text-sm font-medium text-white">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email', $member?->email) }}" class="sf-input mt-2 block w-full" required>
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>

    <div>
        <label for="password" class="text-sm font-medium text-white">{{ $member ? 'Nova senha' : 'Senha' }}</label>
        <input id="password" name="password" type="password" class="sf-input mt-2 block w-full" @required(! $member)>
        <p class="mt-2 text-xs text-[#c7d2e3]">
            {{ $member ? 'Preencha apenas se quiser alterar a senha atual.' : 'Defina uma senha inicial para o profissional.' }}
        </p>
        <x-input-error class="mt-2" :messages="$errors->get('password')" />
    </div>

    <div>
        <label for="role" class="text-sm font-medium text-white">Perfil</label>
        <select id="role" name="role" class="sf-select mt-2 block w-full" required>
            <option value="admin" @selected($selectedRole === 'admin')>Administrador</option>
            <option value="financial" @selected($selectedRole === 'financial')>Financeiro</option>
            <option value="staff" @selected($selectedRole === 'staff')>Profissional</option>
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('role')" />
    </div>

    <div>
        <label for="commission_type" class="text-sm font-medium text-white">Tipo de comissão</label>
        <select id="commission_type" name="commission_type" class="sf-select mt-2 block w-full">
            <option value="none" @selected($selectedCommissionType === 'none' || $selectedCommissionType === null)>Sem comissão</option>
            <option value="percent" @selected($selectedCommissionType === 'percent')>Percentual</option>
            <option value="fixed" @selected($selectedCommissionType === 'fixed')>Valor fixo</option>
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('commission_type')" />
    </div>

    <div>
        <label for="commission_value" class="text-sm font-medium text-white">Valor da comissão</label>
        <input id="commission_value" name="commission_value" type="text" inputmode="decimal" placeholder="R$ 0,00" value="{{ old('commission_value', $member?->commission_value !== null ? \App\Support\BrazilianCurrency::input($member->commission_value) : null) }}" class="sf-input mt-2 block w-full">
        <p class="mt-2 text-xs text-[#c7d2e3]">Use percentual sem o símbolo % ou valor fixo no padrão R$ 0,00.</p>
        <x-input-error class="mt-2" :messages="$errors->get('commission_value')" />
    </div>

    <div class="lg:col-span-2 rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Agenda do profissional</p>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <label class="rounded-2xl border px-4 py-4 transition" :class="scheduleType === 'fixed' ? 'border-[#d4af37] bg-[#d4af37]/10' : 'border-white/10 bg-[#0f203b]'">
                <input type="radio" name="schedule_type" value="fixed" class="sr-only" x-model="scheduleType">
                <span class="text-sm font-semibold text-white">Agenda fixa</span>
                <span class="mt-1 block text-sm leading-6 text-[#c7d2e3]">Use os mesmos dias e horários toda semana.</span>
            </label>
            <label class="rounded-2xl border px-4 py-4 transition" :class="scheduleType === 'dynamic' ? 'border-[#d4af37] bg-[#d4af37]/10' : 'border-white/10 bg-[#0f203b]'">
                <input type="radio" name="schedule_type" value="dynamic" class="sr-only" x-model="scheduleType">
                <span class="text-sm font-semibold text-white">Agenda dinâmica</span>
                <span class="mt-1 block text-sm leading-6 text-[#c7d2e3]">O profissional libera cada dia e horário em Minha agenda.</span>
            </label>
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('schedule_type')" />

        <div x-show="scheduleType === 'fixed'" x-cloak class="mt-5 space-y-5">
            <div>
                <p class="text-sm font-medium text-white">Dias fixos</p>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                    @foreach ($weekdayOptions as $weekday => $label)
                        <label class="flex items-center gap-2 rounded-xl border border-white/10 bg-[#0f203b] px-3 py-2 text-sm text-white">
                            <input type="checkbox" name="fixed_weekdays[]" value="{{ $weekday }}" class="rounded border-white/10 bg-[#1b335b] text-[#d4af37] focus:ring-[#d4af37]" @checked(in_array($weekday, array_map('intval', (array) $fixedWeekdays), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('fixed_weekdays')" />
            </div>

            <div>
                <p class="text-sm font-medium text-white">Turnos fixos</p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @for ($i = 0; $i < 2; $i++)
                        @php($interval = $fixedIntervals[$i] ?? ['start_time' => '', 'end_time' => ''])
                        <div class="rounded-2xl border border-white/10 bg-[#0f203b] px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Turno {{ $i + 1 }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">Início</label>
                                    <input type="time" name="fixed_intervals[{{ $i }}][start_time]" value="{{ $interval['start_time'] ?? '' }}" class="sf-input mt-1 block w-full">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c7d2e3]">Fim</label>
                                    <input type="time" name="fixed_intervals[{{ $i }}][end_time]" value="{{ $interval['end_time'] ?? '' }}" class="sf-input mt-1 block w-full">
                                </div>
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('fixed_intervals.'.$i.'.start_time')" />
                            <x-input-error class="mt-2" :messages="$errors->get('fixed_intervals.'.$i.'.end_time')" />
                        </div>
                    @endfor
                </div>
                <p class="mt-3 text-xs text-[#c7d2e3]">Exemplo: segunda a sábado das 08:00 as 11:00 e das 13:00 as 18:00.</p>
            </div>
        </div>
    </div>
</div>

<div class="mt-5 rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
    <div class="flex items-start gap-3">
        <input name="active" type="hidden" value="0">
        <input id="active" name="active" type="checkbox" value="1" class="mt-1 h-4 w-4 rounded border-white/20 bg-[#1b335b] text-[#d4af37] focus:ring-[#d4af37]" @checked((string) $selectedActive === '1')>
        <div>
            <label for="active" class="text-sm font-medium text-white">Profissional ativo</label>
            <p class="mt-1 text-sm text-[#c7d2e3]">Profissionais inativos deixam de aparecer no autoagendamento e na agenda operacional.</p>
        </div>
    </div>
    <x-input-error class="mt-2" :messages="$errors->get('active')" />
</div>
