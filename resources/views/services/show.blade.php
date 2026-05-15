<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl ring-1 ring-white/10">
                    @if ($service->image_url)
                        <img
                            src="{{ $service->image_url }}"
                            alt="Imagem de {{ $service->name }}"
                            class="absolute inset-0 h-full w-full object-cover"
                            loading="lazy"
                            decoding="async"
                            onerror="this.classList.add('hidden'); this.nextElementSibling?.classList.remove('hidden')"
                        >
                    @endif
                    <div @class([
                        'absolute inset-0 flex items-center justify-center rounded-xl border border-dashed border-white/15 bg-[var(--input-bg)] brand-text',
                        'hidden' => (bool) $service->image_url,
                    ])>
                        <svg class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M7.5 6A2.5 2.5 0 005 8.5v7A2.5 2.5 0 007.5 18h9a2.5 2.5 0 002.5-2.5v-7A2.5 2.5 0 0016.5 6h-9zm0 1.5h9A1 1 0 0117.5 8.5v4.085l-2.23-2.23a1.75 1.75 0 00-2.475 0l-2.92 2.92-1.17-1.17a1.75 1.75 0 00-2.475 0L6.5 13.835V8.5a1 1 0 011-1zm8.75 2.25a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zM7.29 13.166a.25.25 0 01.354 0l1.7 1.7a.75.75 0 001.06 0l3.451-3.45a.25.25 0 01.354 0l2.291 2.29v1.794a1 1 0 01-1 1h-9a1 1 0 01-1-1v-.544l1.79-1.79z" />
                        </svg>
                    </div>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">SERVIÇOS</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                        {{ $service->name }}
                    </h2>
                    <p class="mt-2 text-sm sf-text-muted">Detalhes e performance do serviço</p>
                </div>
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

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium sf-text-muted">Preço</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">R$ {{ number_format((float) $service->price, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm sf-text-muted">Valor atual cadastrado para este serviço.</p>
            </article>
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium sf-text-muted">Duração</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">{{ $service->duration_minutes }} min</p>
                <p class="mt-2 text-sm sf-text-muted">Tempo medio reservado na agenda.</p>
            </article>
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium sf-text-muted">Agendamentos no mês</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">{{ $monthlyAppointmentsCount }}</p>
                <p class="mt-2 text-sm sf-text-muted">Total de agendamentos ligados a este serviço neste mês.</p>
            </article>
            <article class="sf-card-soft p-5">
                <p class="text-sm font-medium sf-text-muted">Receita gerada no mês</p>
                <p class="mt-4 text-4xl font-semibold tracking-tight text-[var(--text-main)]">R$ {{ number_format($monthlyRevenue, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm sf-text-muted">Recebimentos confirmados deste serviço no período atual.</p>
            </article>
        </section>

        @if ($service->productConsumptions->isNotEmpty())
            <section class="sf-card p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-[var(--text-main)]">Insumos vinculados</h3>
                <p class="mt-1 text-sm sf-text-muted">Baixa automática de estoque ao concluir atendimento com este serviço.</p>
                <ul class="mt-4 divide-y divide-white/10 rounded-xl border border-white/10">
                    @foreach ($service->productConsumptions as $consumption)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span class="font-medium text-[var(--text-main)]">{{ $consumption->product?->name ?? 'Produto removido' }}</span>
                            <span class="sf-text-muted">
                                {{ $consumption->quantity }}
                                @if ($consumption->unit)
                                    {{ $consumption->unit }}
                                @else
                                    un.
                                @endif
                                @if (! $consumption->active)
                                    <span class="ml-2 text-xs text-amber-200">(inativo)</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="sf-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center rounded-full border border-white/10 bg-[var(--input-bg)] px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] brand-text">
                            Serviço completo
                        </div>
                        <h3 class="mt-4 text-2xl font-semibold text-[var(--text-main)]">{{ $service->name }}</h3>
                        <p class="mt-2 max-w-2xl whitespace-pre-line text-sm leading-7 sf-text-muted">
                            @if (filled($service->description))
                                {{ $service->description }}
                            @else
                                Use este painel para acompanhar os numeros do serviço, revisar disponibilidade comercial e agir rapidamente sobre o catalogo da empresa.
                            @endif
                        </p>
                    </div>

                    <span class="{{ $service->active ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-rose-400/20 bg-rose-500/10 text-rose-100' }} inline-flex rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.14em]">
                        {{ $service->active ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] brand-text">Posicionamento</p>
                        <p class="mt-2 text-sm font-medium text-[var(--text-main)]">Catalogo da empresa</p>
                        <p class="mt-2 text-sm sf-text-muted">Serviço configurado para usó em agenda operacional e autoagendamento.</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] brand-text">Disponibilidade</p>
                        <p class="mt-2 text-sm font-medium text-[var(--text-main)]">{{ $service->active ? 'Visivel para novos agendamentos' : 'Oculto para novos agendamentos' }}</p>
                        <p class="mt-2 text-sm sf-text-muted">A alteracao de status impacta a agenda interna e o fluxo público imediatamente.</p>
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="sf-card overflow-hidden">
                    <div class="border-b border-white/10 px-5 py-5">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Acoes</p>
                        <h3 class="mt-2 text-xl font-semibold text-[var(--text-main)]">Gerenciar serviço</h3>
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
                                <button type="submit" class="{{ $service->active ? 'inline-flex w-full items-center justify-center rounded-xl border border-rose-300/20 bg-rose-400/12 px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-rose-50 transition duration-150 hover:bg-rose-400/20 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 focus:ring-offset-[var(--app-shell-bg)]' : 'inline-flex w-full items-center justify-center rounded-xl border border-white/10 bg-[var(--input-bg)] px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted opacity-60' }}" @disabled(! $service->active)>
                                    Desativar
                                </button>
                            </form>

                            <form method="POST" action="{{ route('services.destroy', $service) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-rose-300/20 bg-rose-400/12 px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-rose-50 transition duration-150 hover:bg-rose-400/20 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 focus:ring-offset-[var(--app-shell-bg)]" onclick="return confirm('Excluir este serviço?')">
                                    Excluir
                                </button>
                            </form>
                        @else
                            <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4 text-sm sf-text-muted">
                                Apenas administradores podem alterar este serviço.
                            </div>
                        @endif
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
