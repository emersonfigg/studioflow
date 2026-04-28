<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">SERVIÇOS</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Serviços da empresa
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Gerencie preço, duração e rentabilidade</p>
            </div>

            @if (auth()->user()->isAdmin() && auth()->user()->company_id)
                <a href="{{ route('services.create') }}" class="sf-button-primary">
                    + Novo serviço
                </a>
            @endif
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                @switch(session('status'))
                    @case('service-created')
                        Serviço criado com sucesso.
                        @break
                    @case('service-updated')
                        Serviço atualizado com sucesso.
                        @break
                    @case('service-deleted')
                        Serviço removido com sucesso.
                        @break
                    @default
                        {{ session('status') }}
                @endswitch
            </div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Serviços ativos</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">{{ $activeServicesCount }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Serviços disponíveis para agenda e autoagendamento.</p>
            </article>
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Ticket médio</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Valor médio cadastrado no portfólio atual.</p>
            </article>
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Tempo médio</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">{{ (int) round($averageDuration) }} min</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Duração média dos serviços da empresa.</p>
            </article>
        </section>

        <section class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @forelse ($services as $service)
                <article class="sf-card group border border-white/10 p-5 transition duration-200 hover:-translate-y-0.5 hover:border-[#d4af37]/25 hover:bg-[#274574] hover:shadow-[0_22px_46px_rgba(7,17,38,0.34)]">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="mb-4 overflow-hidden rounded-2xl border border-white/10 bg-[#132746]">
                                @if ($service->image_url)
                                    <img src="{{ $service->image_url }}" alt="Imagem de {{ $service->name }}" class="h-40 w-full object-cover">
                                @else
                                    <div class="flex h-40 items-center justify-center bg-[#132746]">
                                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[#d4af37]/12 text-[#d4af37]">
                                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2 1.586-1.586a2 2 0 012.828 0L20 14m-9-5h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="inline-flex items-center rounded-full border border-white/10 bg-[#132746] px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">
                                Serviço
                            </div>
                            <h3 class="mt-4 truncate text-xl font-semibold text-white">{{ $service->name }}</h3>
                            <p class="mt-2 text-sm text-[#c7d2e3]">Duracao operacional e valor comercial sempre visíveis para sua equipe.</p>
                        </div>

                        <span class="{{ $service->active ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-rose-400/20 bg-rose-500/10 text-rose-100' }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                            {{ $service->active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Duração</p>
                            <p class="mt-2 text-lg font-semibold text-white">{{ $service->duration_minutes }} min</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Preço</p>
                            <p class="mt-2 text-lg font-semibold text-white">R$ {{ number_format((float) $service->price, 2, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('services.show', $service) }}" class="sf-button-ghost !px-4 !py-2.5">
                            Visualizar
                        </a>

                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('services.edit', $service) }}" class="sf-button-secondary !px-4 !py-2.5">
                                Editar
                            </a>

                            <form method="POST" action="{{ route('services.update', $service) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="name" value="{{ $service->name }}">
                                <input type="hidden" name="duration_minutes" value="{{ $service->duration_minutes }}">
                                <input type="hidden" name="price" value="{{ number_format((float) $service->price, 2, '.', '') }}">
                                <input type="hidden" name="active" value="0">
                                <button type="submit" class="{{ $service->active ? 'inline-flex items-center justify-center rounded-xl border border-rose-300/20 bg-rose-400/12 px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.18em] text-rose-50 transition duration-150 hover:bg-rose-400/20 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 focus:ring-offset-[#1b335b]' : 'inline-flex items-center justify-center rounded-xl border border-white/10 bg-[#132746] px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3] opacity-60' }}" @disabled(! $service->active)>
                                    Desativar
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="sf-card col-span-full rounded-2xl border border-dashed border-white/10 px-5 py-12 text-center">
                    <p class="text-base font-semibold text-white">Nenhum serviço cadastrado ainda.</p>
                    <p class="mt-2 text-sm text-[#c7d2e3]">Monte seu portfólio para organizar preço, duração e rentabilidade com mais clareza.</p>
                </div>
            @endforelse
        </section>

        @if ($services->hasPages())
            <div class="sf-card px-5 py-4">
                {{ $services->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
