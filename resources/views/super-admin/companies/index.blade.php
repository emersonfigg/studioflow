<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Super Admin</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Empresas</h2>
            <p class="mt-2 text-sm sf-text-muted">Gerencie status, usuários, agendamentos e faturamento das contas do StudioFlow.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                {{ session('status') === 'company-activatéd' ? 'Empresa ativada com sucesso.' : 'Empresa inativada com sucesso.' }}
            </div>
        @endif

        <section class="grid gap-4 xl:grid-cols-2">
            @foreach ($companies as $company)
                <article class="sf-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-base font-semibold text-[var(--text-main)]">{{ $company->name }}</p>
                            <p class="mt-1 text-sm sf-text-muted">{{ $company->phone ?: 'Sem telefone cadastrado' }}</p>
                        </div>
                        <span class="{{ $company->active ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-rose-400/20 bg-rose-500/10 text-rose-100' }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                            {{ $company->active ? 'Ativa' : 'Inativa' }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                            <p class="text-xs uppercase tracking-[0.18em] sf-text-muted">Usuários</p>
                            <p class="mt-2 text-xl font-semibold text-[var(--text-main)]">{{ $company->users_count }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                            <p class="text-xs uppercase tracking-[0.18em] sf-text-muted">Agendamentos</p>
                            <p class="mt-2 text-xl font-semibold text-[var(--text-main)]">{{ $company->appointments_count }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                            <p class="text-xs uppercase tracking-[0.18em] sf-text-muted">Receita</p>
                            <p class="mt-2 text-xl font-semibold text-[var(--text-main)]">R$ {{ number_format((float) ($company->payments_sum_gross_amount ?? 0), 2, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('super-admin.companies.show', $company) }}" class="sf-button-secondary">Detalhes</a>
                        <form method="POST" action="{{ route('super-admin.companies.toggle-active', $company) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="{{ $company->active ? 'inline-flex items-center justify-center rounded-xl border border-rose-300/20 bg-rose-400/12 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-rose-50 transition hover:bg-rose-400/20' : 'sf-button-primary' }}">
                                {{ $company->active ? 'Inativar empresa' : 'Ativar empresa' }}
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </section>

        <div>
            {{ $companies->links() }}
        </div>
    </div>
</x-app-layout>
