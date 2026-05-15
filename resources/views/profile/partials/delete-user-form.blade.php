<section class="space-y-6">
    <header>
        <h2 class="text-xl font-semibold text-[var(--text-main)]">
            Excluir conta
        </h2>

        <p class="mt-2 text-sm leading-6 sf-text-muted">
            Ao excluir sua conta, seus dados de acessó seráo removidos permanentemente. Confirme somente se tiver certeza.
        </p>
    </header>

    <div class="rounded-2xl border border-rose-400/15 bg-rose-400/8 px-4 py-4">
        <p class="text-sm text-rose-100">
            Essa ação é permanente e não pode ser desfeita.
        </p>
    </div>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Excluir conta</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="space-y-6 p-6 sm:p-7">
            @csrf
            @method('delete')

            <div>
                <h2 class="text-xl font-semibold text-[var(--text-main)]">
                    Confirmar exclusao da conta
                </h2>

                <p class="mt-2 text-sm leading-6 sf-text-muted">
                    Para excluir sua conta permanentemente, informe sua senha atual.
                </p>
            </div>

            <div>
                <x-input-label for="password" value="Senha atual" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-2 block w-full"
                    placeholder="Senha atual"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Cancelar
                </x-secondary-button>

                <x-danger-button>
                    Excluir conta
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
