<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Super Admin</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Usuários globais</h2>
            <p class="mt-2 text-sm text-[#c7d2e3]">Visao completa de contas, papéis e vinculacao com empresas.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="sf-card overflow-hidden">
            <div class="divide-y divide-white/10">
                @forelse ($users as $user)
                    <div class="grid gap-3 px-5 py-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto_auto] lg:items-center">
                        <div>
                            <p class="text-base font-semibold text-white">{{ $user->name }}</p>
                            <p class="mt-1 text-sm text-[#c7d2e3]">{{ $user->email }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">Empresa</p>
                            <p class="mt-1 text-sm font-semibold text-white">{{ $user->company?->name ?? 'Conta global' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">Papel</p>
                            <p class="mt-1 text-sm font-semibold text-white">{{ $user->isSuperAdmin() ? 'Super Admin' : ($user->role === 'admin' ? 'Admin' : 'Staff') }}</p>
                        </div>
                        <div>
                            <span class="{{ ($user->company?->active ?? true) ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-rose-400/20 bg-rose-500/10 text-rose-100' }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ ($user->company?->active ?? true) ? 'Ativo' : 'Empresa inativa' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-[#c7d2e3]">
                        Nenhum usuario encontrado.
                    </div>
                @endforelse
            </div>
        </section>

        <div>
            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
