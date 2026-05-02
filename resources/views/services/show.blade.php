<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">SERVIÇOS</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    {{ $service->name }}
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Detalhes e performance do serviço</p>
            </div>

            <a href="{{ route('services.index') }}" class="sf-button-ghost">
                Voltar para serviços
            </a>
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
                    @default
                        {{ session('status') }}
                @endswitch
            </div>
        @endif

        @if ($service->image_url)
            <section class="sf-card overflow-hidden">
                <img src="{{ $service->image_url }}" alt="Imagem de {{ $service->name }}" class="h-56 w-full object-cover sm:h-72">
            </section>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Preço</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format((float) $service->price, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Valor atual cadastrado para este serviço.</p>
            </article>
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Duração</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">{{ $service->duration_minutes }} min</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Tempo medio reservado na agenda.</p>
            </article>
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Agendamentos no mês</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">{{ $monthlyAppointmentsCount }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Total de agendamentos ligados a este serviço neste mês.</p>
            </article>
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium text-[#c7d2e3]">Receita gerada no mês</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-white">R$ {{ number_format($monthlyRevenue, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Recebimentos confirmados deste serviço no período atual.</p>
            </article>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="sf-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center rounded-full border border-white/10 bg-[#132746] px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">
                            Serviço completo
                        </div>
                        <h3 class="mt-4 text-2xl font-semibold text-white">{{ $service->name }}</h3>
                        <p class="mt-2 max-w-2xl text-sm leading-7 text-[#c7d2e3]">
                            Use este painel para acompanhar os numeros do serviço, revisar disponibilidade comercial e agir rapidamente sobre o catalogo da empresa.
                        </p>
                    </div>

                    <span class="{{ $service->active ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-rose-400/20 bg-rose-500/10 text-rose-100' }} inline-flex rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.14em]">
                        {{ $service->active ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Posicionamento</p>
                        <p class="mt-2 text-sm font-medium text-white">Catalogo da empresa</p>
                        <p class="mt-2 text-sm text-[#c7d2e3]">Serviço configurado para usó em agenda operacional e autoagendamento.</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Disponibilidade</p>
                        <p class="mt-2 text-sm font-medium text-white">{{ $service->active ? 'Visivel para novos agendamentos' : 'Oculto para novos agendamentos' }}</p>
                        <p class="mt-2 text-sm text-[#c7d2e3]">A alteracao de status impacta a agenda interna e o fluxo público imediatamente.</p>
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="sf-card overflow-hidden">
                    <div class="border-b border-white/10 px-5 py-5">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Acoes</p>
                        <h3 class="mt-2 text-xl font-semibold text-white">Gerenciar serviço</h3>
                    </div>

                    <div class="space-y-3 px-5 py-5">
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('services.edit', $service) }}" class="sf-button-secondary w-full justify-center">
                                Editar
                            </a>

                            <form method="POST" action="{{ route('services.store') }}">
                                @csrf
                                <input type="hidden" name="name" value="{{ $service->name }} (Copia)">
                                <input type="hidden" name="duration_minutes" value="{{ $service->duration_minutes }}">
                                <input type="hidden" name="price" value="{{ number_format((float) $service->price, 2, '.', '') }}">
                                <input type="hidden" name="active" value="{{ $service->active ? '1' : '0' }}">
                                <button type="submit" class="sf-button-ghost w-full justify-center">
                                    Duplicar
                                </button>
                            </form>

                            <form method="POST" action="{{ route('services.update', $service) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="name" value="{{ $service->name }}">
                                <input type="hidden" name="duration_minutes" value="{{ $service->duration_minutes }}">
                                <input type="hidden" name="price" value="{{ number_format((float) $service->price, 2, '.', '') }}">
                                <input type="hidden" name="active" value="0">
                                <button type="submit" class="{{ $service->active ? 'inline-flex w-full items-center justify-center rounded-xl border border-rose-300/20 bg-rose-400/12 px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-rose-50 transition duration-150 hover:bg-rose-400/20 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 focus:ring-offset-[#1b335b]' : 'inline-flex w-full items-center justify-center rounded-xl border border-white/10 bg-[#132746] px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3] opacity-60' }}" @disabled(! $service->active)>
                                    Desativar
                                </button>
                            </form>

                            <form method="POST" action="{{ route('services.destroy', $service) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-rose-300/20 bg-rose-400/12 px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-rose-50 transition duration-150 hover:bg-rose-400/20 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 focus:ring-offset-[#1b335b]" onclick="return confirm('Excluir este serviço?')">
                                    Excluir
                                </button>
                            </form>
                        @else
                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4 text-sm text-[#c7d2e3]">
                                Apenas administradores podem alterar este serviço.
                            </div>
                        @endif
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
