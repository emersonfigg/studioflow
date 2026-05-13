@php
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $recommendations */
    $recommendations = $recommendations ?? collect();
    $title = $title ?? 'Oportunidades para este cliente';
    $subtitle = $subtitle ?? 'Sugestões de recompra e retorno baseadas no histórico do cliente.';
    $variant = $variant ?? 'card';
@endphp

<section @class([
    'sf-card overflow-hidden' => $variant === 'card',
    'rounded-2xl border border-white/10 bg-[#0f203b]/60 overflow-hidden' => $variant !== 'card',
])>
    <div class="flex flex-col gap-1 border-b border-white/10 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Recomendações comerciais</p>
            <h3 class="mt-1 text-base font-semibold text-white">{{ $title }}</h3>
            <p class="mt-1 text-xs text-[#c7d2e3]">{{ $subtitle }}</p>
        </div>
        @if ($recommendations->isNotEmpty())
            <span class="inline-flex items-center self-start rounded-full border border-[#d4af37]/30 bg-[#d4af37]/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[#f6e7b3] sm:self-auto">
                {{ $recommendations->count() }} {{ \Illuminate\Support\Str::plural('oportunidade', $recommendations->count()) }}
            </span>
        @endif
    </div>

    @if ($recommendations->isEmpty())
        <div class="px-5 py-8 text-sm text-[#c7d2e3]">
            Nenhuma oportunidade ativa no momento. Continue acompanhando o histórico do cliente.
        </div>
    @else
        <ul class="divide-y divide-white/10">
            @foreach ($recommendations as $recommendation)
                @php
                    $status = (string) $recommendation['status'];
                    $badgeClasses = \App\Services\ClientRecommendationService::statusBadgeClasses($status);
                    $statusLabel = \App\Services\ClientRecommendationService::statusLabel($status);
                    $typeLabel = $recommendation['item_type'] === 'product' ? 'Produto' : 'Serviço';
                @endphp
                <li class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_180px] sm:items-start">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] {{ $badgeClasses }}">
                                {{ $statusLabel }}
                            </span>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#c7d2e3]">{{ $typeLabel }}</span>
                        </div>
                        <p class="mt-2 text-sm font-semibold text-white">{{ $recommendation['item_name'] }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-[#c7d2e3]">{{ $recommendation['message'] }}</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-[11px] text-[#c7d2e3] sm:grid-cols-1 sm:text-right">
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.14em]">Última ocorrência</dt>
                            <dd class="mt-1 text-white">{{ $recommendation['last_occurrence_date']->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.14em]">Prazo previsto</dt>
                            <dd class="mt-1 text-white">{{ $recommendation['next_recommendation_date']->format('d/m/Y') }}</dd>
                        </div>
                    </dl>
                </li>
            @endforeach
        </ul>
    @endif
</section>
