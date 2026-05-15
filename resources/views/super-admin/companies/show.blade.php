<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Super Admin</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">{{ $company->name }}</h2>
                <p class="mt-2 text-sm sf-text-muted">Detalhes completos da conta, atividade e receita registrada.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('super-admin.companies.index') }}" class="sf-button-secondary">Voltar</a>
                <form method="POST" action="{{ route('super-admin.companies.support.start', $company) }}">
                    @csrf
                    <button type="submit" class="sf-button-primary">
                        Acessar em modo suporte
                    </button>
                </form>
                <form method="POST" action="{{ route('super-admin.companies.toggle-active', $company) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="{{ $company->active ? 'inline-flex items-center justify-center rounded-xl border border-rose-300/20 bg-rose-400/12 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-rose-50 transition hover:bg-rose-400/20' : 'sf-button-primary' }}">
                        {{ $company->active ? 'Inativar empresa' : 'Ativar empresa' }}
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Status</p>
                <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">{{ $company->active ? 'Ativa' : 'Inativa' }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Usuários</p>
                <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">{{ $company->users_count }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Agendamentos do mês</p>
                <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">{{ $appointmentsThisMonth }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Receita do mês</p>
                <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">R$ {{ number_format($revenueThisMonth, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Receita total</p>
                <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">R$ {{ number_format($totalRevenue, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1fr)_minmax(0,1.05fr)]">
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Usuários recentes</h3>
                </div>
                <div class="divide-y divide-white/10">
                    @forelse ($latestUsers as $user)
                        <div class="flex items-center justify-between gap-4 px-5 py-4">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">{{ $user->name }}</p>
                                <p class="mt-1 text-sm sf-text-muted">{{ $user->email }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-[0.18em] sf-text-muted">{{ $user->global_role === 'super_admin' ? 'Global' : 'Conta' }}</p>
                                <p class="mt-1 text-sm font-semibold text-[var(--text-main)]">{{ $user->role === 'admin' ? 'Admin' : 'Staff' }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-sm sf-text-muted">Nenhum usuario encontrado.</div>
                    @endforelse
                </div>
            </article>

            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Agendamentos recentes</h3>
                </div>
                <div class="divide-y divide-white/10">
                    @forelse ($latestAppointments as $appointment)
                        <div class="grid gap-2 px-5 py-4 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center md:gap-4">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">{{ $appointment->client?->name ?? 'Cliente removido' }}</p>
                                <p class="mt-1 text-sm sf-text-muted">{{ $appointment->user?->name ?? 'Profissional removido' }}</p>
                            </div>
                            <p class="text-sm font-semibold text-[var(--text-main)]">{{ $appointment->start_time->format('d/m H:i') }}</p>
                            <p class="text-sm sf-text-muted">{{ $appointment->statusLabel() }}</p>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-sm sf-text-muted">Nenhum agendamento encontrado.</div>
                    @endforelse
                </div>
            </article>

            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Pagamentos recentes</h3>
                </div>
                <div class="divide-y divide-white/10">
                    @forelse ($latestPayments as $payment)
                        <div class="grid gap-2 px-5 py-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center md:gap-4">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">{{ $payment->client?->name ?? 'Cliente removido' }}</p>
                                <p class="mt-1 text-sm sf-text-muted">
                                    {{ $payment->service?->name ?? 'Serviço removido' }} • {{ $payment->user?->name ?? 'Profissional removido' }}
                                </p>
                            </div>
                            <div class="text-left md:text-right">
                                <p class="text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $payment->gross_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-sm sf-text-muted">{{ $payment->paid_at->format('d/m H:i') }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-sm sf-text-muted">Nenhum pagamento encontrado.</div>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</x-app-layout>
