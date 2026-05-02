@php
    $member = $member ?? null;
    $selectedRole = old('role', $member?->role ?? 'staff');
    $selectedCommissionType = old('commission_type', $member?->commission_type ?? 'none');
    $selectedActive = old('active', $member ? (int) $member->active : 1);
@endphp

<div class="grid gap-5 lg:grid-cols-2">
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
                <input
                    id="photo"
                    name="photo"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="sf-input mt-2 block w-full px-3 py-3"
                >
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
        <input
            id="commission_value"
            name="commission_value"
            type="text"
            inputmode="decimal"
            placeholder="R$ 0,00"
            value="{{ old('commission_value', $member?->commission_value !== null ? \App\Support\BrazilianCurrency::input($member->commission_value) : null) }}"
            class="sf-input mt-2 block w-full"
        >
        <p class="mt-2 text-xs text-[#c7d2e3]">Use percentual sem o símbolo % ou valor fixo no padrão R$ 0,00.</p>
        <x-input-error class="mt-2" :messages="$errors->get('commission_value')" />
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
