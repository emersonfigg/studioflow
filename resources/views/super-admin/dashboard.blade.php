<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Super Admin</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Painel Global
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Acompanhe empresas, usuários, agendamentos e receita do StudioFlow inteiro.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Total de empresas</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $totalCompanies }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Empresas ativas</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $activeCompanies }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Total de usuários</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $totalUsers }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Agendamentos do mês</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $appointmentsThisMonth }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Receita do mês</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($revenueThisMonth, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="sf-card overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-white/10 px-5 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-white">Empresas recentes</h3>
                    <p class="mt-1 text-sm text-[#c7d2e3]">Visao rapida de atividade, usuários e receita por conta.</p>
                </div>
                <a href="{{ route('super-admin.companies.index') }}" class="sf-button-secondary">Ver todas</a>
            </div>

            <div class="divide-y divide-white/10">
                @forelse ($latestCompanies as $company)
                    <div class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(0,0.7fr))_auto] lg:items-center">
                        <div>
                            <p class="text-base font-semibold text-white">{{ $company->name }}</p>
                            <p class="mt-1 text-sm text-[#c7d2e3]">{{ $company->active ? 'Conta ativa' : 'Conta inativa' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">Usuários</p>
                            <p class="mt-1 text-sm font-semibold text-white">{{ $company->users_count }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">Agendamentos</p>
                            <p class="mt-1 text-sm font-semibold text-white">{{ $company->appointments_count }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">Receita</p>
                            <p class="mt-1 text-sm font-semibold text-white">R$ {{ number_format((float) ($company->payments_sum_gross_amount ?? 0), 2, ',', '.') }}</p>
                        </div>
                        <div class="lg:text-right">
                            <a href="{{ route('super-admin.companies.show', $company) }}" class="sf-button-ghost">Detalhes</a>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-[#c7d2e3]">
                        Nenhuma empresa encontrada.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
