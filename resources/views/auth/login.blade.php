<x-guest-layout>
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] brand-text">StudioFlow</p>
        <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">Acesse sua operação completa</p>
        <p class="mt-2 text-sm leading-6 sf-text-muted">
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
                <input id="remember_me" type="checkbox" class="rounded border-white/20 bg-[var(--input-bg)] brand-text shadow-sm focus:ring-[var(--brand-primary)] focus:ring-offset-[var(--app-shell-bg)]" name="remember">
                <span class="ms-2 text-sm sf-text-muted">Lembrar-me</span>
            </label>
        </div>

        <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            @if (Route::has('password.request'))
                <a class="rounded-md text-sm font-medium sf-text-muted transition hover:text-[var(--text-main)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)] focus:ring-offset-2 focus:ring-offset-[var(--app-shell-bg)]" href="{{ route('password.request') }}">
                    Esqueceu sua senha?
                </a>
            @endif

            <x-primary-button class="w-full justify-center sm:ms-3 sm:w-auto">
                Entrar
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
