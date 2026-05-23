@extends('layouts.pdv')

@section('title', 'PDV')

@section('content')
    @php($saleResult = session('pdv_sale_result'))
    @if (is_array($saleResult) && ! empty($saleResult['auto_print_receipt']) && ! empty($saleResult['receipt_url']))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                window.open(@json($saleResult['receipt_url']), '_blank', 'noopener,noreferrer');
            });
        </script>
    @endif

    <div class="pdv-page mx-auto w-full max-w-[1800px] px-0 pb-0 sm:px-0.5 lg:px-1">
        @if (is_array($saleResult))
            <div
                class="mb-2 shrink-0 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-500/40 bg-emerald-950/55 px-3 py-2 text-sm text-emerald-50 shadow-md sm:px-4"
                role="status"
            >
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-emerald-50">
                        @if (! empty($saleResult['appointment_completed']))
                            Venda concluída e agendamento finalizado com sucesso.
                        @else
                            Venda concluída com sucesso.
                        @endif
                    </p>
                    <p class="mt-0.5 text-xs text-emerald-100/90">
                        #{{ $saleResult['service_order_id'] ?? '—' }}
                        <span class="mx-1.5 text-emerald-400/70">·</span>
                        R$ {{ $saleResult['total'] ?? '0,00' }}
                        <span class="mx-1.5 text-emerald-400/70">·</span>
                        {{ $saleResult['payment_label'] ?? $saleResult['payment_method'] ?? '' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('pdv.sales') }}" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold sf-text-muted hover:bg-white/10">Histórico</a>
                    @if (! empty($saleResult['receipt_url']))
                        <a
                            href="{{ $saleResult['receipt_url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-lg border border-emerald-400/50 bg-emerald-600 px-3 py-1.5 text-xs font-bold text-emerald-50 hover:bg-emerald-500"
                        >{{ ! empty($saleResult['appointment_completed']) ? 'Imprimir comprovante' : 'Imprimir' }}</a>
                    @endif
                    @if (empty($saleResult['appointment_completed']))
                        <a href="{{ route('product-sales.index') }}" class="rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-[var(--text-main)] hover:bg-white/15">Ver venda</a>
                    @endif
                    <a href="{{ route('pdv.index') }}" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-emerald-100 hover:bg-white/10">Nova venda</a>
                    @if (! empty($saleResult['appointment_completed']))
                        <a href="{{ route('appointments.index') }}" class="rounded-lg border border-[color-mix(in_srgb,var(--brand-primary)_40%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_15%,transparent)] px-3 py-1.5 text-xs font-semibold text-[var(--brand-primary)] hover:bg-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)]">Voltar para agendamentos</a>
                    @endif
                </div>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('pdv.store') }}"
            autocomplete="off"
            x-data="pdvScreen(@js($catalog), @js($initialCart ?? []))"
            x-init="init()"
            x-on:keydown.window="handlePdvHotkeys($event)"
            x-on:keydown.slash.prevent="$refs.searchInput.focus()"
            x-on:submit="submitSale($event)"
            class="pdv-frame flex flex-col overflow-visible rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)] bg-[var(--brand-accent)] shadow-[0_24px_48px_rgba(0,0,0,0.35)] ring-1 ring-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)]"
        >
            @csrf
            {{-- Hook para testes e inspeção: carrinho inicial server-side (Alpine usa o mesmo dado via @js acima) --}}
            <script type="application/json" id="pdv-initial-cart-data" class="hidden">@json($initialCart ?? [])</script>
            @if (isset($pdvAppointment) && $pdvAppointment && isset($appointmentSummary))
                <input type="hidden" name="appointment_id" value="{{ $pdvAppointment->id }}">
                <div class="shrink-0 border-b border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)] bg-[var(--app-shell-bg)] px-3 py-2 text-xs sf-text-muted sm:px-4 sm:text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Atendimento vinculado ao agendamento #{{ $appointmentSummary['id'] }}</p>
                        @if (! empty($appointmentSummary['service_labels']))
                            <span class="inline-flex items-center rounded-full border border-emerald-400/35 bg-emerald-500/15 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-100">
                                Serviços carregados do agendamento
                            </span>
                        @endif
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <p><span class="sf-text-muted">Cliente:</span> <span class="font-medium text-[var(--text-main)]">{{ $appointmentSummary['client_name'] ?? '—' }}</span></p>
                        <p><span class="sf-text-muted">Profissional:</span> <span class="font-medium text-[var(--text-main)]">{{ $appointmentSummary['professional_name'] ?? '—' }}</span></p>
                        <p><span class="sf-text-muted">Horário:</span> <span class="font-medium text-[var(--text-main)]">{{ $appointmentSummary['start_time'] ?? '—' }}
                            @if (! empty($appointmentSummary['end_time']))
                                – {{ $appointmentSummary['end_time'] }}
                            @endif
                        </span></p>
                        <p><span class="sf-text-muted">Serviços (referência):</span> <span class="font-semibold text-[var(--brand-primary)]">R$ {{ $appointmentSummary['services_total_formatted'] }}</span></p>
                    </div>
                    @if (! empty($appointmentSummary['service_labels']))
                        <p class="mt-2 text-xs sf-text-muted">{{ implode(' · ', $appointmentSummary['service_labels']) }}</p>
                    @endif
                    @if (! empty($appointmentSummary['commission_reference']))
                        <p class="mt-2 text-xs sf-text-muted/90">{{ $appointmentSummary['commission_reference'] }}</p>
                    @endif
                    @if (! empty(($appointmentSummary['membership'] ?? [])['active']))
                        <p class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full border border-[color-mix(in_srgb,var(--brand-primary)_40%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)] px-2 py-0.5 font-semibold text-[var(--text-main)]">Assinante</span>
                            <span class="sf-text-muted">{{ ($appointmentSummary['membership']['plan_name'] ?? '') }}@if (! empty($appointmentSummary['membership']['billing_cycle_label'] ?? null)) · {{ $appointmentSummary['membership']['billing_cycle_label'] }}@endif · período {{ ($appointmentSummary['membership']['cycle_label'] ?? '') }}</span>
                        </p>
                    @endif
                </div>
            @endif

            {{-- 2. Barra superior (identidade StudioFlow) --}}
            <header class="shrink-0 flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-white/10 bg-[var(--app-shell-bg)] px-3 py-2 text-[11px] sf-text-muted sm:px-4 sm:text-xs">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="whitespace-nowrap font-bold tracking-wide text-[var(--brand-primary)]">PDV — Ponto de Venda</span>
                    <span class="hidden h-4 w-px bg-white/15 sm:inline-block" aria-hidden="true"></span>
                    <span class="font-semibold text-[var(--text-main)]">Operador: <span class="sf-text-muted">{{ auth()->user()->name }}</span></span>
                    <a href="{{ route('pdv.sales') }}" class="ml-auto inline-flex items-center rounded-lg border border-white/15 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] sf-text-muted hover:bg-white/10 sm:ml-0">Histórico de vendas</a>
                </div>
                <div class="flex w-full flex-wrap items-end gap-3 lg:w-auto lg:flex-nowrap">
                    <label class="grid min-w-[140px] flex-1 gap-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-[color-mix(in_srgb,var(--brand-primary)_90%,transparent)] lg:max-w-[200px]">
                        Cliente
                        <select
                            name="client_id"
                            x-ref="clientSelect"
                            class="pdv-touch-16 sf-select !border-white/15 !bg-[var(--brand-secondary)] !py-2 !text-base !text-[var(--text-main)] lg:!py-2 lg:!text-sm"
                        >
                            <option value="">— Balcão —</option>
                            @foreach ($clients as $client)
                                <option
                                    value="{{ $client->id }}"
                                    @selected((string) old('client_id', isset($pdvAppointment) && $pdvAppointment ? $pdvAppointment->client_id : null) === (string) $client->id)
                                >{{ $client->client_code ?? '-' }} · {{ $client->name }} · {{ $client->phone }}{{ $client->cpf ? ' · CPF '.$client->cpf : '' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid min-w-[140px] flex-1 gap-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-[color-mix(in_srgb,var(--brand-primary)_90%,transparent)] lg:max-w-[200px]">
                        Profissional
                        <select
                            name="user_id"
                            x-ref="professionalSelect"
                            class="pdv-touch-16 sf-select !border-white/15 !bg-[var(--brand-secondary)] !py-2 !text-base !text-[var(--text-main)] lg:!py-2 lg:!text-sm"
                        >
                            <option value="">— Sessão —</option>
                            @foreach ($professionals as $professional)
                                <option
                                    value="{{ $professional->id }}"
                                    @selected((string) old('user_id', isset($pdvAppointment) && $pdvAppointment ? $pdvAppointment->user_id : null) === (string) $professional->id)
                                >{{ $professional->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="flex w-full flex-wrap items-center justify-between gap-3 sm:w-auto sm:justify-end">
                    <span class="tabular-nums sf-text-muted" x-text="currentTime"></span>
                    <span
                        class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide
                        @if ($cashRegister?->closed_at)
                            border-white/20 bg-white/10 sf-text-muted
                        @elseif ($cashRegister)
                            border-[color-mix(in_srgb,var(--brand-primary)_40%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_15%,transparent)] text-[var(--brand-primary)]
                        @else
                            border-amber-500/30 bg-amber-500/10 text-amber-200
                        @endif
                        "
                    >
                        @if ($cashRegister?->closed_at)
                            Caixa fechado
                        @elseif ($cashRegister)
                            Caixa aberto
                        @else
                            Caixa não iniciado
                        @endif
                    </span>
                </div>
            </header>

            {{-- 3. Faixa do item atual --}}
            <div class="shrink-0 border-b border-[color-mix(in_srgb,var(--brand-primary)_20%,transparent)] bg-gradient-to-r from-[var(--brand-secondary)] via-[var(--app-shell-bg)] to-[var(--brand-accent)] px-3 py-2 sm:px-4 sm:py-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[var(--brand-primary)]" x-text="currentKindLabel"></p>
                <p class="mt-0.5 text-center text-lg font-bold uppercase leading-tight tracking-tight text-[var(--text-main)] sm:text-xl lg:text-2xl" x-text="bannerTitle"></p>
            </div>

            {{-- Corpo: 3 colunas (desktop). Mobile: grid sem flex-1 para rolar página inteira; busca em order-1 para ficar logo após o banner; carrinho com altura máx. e rolagem interna (não “roubar” a busca). --}}
            <div class="pdv-workspace grid gap-3 overflow-visible p-3 lg:grid-cols-[minmax(220px,0.78fr)_minmax(320px,1fr)_minmax(500px,1.45fr)] lg:items-stretch lg:p-4">
                {{-- 4. Esquerda: imagem / ícone + VENDA --}}
                <aside class="pdv-panel flex flex-col gap-3 overflow-visible rounded-xl border border-white/10 bg-[color-mix(in_srgb,var(--brand-secondary)_72%,transparent)] p-3 lg:p-4">
                    <div class="relative flex aspect-[4/3] min-h-[150px] w-full shrink-0 overflow-hidden rounded-xl border border-white/10 bg-[var(--brand-secondary)] shadow-inner lg:min-h-[190px]">
                        <template x-if="visualItem && visualItem.image_url && !previewImageFailed">
                            <img
                                :src="visualItem.image_url"
                                :alt="visualItem.name"
                                class="absolute inset-0 z-20 h-full w-full object-cover"
                                loading="lazy"
                                decoding="async"
                                x-on:error="previewImageFailed = true"
                            >
                        </template>
                        <div
                            class="relative z-10 flex h-full w-full items-center justify-center transition-opacity duration-150"
                            :class="visualItem && visualItem.image_url && !previewImageFailed ? 'pointer-events-none opacity-0' : 'opacity-100'"
                        >
                            <template x-if="visualItem && visualItem.type === 'product'">
                                <svg class="h-16 w-16 text-[color-mix(in_srgb,var(--brand-primary)_90%,transparent)] sm:h-20 sm:w-20 lg:h-24 lg:w-24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M7 4h10l1 2h2v2H4V6h2l1-2zm0 4h10v10a2 2 0 01-2 2H9a2 2 0 01-2-2V8zm2 2v8h6v-8H9z"/>
                                </svg>
                            </template>
                            <template x-if="visualItem && visualItem.type === 'service'">
                                <svg class="h-16 w-16 text-[color-mix(in_srgb,var(--brand-primary)_90%,transparent)] sm:h-20 sm:w-20 lg:h-24 lg:w-24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                                </svg>
                            </template>
                            <template x-if="!visualItem">
                                <svg class="h-16 w-16 text-[color-mix(in_srgb,var(--brand-primary)_50%,transparent)] sm:h-20 sm:w-20 lg:h-24 lg:w-24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                </svg>
                            </template>
                        </div>
                    </div>
                    <div class="rounded-xl border border-[color-mix(in_srgb,var(--brand-primary)_30%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,transparent)] py-2 text-center lg:rounded-2xl lg:py-3">
                        <span class="text-sm font-black uppercase tracking-[0.22em] text-[var(--brand-primary)] sm:text-base lg:text-lg">Venda</span>
                        <p class="mt-0.5 text-[10px] font-semibold uppercase sf-text-muted">Item atual</p>
                    </div>
                </aside>

                {{-- 5. Centro: campos grandes + sugestões (shrink-0: nunca colapsar no flex mobile) --}}
                <section class="pdv-panel flex flex-col gap-3 overflow-visible rounded-xl border border-white/10 bg-[color-mix(in_srgb,var(--brand-secondary)_58%,transparent)] p-3 lg:p-4">
                    <label class="text-[11px] font-bold uppercase tracking-[0.16em] text-[var(--brand-primary)]" for="pdv-search-input">Código / SKU / Nome</label>
                    <div class="relative z-50">
                        <input
                            id="pdv-search-input"
                            x-ref="searchInput"
                            x-model="search"
                            x-on:keydown.arrow-down.prevent="highlightNext()"
                            x-on:keydown.arrow-up.prevent="highlightPrev()"
                            x-on:keydown.enter.prevent="selectHighlighted()"
                            type="text"
                            autocomplete="off"
                            class="pdv-touch-16 sf-input mt-2 !border-white/15 !bg-[var(--brand-secondary)] !py-3 !text-base !font-semibold !text-[var(--text-main)] placeholder:sf-text-muted/50 lg:!py-3.5 lg:!text-lg"
                            placeholder="Buscar ou escanear…"
                        >
                        <div
                            x-cloak
                            x-show="search.trim().length > 0 && filteredCatalog.length > 0"
                            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-[min(18rem,45dvh)] overflow-y-auto overscroll-contain rounded-xl border border-white/15 bg-[var(--brand-accent)] py-1 shadow-[0_16px_40px_rgba(0,0,0,0.35)] ring-1 ring-[color-mix(in_srgb,var(--brand-primary)_15%,transparent)] lg:max-h-[min(22rem,50vh)]"
                            role="listbox"
                            aria-label="Sugestões de produtos e serviços"
                        >
                            <template x-for="(item, idx) in filteredCatalog" :key="`${item.type}-${item.id}`">
                                <button
                                    type="button"
                                    role="option"
                                    :aria-selected="highlightedIndex === idx ? 'true' : 'false'"
                                    x-on:click="addCatalogItem(item)"
                                    :class="highlightedIndex === idx ? 'border-[color-mix(in_srgb,var(--brand-primary)_50%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_15%,transparent)]' : 'border-transparent hover:bg-white/5'"
                                    class="flex w-full flex-col rounded-lg border px-3 py-2.5 text-left transition"
                                >
                                    <span class="font-mono text-xs font-bold text-[var(--brand-primary)]" x-text="item.code"></span>
                                    <span class="mt-0.5 block truncate text-sm text-[var(--text-main)]" x-text="item.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <p class="mt-1 text-[10px] sf-text-muted/85">Digite código, SKU ou nome — as sugestões aparecem logo abaixo do campo.</p>

                    <div class="mt-2 grid shrink-0 gap-2 sm:grid-cols-3 sm:gap-3">
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-[0.14em] sf-text-muted">Quantidade</label>
                            <div class="sf-input mt-1 !border-white/15 !bg-[var(--app-shell-bg)] !py-2.5 !text-right !text-lg !font-bold !text-[var(--text-main)] tabular-nums sm:!py-3 sm:!text-xl" x-text="previewQty"></div>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-[0.14em] sf-text-muted">Preço unitário</label>
                            <div class="sf-input mt-1 !border-white/15 !bg-[var(--app-shell-bg)] !py-2.5 !text-right !text-lg !font-bold !text-[var(--brand-primary)] tabular-nums sm:!py-3 sm:!text-xl">
                                R$ <span x-text="formatMoneyBRL(previewUnit)"></span>
                            </div>
                        </div>
                        <div class="sm:col-span-1">
                            <label class="text-[10px] font-bold uppercase tracking-[0.14em] sf-text-muted">Preço total</label>
                            <div class="sf-input mt-1 !border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)] !bg-[var(--brand-secondary)] !py-2.5 !text-right !text-lg !font-bold !text-[var(--text-main)] tabular-nums sm:!py-3 sm:!text-xl">
                                R$ <span x-text="formatMoneyBRL(previewLineTotal)"></span>
                            </div>
                        </div>
                    </div>

                    <p x-show="search.trim().length > 0 && filteredCatalog.length === 0" x-cloak class="mt-2 rounded-lg border border-white/10 bg-[color-mix(in_srgb,var(--app-shell-bg)_60%,transparent)] px-3 py-2 text-center text-sm sf-text-muted">
                        Nenhum item encontrado para esta busca.
                    </p>

                    <button
                        type="button"
                        class="sf-button-primary mt-3 w-full lg:hidden"
                        x-on:click="selectHighlighted()"
                        :disabled="!previewItem"
                        :class="!previewItem ? 'pointer-events-none opacity-40' : ''"
                    >
                        Adicionar item
                    </button>
                </section>

                {{-- 6. Direita: cupom (mobile: altura limitada + scroll; desktop: coluna flexível) --}}
                <section class="pdv-panel flex min-w-0 flex-col overflow-visible rounded-xl border border-white/10 bg-[color-mix(in_srgb,var(--brand-secondary)_58%,transparent)] p-3 lg:p-4">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--brand-primary)] lg:hidden">Itens da venda</p>
                    <div class="pdv-cart-shell flex flex-col rounded-xl border border-white/10 bg-[var(--brand-secondary)] shadow-inner lg:rounded-2xl">
                        <div class="pdv-cart-scroll overflow-x-auto overscroll-contain">
                            <table class="w-full min-w-[620px] border-collapse text-left text-[11px] sf-text-muted">
                                <thead>
                                    <tr class="border-b border-dashed border-white/25 bg-[var(--brand-accent)] text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--brand-primary)]">
                                        <th class="w-10 px-2 py-2 text-left">Item</th>
                                        <th class="w-20 px-1 py-2 text-left">Cód.</th>
                                        <th class="px-2 py-2 text-left">Descrição</th>
                                        <th class="w-16 px-1 py-2 text-right">Qtd</th>
                                        <th class="w-20 px-2 py-2 text-right">Vl.un.</th>
                                        <th class="w-20 px-2 py-2 text-right">Vl.item</th>
                                        <th class="w-12 px-1 py-2 text-center"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-if="cart.length === 0">
                                        <tr>
                                            <td colspan="7" class="px-4 py-12 text-center text-sm sf-text-muted">Nenhum item lançado.</td>
                                        </tr>
                                    </template>
                                    <template x-for="(item, index) in cart" :key="`${item.type}-${item.id}-${index}`">
                                        <tr class="border-b border-white/5 odd:bg-[color-mix(in_srgb,var(--app-shell-bg)_40%,transparent)] even:bg-transparent">
                                            <td class="w-10 px-2 py-2 font-mono text-[var(--text-main)]" x-text="String(index + 1).padStart(4, '0')"></td>
                                            <td class="w-20 px-1 py-2 font-mono text-[var(--brand-primary)]" x-text="item.code"></td>
                                            <td class="min-w-[210px] px-2 py-2 align-top">
                                                <span class="block whitespace-normal break-words leading-snug text-[var(--text-main)]" x-text="item.name"></span>
                                                <template x-if="item.type === 'product'">
                                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                                        <label class="flex w-full flex-col gap-1 text-[10px] font-semibold uppercase tracking-[0.14em] sf-text-muted sm:max-w-[18rem]">
                                                            <span>Vendedor</span>
                                                            <select
                                                                x-model.number="item.seller_id"
                                                                class="pdv-touch-16 w-full rounded border border-white/15 bg-[var(--app-shell-bg)] px-2 py-1.5 text-[12px] font-semibold normal-case tracking-normal text-[var(--text-main)]"
                                                                :class="item.commission && !item.seller_id ? '!border-amber-400/60 !text-amber-200' : ''"
                                                            >
                                                                <option value="">— Selecione —</option>
                                                                @foreach ($professionals as $professional)
                                                                    <option value="{{ $professional->id }}">{{ $professional->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <template x-if="item.commission && !item.seller_id">
                                                            <span class="text-[10px] font-semibold uppercase tracking-wide text-amber-300">Obrigatório</span>
                                                        </template>
                                                        <template x-if="item.commission && item.seller_id">
                                                            <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-300">Comissão configurada</span>
                                                        </template>
                                                    </div>
                                                </template>
                                            </td>
                                            <td class="w-16 px-1 py-2">
                                                <input
                                                    x-model.number="item.quantity"
                                                    x-on:change="item.quantity = validQuantity(item.quantity)"
                                                    type="number"
                                                    min="1"
                                                    class="pdv-touch-16 w-full rounded border border-white/15 bg-[var(--app-shell-bg)] px-1 py-1.5 text-right text-base font-semibold text-[var(--text-main)] tabular-nums lg:py-1 lg:text-[11px]"
                                                >
                                            </td>
                                            <td class="w-20 px-2 py-2 text-right tabular-nums">
                                                <template x-if="item.type === 'service' && item.allow_price_edit">
                                                    <div class="space-y-1">
                                                        <input
                                                            x-model.number="item.price"
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            class="pdv-touch-16 w-24 rounded border border-[color-mix(in_srgb,var(--brand-primary)_28%,transparent)] bg-[var(--app-shell-bg)] px-2 py-1.5 text-right text-xs font-semibold text-[var(--brand-primary)] tabular-nums"
                                                        >
                                                        <input
                                                            x-model="item.price_adjustment_reason"
                                                            type="text"
                                                            maxlength="255"
                                                            class="w-24 rounded border border-white/10 bg-[var(--app-shell-bg)] px-2 py-1 text-[10px] text-[var(--text-main)]"
                                                            placeholder="Motivo"
                                                        >
                                                    </div>
                                                </template>
                                                <template x-if="!(item.type === 'service' && item.allow_price_edit)">
                                                    <span class="whitespace-nowrap" x-text="formatMoneyBRL(item.price)"></span>
                                                </template>
                                            </td>
                                            <td class="w-20 whitespace-nowrap px-2 py-2 text-right font-semibold text-[var(--text-main)] tabular-nums" x-text="formatMoneyBRL(Number(item.price || 0) * validQuantity(item.quantity))"></td>
                                            <td class="w-12 px-1 py-2 text-center">
                                                <button
                                                    type="button"
                                                    class="text-[10px] font-bold uppercase text-rose-300 hover:text-rose-200"
                                                    title="Remover linha"
                                                    x-on:click="removeItem(index)"
                                                >Excl.</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            {{-- 7 + 8. Rodapé totais + pagamento (padding extra no mobile para barra fixa do botão finalizar) --}}
            <div class="pdv-checkout border-t border-white/10 bg-[var(--app-shell-bg)] px-3 py-3 sm:px-4">
                <div class="grid grid-cols-2 gap-2 md:grid-cols-6 md:gap-3">
                    <div class="rounded-lg border border-white/10 bg-[var(--brand-accent)] p-2 sm:rounded-xl">
                        <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Volumes / Itens</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-[var(--text-main)] sm:text-2xl" x-text="formatQty(totalVolumeQty)"></p>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-[var(--brand-accent)] p-2 sm:rounded-xl">
                        <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Subtotal serviços</p>
                        <p class="mt-1 text-base font-bold sf-text-muted sm:text-lg">R$ <span class="tabular-nums text-[var(--text-main)]" x-text="formatMoneyBRL(subtotalServices)"></span></p>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-[var(--brand-accent)] p-2 sm:rounded-xl">
                        <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Subtotal produtos</p>
                        <p class="mt-1 text-base font-bold sf-text-muted sm:text-lg">R$ <span class="tabular-nums text-[var(--text-main)]" x-text="formatMoneyBRL(subtotalProducts)"></span></p>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-[var(--brand-accent)] p-2 sm:rounded-xl">
                        <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Assinaturas</p>
                        <p class="mt-1 text-base font-bold sf-text-muted sm:text-lg">R$ <span class="tabular-nums text-[var(--text-main)]" x-text="formatMoneyBRL(subtotalMemberships)"></span></p>
                    </div>
                    <div class="rounded-lg border border-rose-400/20 bg-[var(--brand-accent)] p-2 sm:rounded-xl">
                        <label for="pdv-discount-value" class="block text-[10px] font-bold uppercase tracking-[0.14em] text-rose-200/90">Desconto</label>
                        <div class="mt-2 grid grid-cols-[96px_minmax(0,1fr)] gap-2">
                            <select
                                id="pdv-discount-type"
                                name="discount_type"
                                class="pdv-touch-16 sf-select !border-white/15 !bg-[var(--brand-secondary)] !py-2 !text-base !text-[var(--text-main)] lg:!py-2 lg:!text-sm"
                                x-model="discountType"
                            >
                                <option value="fixed">R$</option>
                                <option value="percent">%</option>
                            </select>
                            <input
                                id="pdv-discount-value"
                                name="discount_value"
                                type="text"
                                inputmode="decimal"
                                placeholder="0,00"
                                autocomplete="off"
                                class="pdv-touch-16 sf-input w-full !border-white/15 !bg-[var(--brand-secondary)] !py-2 !text-base !text-[var(--text-main)] tabular-nums lg:!py-2 lg:!text-sm"
                                x-model="discountInput"
                            >
                        </div>
                        <p class="mt-2 text-[10px] text-rose-100/80">
                            Aplicado: R$ <span class="font-semibold tabular-nums" x-text="formatMoneyBRL(discountApplied)"></span>
                        </p>
                    </div>
                    <div class="col-span-2 rounded-lg border-2 border-[color-mix(in_srgb,var(--brand-primary)_40%,transparent)] bg-gradient-to-br from-[var(--brand-secondary)] to-[var(--brand-accent)] p-3 sm:rounded-xl md:col-span-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Total da venda</p>
                        <p class="mt-1 text-right text-3xl font-black tabular-nums text-[var(--brand-primary)] sm:text-4xl">
                            R$ <span x-text="formatMoneyBRL(total)"></span>
                        </p>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-12 lg:items-end">
                    <label class="lg:col-span-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Forma de pagamento</span>
                        <select
                            name="payment_method"
                            x-ref="paymentSelect"
                            required
                            class="pdv-touch-16 sf-select mt-2 !w-full !border-white/15 !bg-[var(--brand-secondary)] !py-2.5 !text-base !text-[var(--text-main)] lg:!py-2.5 lg:!text-sm"
                        >
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="lg:col-span-5">
                        <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--brand-primary)]">Observações</span>
                        <textarea
                            name="notes"
                            rows="2"
                            class="pdv-touch-16 sf-input mt-2 !min-h-[2.75rem] !w-full !resize-y !border-white/15 !bg-[var(--brand-secondary)] !py-2 !text-base !text-[var(--text-main)] placeholder:sf-text-muted/40 lg:!text-sm"
                            placeholder="Opcional"
                        >{{ old('notes') }}</textarea>
                    </label>
                    <div class="pdv-submit-wrap sticky bottom-0 z-30 -mx-3 rounded-t-xl border border-white/10 border-b-0 bg-[var(--app-shell-bg)]/98 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom,0px))] pt-2 shadow-[0_-12px_32px_rgba(0,0,0,0.35)] backdrop-blur-sm lg:col-span-3 lg:mx-0 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none lg:backdrop-blur-none">
                        <button
                            x-ref="submitBtn"
                            type="submit"
                            class="sf-button-primary w-full !py-3 !text-sm !font-black !uppercase !tracking-wider sm:!py-3.5 sm:!text-base disabled:opacity-40"
                            :disabled="!canSubmit"
                            :title="submitBlockReason"
                        >
                            <span x-text="submitting ? 'Finalizando…' : (hasMissingSeller ? 'Selecione vendedor com comissão' : 'Concluir venda')"></span>
                        </button>
                        <p x-show="submitBlockReason" x-cloak class="mt-2 text-center text-[11px] font-semibold text-amber-200" x-text="submitBlockReason"></p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-4 rounded-xl border border-rose-400/40 bg-rose-950/40 px-4 py-3 text-sm text-rose-100">
                        {{ $errors->first() }}
                    </div>
                @endif
            </div>

            {{-- Hidden payload --}}
            <template x-for="(item, idx) in servicePayload" :key="`s-${idx}-${item.service_id}`">
                <div>
                    <input type="hidden" :name="`service_items[${idx}][service_id]`" :value="item.service_id">
                    <input type="hidden" :name="`service_items[${idx}][unit_price]`" :value="item.unit_price">
                    <template x-if="item.price_adjustment_reason">
                        <input type="hidden" :name="`service_items[${idx}][price_adjustment_reason]`" :value="item.price_adjustment_reason">
                    </template>
                </div>
            </template>
            <input type="hidden" name="discount" :value="formatMoneyBRL(discountApplied).replace(/\./g, '').replace(',', '.')">
            <template x-for="(item, idx) in productPayload" :key="`p-${idx}-${item.product_id}`">
                <div>
                    <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id">
                    <input type="hidden" :name="`items[${idx}][quantity]`" :value="item.quantity">
                    <template x-if="item.seller_id">
                        <input type="hidden" :name="`items[${idx}][seller_id]`" :value="item.seller_id">
                    </template>
                </div>
            </template>
            <template x-for="(item, idx) in membershipPayload" :key="`m-${idx}-${item.membership_plan_id}`">
                <input type="hidden" :name="`membership_items[${idx}][membership_plan_id]`" :value="item.membership_plan_id">
            </template>

            {{-- 9. Barra de atalhos --}}
            <footer class="shrink-0 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 border-t border-white/10 bg-[var(--app-shell-bg)] px-2 py-1.5 text-[9px] font-semibold sf-text-muted sm:justify-between sm:px-3 sm:py-2 sm:text-[10px]">
                <div class="flex flex-wrap justify-center gap-x-3 gap-y-1 sm:justify-start">
                    <span><kbd class="rounded border border-white/20 bg-[var(--brand-secondary)] px-1.5 py-0.5 text-[var(--brand-primary)]">F2</kbd> Cliente</span>
                    <span><kbd class="rounded border border-white/20 bg-[var(--brand-secondary)] px-1.5 py-0.5 text-[var(--brand-primary)]">F3</kbd> Busca</span>
                    <span><kbd class="rounded border border-white/20 bg-[var(--brand-secondary)] px-1.5 py-0.5 text-[var(--brand-primary)]">Enter</kbd> Adicionar</span>
                    <span><kbd class="rounded border border-white/20 bg-[var(--brand-secondary)] px-1.5 py-0.5 text-[var(--brand-primary)]">Del</kbd> Remover último</span>
                    <span class="hidden sm:inline"><kbd class="rounded border border-white/20 bg-[var(--brand-secondary)] px-1.5 py-0.5 text-[var(--brand-primary)]">↑↓</kbd> Lista</span>
                    <span><kbd class="rounded border border-white/20 bg-[var(--brand-secondary)] px-1.5 py-0.5 text-[var(--brand-primary)]">F8</kbd> Pagamento</span>
                    <span><kbd class="rounded border border-white/20 bg-[var(--brand-secondary)] px-1.5 py-0.5 text-[var(--brand-primary)]">Esc</kbd> Limpar busca</span>
                    <span><kbd class="rounded border border-white/20 bg-[var(--brand-secondary)] px-1.5 py-0.5 text-[var(--brand-primary)]">F12</kbd> Finalizar</span>
                    <span><kbd class="rounded border border-white/20 bg-[var(--brand-secondary)] px-1.5 py-0.5 text-[var(--brand-primary)]">/</kbd> Busca</span>
                </div>
            </footer>
        </form>
    </div>

    <script>
        function pdvScreen(catalogData, initialCartData) {
            const root = catalogData && typeof catalogData === 'object' ? catalogData : {};
            const products = Array.isArray(root.products) ? root.products : [];
            const services = Array.isArray(root.services) ? root.services : [];
            const memberships = Array.isArray(root.memberships) ? root.memberships : [];

            const productCommissionMap = new Map();
            products.forEach((p) => {
                productCommissionMap.set(Number(p.id), Boolean(p.commission));
            });

            const bootCart = Array.isArray(initialCartData) && initialCartData.length
                ? initialCartData.map((row) => {
                    const id = row.id != null && row.id !== '' ? Number(row.id) : 0;
                    const rawType = String(row.type || '').toLowerCase();
                    const isProduct = rawType === 'product';
                    const isMembership = rawType === 'membership';
                    const type = isMembership ? 'membership' : (isProduct ? 'product' : 'service');
                    const serviceId = type === 'service' ? (row.service_id != null ? Number(row.service_id) : id) : undefined;

                    return {
                        ...row,
                        type,
                        id,
                        price: Number(row.price || 0),
                        quantity: Math.max(1, Number(row.quantity || 1)),
                        source: row.source || null,
                        service_id: serviceId,
                        membership_plan_id: isMembership ? Number(row.membership_plan_id || id) : undefined,
                        code: row.code || (isProduct ? 'P' + id : (isMembership ? 'A' + id : 'S' + (serviceId || id))),
                        image_url: row.image_url ?? null,
                        seller_id: isProduct && row.seller_id ? Number(row.seller_id) : '',
                        commission: isProduct ? Boolean(productCommissionMap.get(id) ?? row.commission) : false,
                        allow_price_edit: Boolean(row.allow_price_edit),
                        original_price: Number(row.original_price ?? row.price ?? 0),
                        price_adjustment_reason: row.price_adjustment_reason || '',
                    };
                })
                : [];

            return {
                submitting: false,
                search: '',
                highlightedIndex: 0,
                cart: bootCart,
                catalog: [...products, ...services, ...memberships],
                discountType: @json(old('discount_type', 'fixed')),
                discountInput: @json(old('discount_value', old('discount', '0'))),
                currentTime: '',
                previewImageFailed: false,
                init() {
                    this.tickClock();
                    setInterval(() => this.tickClock(), 1000);
                    this.$watch(
                        () => {
                            const v = this.visualItem;

                            return v ? `${v.type}-${v.id}-${v.image_url || ''}` : '';
                        },
                        () => {
                            this.previewImageFailed = false;
                        },
                    );
                    this.$nextTick(() => {
                        this.$refs.searchInput?.focus();
                    });
                    this.$watch('search', () => {
                        this.highlightedIndex = 0;
                    });
                },
                tickClock() {
                    const d = new Date();
                    this.currentTime = d.toLocaleTimeString('pt-BR') + ' | ' + d.toLocaleDateString('pt-BR');
                },
                formatMoneyBRL(value) {
                    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                formatQty(value) {
                    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
                },
                get filteredCatalog() {
                    const term = this.search.trim().toLowerCase();
                    if (!term) {
                        this.highlightedIndex = 0;

                        return [];
                    }
                    const rows = this.catalog
                        .filter((item) => {
                            const code = `${item.code || ''}`.toLowerCase();
                            const sku = `${item.sku || ''}`.toLowerCase();
                            const name = `${item.name}`.toLowerCase();

                            return name.includes(term) || code.includes(term) || sku.includes(term);
                        })
                        .slice(0, 24);
                    if (this.highlightedIndex >= rows.length) {
                        this.highlightedIndex = 0;
                    }

                    return rows;
                },
                get previewItem() {
                    return this.filteredCatalog[this.highlightedIndex] || null;
                },
                get visualItem() {
                    if (this.cart.length > 0) {
                        return this.cart[this.cart.length - 1];
                    }

                    return this.previewItem;
                },
                get previewQty() {
                    return this.previewItem ? '1' : '0';
                },
                get previewUnit() {
                    return this.previewItem ? Number(this.previewItem.price || 0) : 0;
                },
                get previewLineTotal() {
                    return this.previewItem ? this.previewUnit : 0;
                },
                get currentKindLabel() {
                    if (this.cart.length > 0) {
                        const last = this.cart[this.cart.length - 1];
                        return last.type === 'product' ? 'Produto' : 'Serviço';
                    }
                    const p = this.previewItem;
                    if (p) {
                        return p.type === 'product' ? 'Produto' : 'Serviço';
                    }
                    return 'Pronto para venda';
                },
                get bannerTitle() {
                    if (this.cart.length > 0) {
                        const last = this.cart[this.cart.length - 1];
                        return String(last.name || '').toUpperCase();
                    }
                    const p = this.previewItem;
                    if (p) {
                        return String(p.name || '').toUpperCase();
                    }
                    return 'Selecione um item';
                },
                get totalVolumeQty() {
                    return this.cart.reduce((acc, item) => acc + this.validQuantity(item.quantity), 0);
                },
                highlightNext() {
                    if (!this.filteredCatalog.length) {
                        return;
                    }
                    this.highlightedIndex = (this.highlightedIndex + 1) % this.filteredCatalog.length;
                },
                highlightPrev() {
                    if (!this.filteredCatalog.length) {
                        return;
                    }
                    this.highlightedIndex = (this.highlightedIndex - 1 + this.filteredCatalog.length) % this.filteredCatalog.length;
                },
                selectHighlighted() {
                    const item = this.filteredCatalog[this.highlightedIndex];
                    if (item) {
                        this.addCatalogItem(item);
                    }
                },
                addCatalogItem(item) {
                    const isService = item.type === 'service';
                    const isMembership = item.type === 'membership';
                    const defaultSellerId = this.$refs.professionalSelect?.value
                        ? Number(this.$refs.professionalSelect.value)
                        : '';

                    if (isService) {
                        const existing = this.cart.find((row) => String(row.type) === 'service' && Number(row.service_id ?? row.id) === Number(item.id));
                        if (existing) {
                            existing.quantity = this.validQuantity(existing.quantity) + 1;
                            this.search = '';
                            this.highlightedIndex = 0;

                            return;
                        }
                    }

                    if (isMembership) {
                        const existing = this.cart.find((row) => String(row.type) === 'membership' && Number(row.membership_plan_id ?? row.id) === Number(item.id));
                        if (existing) {
                            existing.quantity = this.validQuantity(existing.quantity) + 1;
                            this.search = '';
                            this.highlightedIndex = 0;

                            return;
                        }
                    }

                    this.cart.push({
                        id: item.id,
                        service_id: isService ? item.id : undefined,
                        membership_plan_id: isMembership ? item.id : undefined,
                        type: item.type,
                        code: item.code || (item.type === 'product' ? 'P' + item.id : (isMembership ? 'A' + item.id : 'S' + item.id)),
                        name: item.name,
                        price: Number(item.price || 0),
                        original_price: Number(item.price || 0),
                        quantity: 1,
                        source: null,
                        image_url: item.image_url ?? null,
                        seller_id: item.type === 'product' ? defaultSellerId : '',
                        commission: item.type === 'product' && Boolean(item.commission),
                        allow_price_edit: isService && Boolean(item.allow_price_edit),
                        price_adjustment_reason: '',
                    });
                    this.search = '';
                    this.highlightedIndex = 0;
                },
                removeItem(index) {
                    this.cart.splice(index, 1);
                },
                removeLastItem() {
                    if (this.cart.length === 0) {
                        return;
                    }
                    this.cart.pop();
                },
                validQuantity(value) {
                    const qty = Number.parseInt(value, 10);

                    return Number.isFinite(qty) && qty > 0 ? qty : 1;
                },
                submitSale(e) {
                    if (this.submitBlockReason) {
                        e.preventDefault();
                        this.submitting = false;

                        return;
                    }

                    this.cart = this.cart.map((item) => ({
                        ...item,
                        quantity: this.validQuantity(item.quantity),
                        price: Number.isFinite(Number(item.price)) && Number(item.price) >= 0 ? Number(item.price) : 0,
                    }));
                    this.submitting = true;
                },
                clearSearch() {
                    this.search = '';
                    this.highlightedIndex = 0;
                },
                handlePdvHotkeys(e) {
                    if (e.key === 'F2') {
                        e.preventDefault();
                        this.$refs.clientSelect?.focus();
                    }
                    if (e.key === 'F3') {
                        e.preventDefault();
                        this.$refs.searchInput?.focus();
                    }
                    if (e.key === 'F8') {
                        e.preventDefault();
                        this.$refs.paymentSelect?.focus();
                    }
                    if (e.key === 'F12') {
                        e.preventDefault();
                        if (this.submitting) {
                            return;
                        }
                        if (this.cart.length > 0 && this.$refs.submitBtn) {
                            this.$refs.submitBtn.click();
                        }
                    }
                    if (e.key === 'Delete') {
                        const tag = e.target?.tagName;
                        const type = e.target?.type;
                        if (tag === 'TEXTAREA' || tag === 'SELECT') {
                            return;
                        }
                        if (tag === 'INPUT' && type === 'number') {
                            return;
                        }
                        if (e.target === this.$refs.searchInput && String(this.search || '').length > 0) {
                            return;
                        }
                        if (tag === 'INPUT' && type === 'text' && e.target !== this.$refs.searchInput) {
                            return;
                        }
                        e.preventDefault();
                        this.removeLastItem();
                    }
                    if (e.key === 'Escape') {
                        const tag = e.target?.tagName;
                        if (tag === 'TEXTAREA' || tag === 'SELECT') {
                            return;
                        }
                        if (tag === 'INPUT' && e.target?.type === 'number') {
                            return;
                        }
                        e.preventDefault();
                        this.clearSearch();
                    }
                },
                get subtotalServices() {
                    return this.cart
                        .filter((item) => String(item.type) === 'service')
                        .reduce((acc, item) => acc + Number(item.price || 0) * this.validQuantity(item.quantity), 0);
                },
                get subtotalProducts() {
                    return this.cart
                        .filter((item) => String(item.type) === 'product')
                        .reduce((acc, item) => acc + Number(item.price || 0) * this.validQuantity(item.quantity), 0);
                },
                get subtotalMemberships() {
                    return this.cart
                        .filter((item) => String(item.type) === 'membership')
                        .reduce((acc, item) => acc + Number(item.price || 0) * this.validQuantity(item.quantity), 0);
                },
                get discountValue() {
                    const raw = String(this.discountInput || '').trim();
                    if (!raw) {
                        return 0;
                    }
                    const normalized = Number(
                        raw.replace(/\s/g, '').replace(/\./g, '').replace(',', '.'),
                    );

                    return Number.isFinite(normalized) && normalized > 0 ? normalized : 0;
                },
                get discountApplied() {
                    const subtotal = this.subtotalServices + this.subtotalProducts + this.subtotalMemberships;
                    if (subtotal <= 0) {
                        return 0;
                    }
                    if (this.discountType === 'percent') {
                        const percent = Math.max(0, Math.min(100, this.discountValue));
                        return (subtotal * percent) / 100;
                    }
                    return Math.min(this.discountValue, subtotal);
                },
                get total() {
                    const raw = this.subtotalServices + this.subtotalProducts + this.subtotalMemberships - this.discountApplied;

                    return raw > 0 ? raw : 0;
                },
                get submitBlockReason() {
                    if (this.submitting) {
                        return '';
                    }
                    if (this.cart.length === 0) {
                        return 'Adicione ao menos um servico, produto ou assinatura para concluir.';
                    }
                    if (this.hasMissingSeller) {
                        return 'Selecione o vendedor responsável para produtos com comissão.';
                    }
                    if (this.discountType === 'percent' && this.discountValue > 100) {
                        return 'Percentual de desconto não pode ser maior que 100%.';
                    }
                    if (this.discountType !== 'percent' && this.discountValue > (this.subtotalServices + this.subtotalProducts + this.subtotalMemberships)) {
                        return 'O desconto não pode ser maior que a soma dos subtotais.';
                    }

                    return '';
                },
                get canSubmit() {
                    return !this.submitBlockReason && !this.submitting;
                },
                get servicePayload() {
                    const rows = [];
                    this.cart
                        .filter((item) => String(item.type) === 'service')
                        .forEach((item) => {
                            const qty = this.validQuantity(item.quantity);
                            const sid = item.service_id ?? item.id;
                            for (let i = 0; i < qty; i++) {
                                rows.push({
                                    service_id: Number(sid),
                                    unit_price: Number.isFinite(Number(item.price)) && Number(item.price) >= 0 ? Number(item.price) : 0,
                                    price_adjustment_reason: item.price_adjustment_reason || '',
                                });
                            }
                        });
                    return rows;
                },
                get productPayload() {
                    return this.cart
                        .filter((item) => String(item.type) === 'product')
                        .map((item) => ({
                            product_id: item.id,
                            quantity: this.validQuantity(item.quantity),
                            seller_id: item.seller_id ? Number(item.seller_id) : '',
                        }));
                },
                get membershipPayload() {
                    const rows = [];
                    this.cart
                        .filter((item) => String(item.type) === 'membership')
                        .forEach((item) => {
                            const qty = this.validQuantity(item.quantity);
                            const planId = item.membership_plan_id ?? item.id;
                            for (let i = 0; i < qty; i++) {
                                rows.push({ membership_plan_id: Number(planId) });
                            }
                        });
                    return rows;
                },
                get hasMissingSeller() {
                    return this.cart.some((item) => String(item.type) === 'product' && Boolean(item.commission) && !item.seller_id);
                },
            };
        }
    </script>
@endsection
