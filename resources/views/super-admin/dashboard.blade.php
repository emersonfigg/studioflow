<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Super Admin</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                    Painel Global
                </h2>
                <p class="mt-2 text-sm sf-text-muted">Acompanhe empresas, usuários, agendamentos e receita do StudioFlow inteiro.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Total de empresas</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $totalCompanies }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Empresas ativas</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $activeCompanies }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Total de usuários</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $totalUsers }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Agendamentos do mês</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $appointmentsThisMonth }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Receita do mês</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($revenueThisMonth, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="sf-card overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-white/10 px-5 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Empresas recentes</h3>
                    <p class="mt-1 text-sm sf-text-muted">Visao rapida de atividade, usuários e receita por conta.</p>
                </div>
                <a href="{{ route('super-admin.companies.index') }}" class="sf-button-secondary">Ver todas</a>
            </div>

            <div class="divide-y divide-white/10">
                @forelse ($latestCompanies as $company)
                    <div class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(0,0.7fr))_auto] lg:items-center">
                        <div>
                            <p class="text-base font-semibold text-[var(--text-main)]">{{ $company->name }}</p>
                            <p class="mt-1 text-sm sf-text-muted">{{ $company->active ? 'Conta ativa' : 'Conta inativa' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] sf-text-muted">Usuários</p>
                            <p class="mt-1 text-sm font-semibold text-[var(--text-main)]">{{ $company->users_count }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] sf-text-muted">Agendamentos</p>
                            <p class="mt-1 text-sm font-semibold text-[var(--text-main)]">{{ $company->appointments_count }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] sf-text-muted">Receita</p>
                            <p class="mt-1 text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) ($company->payments_sum_gross_amount ?? 0), 2, ',', '.') }}</p>
                        </div>
                        <div class="lg:text-right">
                            <a href="{{ route('super-admin.companies.show', $company) }}" class="sf-button-ghost">Detalhes</a>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm sf-text-muted">
                        Nenhuma empresa encontrada.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
