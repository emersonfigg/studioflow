<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Equipe</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Profissionais da empresa
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Gerencie acessos, status, fotos e comissoes do seu time.</p>
            </div>

            <a href="{{ route('team.create') }}" class="sf-button-primary">
                Novo profissional
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                @switch(session('status'))
                    @case('team-member-created')
                        Profissional criado com sucesso.
                        @break
                    @case('team-member-updated')
                        Profissional atualizado com sucesso.
                        @break
                    @case('team-member-activated')
                        Profissional ativado com sucesso.
                        @break
                    @case('team-member-deactivated')
                        Profissional inativado com sucesso.
                        @break
                @endswitch
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($users as $user)
                @php
                    $highlighted = (int) session('highlight_user_id') === $user->id;
                    $commissionLabel = match ($user->commission_type) {
                        'percent' => number_format((float) $user->commission_value, 2, ',', '.') . '%',
                        'fixed' => 'R$ ' . number_format((float) $user->commission_value, 2, ',', '.'),
                        default => 'Sem comissao',
                    };
                @endphp
                <article class="{{ $highlighted ? 'border-[#d4af37]/35 shadow-[0_18px_40px_rgba(212,175,55,0.12)]' : 'border-white/10' }} sf-card overflow-hidden border p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                @if ($user->photo_url)
                                    <img src="{{ $user->photo_url }}" alt="Foto de {{ $user->name }}" class="h-12 w-12 shrink-0 rounded-full object-cover ring-1 ring-white/10">
                                @else
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">
                                        {{ $user->avatar_initial }}
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold text-white">{{ $user->name }}</p>
                                    <p class="truncate text-sm text-[#c7d2e3]">{{ $user->email }}</p>
                                </div>
                            </div>
                        </div>

                        <span class="{{ $user->active ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-rose-400/20 bg-rose-500/10 text-rose-100' }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                            {{ $user->active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>

                    <dl class="mt-5 space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Perfil</dt>
                            <dd class="text-sm font-semibold text-white">{{ $user->role === 'admin' ? 'Admin' : 'Staff' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Comissao</dt>
                            <dd class="text-sm font-semibold text-white">{{ $commissionLabel }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Agenda publica</dt>
                            <dd class="text-sm font-semibold {{ $user->active ? 'text-[#d4af37]' : 'text-[#c7d2e3]' }}">{{ $user->active ? 'Disponivel' : 'Oculta' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('team.edit', $user) }}" class="sf-button-ghost !px-4 !py-2.5">
                            Editar
                        </a>
                        <a href="{{ route('team.availability.edit', $user) }}" class="sf-button-secondary !px-4 !py-2.5">
                            Agenda
                        </a>

                        <form method="POST" action="{{ route('team.toggle-active', $user) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="{{ $user->active ? 'inline-flex items-center justify-center rounded-xl border border-rose-300/20 bg-rose-400/12 px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.18em] text-rose-50 transition duration-150 hover:bg-rose-400/20 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 focus:ring-offset-[#1b335b]' : 'sf-button-secondary !px-4 !py-2.5' }}">
                                {{ $user->active ? 'Inativar' : 'Ativar' }}
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="sf-card col-span-full rounded-2xl border border-dashed border-white/10 px-5 py-10 text-center text-sm text-[#c7d2e3]">
                    Nenhum profissional cadastrado ainda.
                </div>
            @endforelse
        </section>

        <div>
            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
