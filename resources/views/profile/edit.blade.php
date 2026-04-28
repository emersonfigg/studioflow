<x-app-layout>
    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="sf-card overflow-hidden px-5 py-6 sm:px-7">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="inline-flex items-center rounded-full border border-[#d4af37]/25 bg-[#d4af37]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-[#d4af37]">
                            Conta
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                            Perfil
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm leading-7 text-[#c7d2e3] sm:text-base">
                            Gerencie seus dados de acesso e seguranca.
                        </p>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                <div class="space-y-6">
                    <div class="sf-card rounded-[24px] border border-white/10 p-5 shadow-sm sm:p-7">
                        @include('profile.partials.update-profile-information-form')
                    </div>

                    <div class="sf-card rounded-[24px] border border-white/10 p-5 shadow-sm sm:p-7">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="sf-card rounded-[24px] border border-rose-400/15 p-5 shadow-sm sm:p-7">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
