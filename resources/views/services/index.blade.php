<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h2 class="text-3xl font-semibold tracking-tight text-[var(--text-main)]">Serviços</h2>
                <p class="mt-2 text-sm sf-text-muted">Gerencie serviços vendidos no PDV e exibidos no link de agendamento.</p>
            </div>

            @if (auth()->user()->isAdmin() && auth()->user()->company_id)
                <a href="{{ route('services.create') }}" class="sf-button-primary">
                    + Novo serviço
                </a>
            @endif
        </div>
    </x-slot>

    @php
        $statusBadge = function (bool $enabled, string $on, string $off, string $tone = 'green'): string {
            $classes = $enabled
                ? ($tone === 'gold'
                    ? 'border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-[var(--brand-primary)]'
                    : 'border-emerald-400/25 bg-emerald-500/10 text-emerald-100')
                : 'border-white/10 bg-[var(--input-bg)] sf-text-muted';

            return '<span class="'.$classes.' inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold">'.e($enabled ? $on : $off).'</span>';
        };
    @endphp

    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
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

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article class="sf-card-soft flex items-center justify-between gap-3 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] brand-text">Serviços ativos</p>
                <p class="text-2xl font-semibold text-[var(--text-main)]">{{ $activeServicesCount }}</p>
            </article>
            <article class="sf-card-soft flex items-center justify-between gap-3 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] brand-text">Link público</p>
                <p class="text-2xl font-semibold text-[var(--text-main)]">{{ $publicServicesCount }}</p>
            </article>
            <article class="sf-card-soft flex items-center justify-between gap-3 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] brand-text">PDV</p>
                <p class="text-2xl font-semibold text-[var(--text-main)]">{{ $posServicesCount }}</p>
            </article>
            <article class="sf-card-soft flex items-center justify-between gap-3 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] brand-text">Ticket médio</p>
                <p class="text-xl font-semibold text-[var(--text-main)]">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card-soft flex items-center justify-between gap-3 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] brand-text">Tempo médio</p>
                <p class="text-xl font-semibold text-[var(--text-main)]">{{ (int) round($averageDuration) }} min</p>
            </article>
        </section>

        <section class="sf-card p-4">
            <form method="GET" action="{{ route('services.index') }}" class="grid gap-3 xl:grid-cols-[minmax(240px,1fr)_180px_180px_160px_auto] xl:items-end">
                <input type="hidden" name="view" value="{{ $viewMode }}">
                <label class="grid gap-1 text-xs font-semibold uppercase tracking-[0.14em] brand-text">
                    Buscar
                    <input name="q" value="{{ $search }}" class="sf-input !py-2.5 normal-case tracking-normal" placeholder="Buscar serviço...">
                </label>
                <label class="grid gap-1 text-xs font-semibold uppercase tracking-[0.14em] brand-text">
                    Público
                    <select name="public_status" class="sf-select !py-2.5 normal-case tracking-normal">
                        <option value="">Todos</option>
                        <option value="1" @selected($publicStatus === '1')>Disponível</option>
                        <option value="0" @selected($publicStatus === '0')>Oculto</option>
                    </select>
                </label>
                <label class="grid gap-1 text-xs font-semibold uppercase tracking-[0.14em] brand-text">
                    PDV
                    <select name="pos_status" class="sf-select !py-2.5 normal-case tracking-normal">
                        <option value="">Todos</option>
                        <option value="1" @selected($posStatus === '1')>Disponível</option>
                        <option value="0" @selected($posStatus === '0')>Indisponível</option>
                    </select>
                </label>
                <label class="grid gap-1 text-xs font-semibold uppercase tracking-[0.14em] brand-text">
                    Status
                    <select name="active_status" class="sf-select !py-2.5 normal-case tracking-normal">
                        <option value="">Todos</option>
                        <option value="1" @selected($activeStatus === '1')>Ativo</option>
                        <option value="0" @selected($activeStatus === '0')>Inativo</option>
                    </select>
                </label>
                <button class="sf-button-ghost !py-2.5">Filtrar</button>
            </form>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-3">
                <p class="text-sm sf-text-muted">{{ $services->total() }} serviços encontrados.</p>
                <div class="inline-flex overflow-hidden rounded-lg border border-white/10 bg-[var(--input-bg)] p-1">
                    <a href="{{ route('services.index', array_merge(request()->except('view', 'page'), ['view' => 'list'])) }}" class="{{ $viewMode === 'list' ? 'bg-[var(--brand-primary)] text-[var(--brand-on-primary)]' : 'sf-text-muted hover:bg-white/10' }} rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.12em]">Lista</a>
                    <a href="{{ route('services.index', array_merge(request()->except('view', 'page'), ['view' => 'cards'])) }}" class="{{ $viewMode === 'cards' ? 'bg-[var(--brand-primary)] text-[var(--brand-on-primary)]' : 'sf-text-muted hover:bg-white/10' }} rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.12em]">Cards</a>
                </div>
            </div>
        </section>

        @if ($services->isEmpty())
            <section class="sf-card rounded-xl border border-dashed border-white/10 px-5 py-12 text-center">
                <p class="text-base font-semibold text-[var(--text-main)]">Nenhum serviço encontrado.</p>
                <p class="mt-2 text-sm sf-text-muted">Ajuste os filtros ou cadastre um novo serviço.</p>
            </section>
        @elseif ($viewMode === 'cards')
            <section class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($services as $service)
                    <article class="sf-card flex gap-4 p-4">
                        <div class="h-20 w-24 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-[var(--input-bg)]">
                            @if ($service->image_url)
                                <img src="{{ $service->image_url }}" alt="Imagem de {{ $service->name }}" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-xs font-semibold brand-text">SF</div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="truncate text-base font-semibold text-[var(--text-main)]">{{ $service->name }}</h3>
                                {!! $statusBadge((bool) $service->active, 'Ativo', 'Inativo') !!}
                            </div>
                            <p class="mt-1 truncate text-sm sf-text-muted">{{ $service->description ?: 'Sem descrição cadastrada.' }}</p>
                            <p class="mt-2 text-sm font-semibold text-[var(--text-main)]">{{ $service->duration_minutes }} min · R$ {{ number_format((float) $service->price, 2, ',', '.') }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
                                {!! $statusBadge((bool) $service->is_publicly_available, 'Público: Sim', 'Público: Não') !!}
                                {!! $statusBadge((bool) $service->available_for_pos, 'PDV: Sim', 'PDV: Não', 'gold') !!}
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('services.show', $service) }}" class="sf-button-ghost !px-3 !py-1.5 !text-xs">Visualizar</a>
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('services.edit', $service) }}" class="sf-button-secondary !px-3 !py-1.5 !text-xs">Editar</a>
                                    @include('services._toggle-active-form', ['service' => $service, 'buttonClass' => 'sf-button-ghost !px-3 !py-1.5 !text-xs'])
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="space-y-3 md:hidden">
                @foreach ($services as $service)
                    <article class="sf-card p-4">
                        <div class="flex gap-3">
                            <div class="h-14 w-16 shrink-0 overflow-hidden rounded-md border border-white/10 bg-[var(--input-bg)]">
                                @if ($service->image_url)
                                    <img src="{{ $service->image_url }}" alt="Imagem de {{ $service->name }}" class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-xs font-semibold brand-text">SF</div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-sm font-semibold text-[var(--text-main)]">{{ $service->name }}</h3>
                                <p class="mt-1 line-clamp-2 text-xs sf-text-muted">{{ $service->description ?: 'Sem descrição cadastrada.' }}</p>
                                <p class="mt-2 text-sm font-semibold text-[var(--text-main)]">{{ $service->duration_minutes }} min · R$ {{ number_format((float) $service->price, 2, ',', '.') }}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            {!! $statusBadge((bool) $service->active, 'Ativo', 'Inativo') !!}
                            {!! $statusBadge((bool) $service->is_publicly_available, 'Público: Sim', 'Público: Não') !!}
                            {!! $statusBadge((bool) $service->available_for_pos, 'PDV: Sim', 'PDV: Não', 'gold') !!}
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ route('services.show', $service) }}" class="sf-button-ghost !px-3 !py-1.5 !text-xs">Visualizar</a>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('services.edit', $service) }}" class="sf-button-secondary !px-3 !py-1.5 !text-xs">Editar</a>
                                @include('services._toggle-active-form', ['service' => $service, 'buttonClass' => 'sf-button-ghost !px-3 !py-1.5 !text-xs'])
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="sf-card hidden overflow-hidden md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Serviço</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Duração</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Preço</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Público</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">PDV</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Operacional</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8">
                            @foreach ($services as $service)
                                <tr class="transition hover:bg-white/[0.03]">
                                    <td class="px-4 py-3">
                                        <div class="flex min-w-[280px] items-center gap-3">
                                            <div class="h-12 w-14 shrink-0 overflow-hidden rounded-md border border-white/10 bg-[var(--input-bg)]">
                                                @if ($service->image_url)
                                                    <img src="{{ $service->image_url }}" alt="Imagem de {{ $service->name }}" class="h-full w-full object-cover" loading="lazy">
                                                @else
                                                    <div class="flex h-full w-full items-center justify-center text-xs font-semibold brand-text">SF</div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-[var(--text-main)]">{{ $service->name }}</p>
                                                <p class="max-w-[34rem] truncate text-xs sf-text-muted">{{ $service->description ?: 'Sem descrição cadastrada.' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums sf-text-muted">{{ $service->duration_minutes }} min</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $service->price, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-center">{!! $statusBadge((bool) $service->is_publicly_available, 'Sim', 'Não') !!}</td>
                                    <td class="px-4 py-3 text-center">{!! $statusBadge((bool) $service->available_for_pos, 'Sim', 'Não', 'gold') !!}</td>
                                    <td class="px-4 py-3 text-center">{!! $statusBadge((bool) $service->active, 'Ativo', 'Inativo') !!}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('services.show', $service) }}" class="sf-button-ghost !px-3 !py-1.5 !text-xs">Visualizar</a>
                                            @if (auth()->user()->isAdmin())
                                                <a href="{{ route('services.edit', $service) }}" class="sf-button-secondary !px-3 !py-1.5 !text-xs">Editar</a>
                                                @include('services._toggle-active-form', ['service' => $service, 'buttonClass' => 'sf-button-ghost !px-3 !py-1.5 !text-xs'])
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($services->hasPages())
            <div class="sf-card px-5 py-4">
                {{ $services->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
