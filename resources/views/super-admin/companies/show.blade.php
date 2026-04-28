<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Super Admin</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">{{ $company->name }}</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Detalhes completos da conta, atividade e receita registrada.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('super-admin.companies.index') }}" class="sf-button-secondary">Voltar</a>
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
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Status</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ $company->active ? 'Ativa' : 'Inativa' }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Usuarios</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ $company->users_count }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Agendamentos do mes</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ $appointmentsThisMonth }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Receita do mes</p>
                <p class="mt-3 text-2xl font-semibold text-white">R$ {{ number_format($revenueThisMonth, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Receita total</p>
                <p class="mt-3 text-2xl font-semibold text-white">R$ {{ number_format($totalRevenue, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1fr)_minmax(0,1.05fr)]">
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-white">Usuarios recentes</h3>
                </div>
                <div class="divide-y divide-white/10">
                    @forelse ($latestUsers as $user)
                        <div class="flex items-center justify-between gap-4 px-5 py-4">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $user->name }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">{{ $user->email }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">{{ $user->global_role === 'super_admin' ? 'Global' : 'Conta' }}</p>
                                <p class="mt-1 text-sm font-semibold text-white">{{ $user->role === 'admin' ? 'Admin' : 'Staff' }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-sm text-[#c7d2e3]">Nenhum usuario encontrado.</div>
                    @endforelse
                </div>
            </article>

            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-white">Agendamentos recentes</h3>
                </div>
                <div class="divide-y divide-white/10">
                    @forelse ($latestAppointments as $appointment)
                        <div class="grid gap-2 px-5 py-4 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center md:gap-4">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $appointment->client?->name ?? 'Cliente removido' }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">{{ $appointment->user?->name ?? 'Profissional removido' }}</p>
                            </div>
                            <p class="text-sm font-semibold text-white">{{ $appointment->start_time->format('d/m H:i') }}</p>
                            <p class="text-sm text-[#c7d2e3]">{{ $appointment->statusLabel() }}</p>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-sm text-[#c7d2e3]">Nenhum agendamento encontrado.</div>
                    @endforelse
                </div>
            </article>

            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-white">Pagamentos recentes</h3>
                </div>
                <div class="divide-y divide-white/10">
                    @forelse ($latestPayments as $payment)
                        <div class="grid gap-2 px-5 py-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center md:gap-4">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $payment->client?->name ?? 'Cliente removido' }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">
                                    {{ $payment->service?->name ?? 'Servico removido' }} • {{ $payment->user?->name ?? 'Profissional removido' }}
                                </p>
                            </div>
                            <div class="text-left md:text-right">
                                <p class="text-sm font-semibold text-white">R$ {{ number_format((float) $payment->gross_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">{{ $payment->paid_at->format('d/m H:i') }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-sm text-[#c7d2e3]">Nenhum pagamento encontrado.</div>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</x-app-layout>
