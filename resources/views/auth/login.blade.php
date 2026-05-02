<x-guest-layout>
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d4af37]">StudioFlow</p>
        <p class="mt-3 text-2xl font-semibold text-white">Acesse sua operação completa</p>
        <p class="mt-2 text-sm leading-6 text-[#c7d2e3]">
            Entre para acompanhar agenda, clientes, serviços e o fluxo de agendamentos em um só lugar.
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Senha" />
            <x-text-input
                id="password"
                class="mt-1 block w-full"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4 block">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-white/20 bg-[#132746] text-[#d4af37] shadow-sm focus:ring-[#d4af37] focus:ring-offset-[#223d69]" name="remember">
                <span class="ms-2 text-sm text-[#c7d2e3]">Lembrar-me</span>
            </label>
        </div>

        <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            @if (Route::has('password.request'))
                <a class="rounded-md text-sm font-medium text-[#c7d2e3] transition hover:text-white focus:outline-none focus:ring-2 focus:ring-[#d4af37] focus:ring-offset-2 focus:ring-offset-[#223d69]" href="{{ route('password.request') }}">
                    Esqueceu sua senha?
                </a>
            @endif

            <x-primary-button class="w-full justify-center sm:ms-3 sm:w-auto">
                Entrar
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
