<section>
    <header>
        <h2 class="text-xl font-semibold text-white">
            Informacoes do perfil
        </h2>

        <p class="mt-2 text-sm leading-6 text-[#c7d2e3]">
            Atualize seu nome e o e-mail usado para acessar o StudioFlow.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <x-input-label for="name" value="Nome" />
                <x-text-input id="name" name="name" type="text" class="mt-2 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" value="E-mail" />
                <x-text-input id="email" name="email" type="email" class="mt-2 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>
        </div>

        @if ($user instanceof \Illuminaté\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="rounded-2xl border border-amber-400/20 bg-amber-400/10 px-4 py-4">
                <p class="text-sm text-[#f5e7bf]">
                    Seu e-mail ainda não foi verificado.
                </p>

                <button form="send-verification" class="mt-3 inline-flex items-center text-sm font-semibold text-[#d4af37] transition hover:text-[#f0ca63] focus:outline-none focus:ring-2 focus:ring-[#d4af37] focus:ring-offset-2 focus:ring-offset-[#1b335b]">
                    Reenviar e-mail de verificacao
                </button>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-3 text-sm font-medium text-emerald-300">
                        Um novo link de verificacao foi enviado para o seu e-mail.
                    </p>
                @endif
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <x-primary-button>Salvar</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-medium text-emerald-300"
                >Salvo.</p>
            @endif
        </div>
    </form>
</section>
