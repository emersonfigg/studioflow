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

    <div class="pdv-page mx-auto flex w-full max-w-[1800px] flex-1 flex-col px-0 pb-0 max-lg:flex-none sm:px-0.5 lg:min-h-0 lg:flex-1 lg:px-1">
        @if (is_array($saleResult))
            <div
                class="mb-2 shrink-0 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-500/40 bg-emerald-950/55 px-3 py-2 text-sm text-emerald-50 shadow-md sm:px-4"
                role="status"
            >
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-white">
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
                    <a href="{{ route('pdv.sales') }}" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-[#c7d2e3] hover:bg-white/10">Histórico</a>
                    @if (! empty($saleResult['receipt_url']))
                        <a
                            href="{{ $saleResult['receipt_url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-lg border border-emerald-400/50 bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500"
                        >{{ ! empty($saleResult['appointment_completed']) ? 'Imprimir comprovante' : 'Imprimir' }}</a>
                    @endif
                    @if (empty($saleResult['appointment_completed']))
                        <a href="{{ route('product-sales.index') }}" class="rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/15">Ver venda</a>
                    @endif
                    <a href="{{ route('pdv.index') }}" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-emerald-100 hover:bg-white/10">Nova venda</a>
                    @if (! empty($saleResult['appointment_completed']))
                        <a href="{{ route('appointments.index') }}" class="rounded-lg border border-[#d4af37]/40 bg-[#d4af37]/15 px-3 py-1.5 text-xs font-semibold text-[#d4af37] hover:bg-[#d4af37]/25">Voltar para agendamentos</a>
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
            x-on:submit="submitting = true"
            class="pdv-frame flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-visible rounded-2xl border border-[#d4af37]/25 bg-[#132746] shadow-[0_24px_48px_rgba(9,20,45,0.45)] ring-1 ring-[#d4af37]/10 lg:max-h-full lg:overflow-hidden"
        >
            @csrf
            {{-- Hook para testes e inspeção: carrinho inicial server-side (Alpine usa o mesmo dado via @js acima) --}}
            <script type="application/json" id="pdv-initial-cart-data" class="hidden">@json($initialCart ?? [])</script>
            @if (isset($pdvAppointment) && $pdvAppointment && isset($appointmentSummary))
                <input type="hidden" name="appointment_id" value="{{ $pdvAppointment->id }}">
                <div class="shrink-0 border-b border-[#d4af37]/35 bg-[#1b335b] px-3 py-2 text-xs text-[#c7d2e3] sm:px-4 sm:text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#d4af37]">Atendimento vinculado ao agendamento #{{ $appointmentSummary['id'] }}</p>
                        @if (! empty($appointmentSummary['service_labels']))
                            <span class="inline-flex items-center rounded-full border border-emerald-400/35 bg-emerald-500/15 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-100">
                                Serviços carregados do agendamento
                            </span>
                        @endif
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <p><span class="text-white/60">Cliente:</span> <span class="font-medium text-white">{{ $appointmentSummary['client_name'] ?? '—' }}</span></p>
                        <p><span class="text-white/60">Profissional:</span> <span class="font-medium text-white">{{ $appointmentSummary['professional_name'] ?? '—' }}</span></p>
                        <p><span class="text-white/60">Horário:</span> <span class="font-medium text-white">{{ $appointmentSummary['start_time'] ?? '—' }}@if (! empty($appointmentSummary['end_time'])) – {{ $appointmentSummary['end_time'] }}@endif</span></p>
                        <p><span class="text-white/60">Serviços (referência):</span> <span class="font-semibold text-[#d4af37]">R$ {{ $appointmentSummary['services_total_formatted'] }}</span></p>
                    </div>
                    @if (! empty($appointmentSummary['service_labels']))
                        <p class="mt-2 text-xs text-[#c7d2e3]">{{ implode(' · ', $appointmentSummary['service_labels']) }}</p>
                    @endif
                    @if (! empty($appointmentSummary['commission_reference']))
                        <p class="mt-2 text-xs text-[#c7d2e3]/90">{{ $appointmentSummary['commission_reference'] }}</p>
                    @endif
                </div>
            @endif

            {{-- 2. Barra superior (identidade StudioFlow) --}}
            <header class="shrink-0 flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-white/10 bg-[#1b335b] px-3 py-2 text-[11px] text-[#c7d2e3] sm:px-4 sm:text-xs">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="whitespace-nowrap font-bold tracking-wide text-[#d4af37]">PDV — Ponto de Venda</span>
                    <span class="hidden h-4 w-px bg-white/15 sm:inline-block" aria-hidden="true"></span>
                    <span class="font-semibold text-white">Operador: <span class="text-[#c7d2e3]">{{ auth()->user()->name }}</span></span>
                    <a href="{{ route('pdv.sales') }}" class="ml-auto inline-flex items-center rounded-lg border border-white/15 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#c7d2e3] hover:bg-white/10 sm:ml-0">Histórico de vendas</a>
                </div>
                <div class="flex w-full flex-wrap items-end gap-3 lg:w-auto lg:flex-nowrap">
                    <label class="grid min-w-[140px] flex-1 gap-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-[#d4af37]/90 lg:max-w-[200px]">
                        Cliente
                        <select
                            name="client_id"
                            x-ref="clientSelect"
                            class="pdv-touch-16 sf-select !border-white/15 !bg-[#223d69] !py-2 !text-base !text-white lg:!py-2 lg:!text-sm"
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
                    <label class="grid min-w-[140px] flex-1 gap-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-[#d4af37]/90 lg:max-w-[200px]">
                        Profissional
                        <select
                            name="user_id"
                            x-ref="professionalSelect"
                            class="pdv-touch-16 sf-select !border-white/15 !bg-[#223d69] !py-2 !text-base !text-white lg:!py-2 lg:!text-sm"
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
                    <span class="tabular-nums text-[#c7d2e3]" x-text="currentTime"></span>
                    <span
                        class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide
                        @if ($cashRegister?->closed_at)
                            border-white/20 bg-white/10 text-[#c7d2e3]
                        @elseif ($cashRegister)
                            border-[#d4af37]/40 bg-[#d4af37]/15 text-[#d4af37]
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
            <div class="shrink-0 border-b border-[#d4af37]/20 bg-gradient-to-r from-[#223d69] via-[#1b335b] to-[#132746] px-3 py-2 sm:px-4 sm:py-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#d4af37]" x-text="currentKindLabel"></p>
                <p class="mt-0.5 text-center text-lg font-bold uppercase leading-tight tracking-tight text-white sm:text-xl lg:text-2xl" x-text="bannerTitle"></p>
            </div>

            {{-- Corpo: 3 colunas (desktop). Mobile: grid sem flex-1 para rolar página inteira; busca em order-1 para ficar logo após o banner; carrinho com altura máx. e rolagem interna (não “roubar” a busca). --}}
            <div class="flex max-lg:flex-none max-lg:min-h-0 min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-visible lg:grid lg:min-h-0 lg:flex-1 lg:grid-cols-12 lg:grid-rows-[minmax(0,1fr)] lg:divide-x lg:divide-white/10 lg:overflow-hidden">
                {{-- 4. Esquerda: imagem / ícone + VENDA --}}
                <aside class="flex max-lg:order-2 shrink-0 flex-col gap-2 border-b border-white/10 p-3 lg:order-none lg:col-span-3 lg:flex lg:max-h-none lg:min-h-0 lg:flex-col lg:justify-start lg:overflow-y-auto lg:border-b-0 lg:p-4">
                    <div class="relative flex aspect-auto max-h-[120px] min-h-[92px] w-full shrink-0 overflow-hidden rounded-xl border border-white/10 bg-[#223d69] shadow-inner lg:aspect-square lg:max-h-[clamp(104px,20vh,160px)]">
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
                                <svg class="h-16 w-16 text-[#d4af37]/90 sm:h-20 sm:w-20 lg:h-24 lg:w-24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M7 4h10l1 2h2v2H4V6h2l1-2zm0 4h10v10a2 2 0 01-2 2H9a2 2 0 01-2-2V8zm2 2v8h6v-8H9z"/>
                                </svg>
                            </template>
                            <template x-if="visualItem && visualItem.type === 'service'">
                                <svg class="h-16 w-16 text-[#d4af37]/90 sm:h-20 sm:w-20 lg:h-24 lg:w-24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                                </svg>
                            </template>
                            <template x-if="!visualItem">
                                <svg class="h-16 w-16 text-[#d4af37]/50 sm:h-20 sm:w-20 lg:h-24 lg:w-24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                </svg>
                            </template>
                        </div>
                    </div>
                    <div class="rounded-xl border border-[#d4af37]/30 bg-[#d4af37]/10 py-2 text-center lg:rounded-2xl lg:py-3">
                        <span class="text-sm font-black uppercase tracking-[0.22em] text-[#d4af37] sm:text-base lg:text-lg">Venda</span>
                        <p class="mt-0.5 text-[10px] font-semibold uppercase text-[#c7d2e3]">Item atual</p>
                    </div>
                </aside>

                {{-- 5. Centro: campos grandes + sugestões (shrink-0: nunca colapsar no flex mobile) --}}
                <section class="flex max-lg:order-1 min-h-0 shrink-0 flex-col gap-2 border-b border-white/10 p-3 max-lg:overflow-visible lg:order-none lg:col-span-4 lg:flex lg:min-h-0 lg:flex-col lg:overflow-hidden lg:border-b-0 lg:p-4">
                    <label class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#d4af37]" for="pdv-search-input">Código / SKU / Nome</label>
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
                            class="pdv-touch-16 sf-input mt-2 !border-white/15 !bg-[#223d69] !py-3 !text-base !font-semibold !text-white placeholder:text-[#c7d2e3]/50 lg:!py-3.5 lg:!text-lg"
                            placeholder="Buscar ou escanear…"
                        >
                        <div
                            x-cloak
                            x-show="search.trim().length > 0 && filteredCatalog.length > 0"
                            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-[min(18rem,45dvh)] overflow-y-auto overscroll-contain rounded-xl border border-white/15 bg-[#132746] py-1 shadow-[0_16px_40px_rgba(9,20,45,0.55)] ring-1 ring-[#d4af37]/15 lg:max-h-[min(22rem,50vh)]"
                            role="listbox"
                            aria-label="Sugestões de produtos e serviços"
                        >
                            <template x-for="(item, idx) in filteredCatalog" :key="`${item.type}-${item.id}`">
                                <button
                                    type="button"
                                    role="option"
                                    :aria-selected="highlightedIndex === idx ? 'true' : 'false'"
                                    x-on:click="addCatalogItem(item)"
                                    :class="highlightedIndex === idx ? 'border-[#d4af37]/50 bg-[#d4af37]/15' : 'border-transparent hover:bg-white/5'"
                                    class="flex w-full flex-col rounded-lg border px-3 py-2.5 text-left transition"
                                >
                                    <span class="font-mono text-xs font-bold text-[#d4af37]" x-text="item.code"></span>
                                    <span class="mt-0.5 block truncate text-sm text-white" x-text="item.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <p class="mt-1 text-[10px] text-[#c7d2e3]/85">Digite código, SKU ou nome — as sugestões aparecem logo abaixo do campo.</p>

                    <div class="mt-2 grid shrink-0 gap-2 sm:grid-cols-3 sm:gap-3">
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#c7d2e3]">Quantidade</label>
                            <div class="sf-input mt-1 !border-white/15 !bg-[#1b335b] !py-2.5 !text-right !text-lg !font-bold !text-white tabular-nums sm:!py-3 sm:!text-xl" x-text="previewQty"></div>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#c7d2e3]">Preço unitário</label>
                            <div class="sf-input mt-1 !border-white/15 !bg-[#1b335b] !py-2.5 !text-right !text-lg !font-bold !text-[#d4af37] tabular-nums sm:!py-3 sm:!text-xl">
                                R$ <span x-text="formatMoneyBRL(previewUnit)"></span>
                            </div>
                        </div>
                        <div class="sm:col-span-1">
                            <label class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#c7d2e3]">Preço total</label>
                            <div class="sf-input mt-1 !border-[#d4af37]/35 !bg-[#223d69] !py-2.5 !text-right !text-lg !font-bold !text-white tabular-nums sm:!py-3 sm:!text-xl">
                                R$ <span x-text="formatMoneyBRL(previewLineTotal)"></span>
                            </div>
                        </div>
                    </div>

                    <p x-show="search.trim().length > 0 && filteredCatalog.length === 0" x-cloak class="mt-2 rounded-lg border border-white/10 bg-[#1b335b]/60 px-3 py-2 text-center text-sm text-[#c7d2e3]">
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
                <section class="flex max-lg:order-3 max-lg:max-h-[min(52dvh,22rem)] max-lg:shrink-0 max-lg:flex-none min-h-0 flex-1 flex-col overflow-hidden p-3 lg:order-none lg:col-span-5 lg:max-h-none lg:min-h-0 lg:flex lg:h-auto lg:flex-1 lg:self-stretch lg:p-4">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-[#d4af37] lg:hidden">Itens da venda</p>
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-white/10 bg-[#223d69] shadow-inner lg:rounded-2xl">
                        <div class="min-h-0 flex-1 overflow-x-auto overflow-y-auto overscroll-contain">
                            <table class="w-full min-w-0 border-collapse text-left text-[11px] text-[#c7d2e3] sm:min-w-[480px]">
                                <thead>
                                    <tr class="border-b border-dashed border-white/25 bg-[#132746] text-[10px] font-bold uppercase tracking-[0.12em] text-[#d4af37]">
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
                                            <td colspan="7" class="px-4 py-12 text-center text-sm text-[#c7d2e3]">Nenhum item lançado.</td>
                                        </tr>
                                    </template>
                                    <template x-for="(item, index) in cart" :key="`${item.type}-${item.id}-${index}`">
                                        <tr class="border-b border-white/5 odd:bg-[#1b335b]/40 even:bg-transparent">
                                            <td class="w-10 px-2 py-2 font-mono text-white" x-text="String(index + 1).padStart(4, '0')"></td>
                                            <td class="w-20 px-1 py-2 font-mono text-[#d4af37]" x-text="item.code"></td>
                                            <td class="max-w-[1px] px-2 py-2">
                                                <span class="block truncate text-white" x-text="item.name"></span>
                                            </td>
                                            <td class="w-16 px-1 py-2">
                                                <input
                                                    x-model.number="item.quantity"
                                                    type="number"
                                                    min="1"
                                                    class="pdv-touch-16 w-full rounded border border-white/15 bg-[#1b335b] px-1 py-1.5 text-right text-base font-semibold text-white tabular-nums lg:py-1 lg:text-[11px]"
                                                >
                                            </td>
                                            <td class="w-20 whitespace-nowrap px-2 py-2 text-right tabular-nums" x-text="formatMoneyBRL(item.price)"></td>
                                            <td class="w-20 whitespace-nowrap px-2 py-2 text-right font-semibold text-white tabular-nums" x-text="formatMoneyBRL(item.price * Math.max(1, Number(item.quantity || 1)))"></td>
                                            <td class="w-12 px-1 py-2 text-center">
                                                <button
                                                    type="button"
                                                    class="text-[10px] font-bold uppercase"
                                                    :class="item.source === 'appointment' ? 'cursor-not-allowed text-white/25' : 'text-rose-300 hover:text-rose-200'"
                                                    :disabled="item.source === 'appointment'"
                                                    :title="item.source === 'appointment' ? 'Serviço do agendamento (fixo)' : 'Remover linha'"
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
            <div class="shrink-0 border-t border-white/10 bg-[#0f203b] px-3 py-2 max-lg:pb-[max(5.5rem,env(safe-area-inset-bottom,0px))] sm:px-4 sm:py-3 lg:pb-2">
                <div class="grid grid-cols-2 gap-2 md:grid-cols-6 md:gap-3">
                    <div class="rounded-lg border border-white/10 bg-[#132746] p-2 sm:rounded-xl sm:p-3">
                        <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Volumes / Itens</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-white sm:text-2xl" x-text="formatQty(totalVolumeQty)"></p>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-[#132746] p-2 sm:rounded-xl sm:p-3">
                        <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Subtotal serviços</p>
                        <p class="mt-1 text-base font-bold text-[#c7d2e3] sm:text-lg">R$ <span class="tabular-nums text-white" x-text="formatMoneyBRL(subtotalServices)"></span></p>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-[#132746] p-2 sm:rounded-xl sm:p-3">
                        <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Subtotal produtos</p>
                        <p class="mt-1 text-base font-bold text-[#c7d2e3] sm:text-lg">R$ <span class="tabular-nums text-white" x-text="formatMoneyBRL(subtotalProducts)"></span></p>
                    </div>
                    <div class="rounded-lg border border-rose-400/20 bg-[#132746] p-2 sm:rounded-xl sm:p-3">
                        <label for="pdv-discount-value" class="block text-[10px] font-bold uppercase tracking-[0.14em] text-rose-200/90">Desconto</label>
                        <div class="mt-2 grid grid-cols-[96px_minmax(0,1fr)] gap-2">
                            <select
                                id="pdv-discount-type"
                                name="discount_type"
                                class="pdv-touch-16 sf-select !border-white/15 !bg-[#223d69] !py-2 !text-base !text-white lg:!py-2 lg:!text-sm"
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
                                class="pdv-touch-16 sf-input w-full !border-white/15 !bg-[#223d69] !py-2 !text-base !text-white tabular-nums lg:!py-2 lg:!text-sm"
                                x-model="discountInput"
                            >
                        </div>
                        <p class="mt-2 text-[10px] text-rose-100/80">
                            Aplicado: R$ <span class="font-semibold tabular-nums" x-text="formatMoneyBRL(discountApplied)"></span>
                        </p>
                    </div>
                    <div class="col-span-2 rounded-lg border-2 border-[#d4af37]/40 bg-gradient-to-br from-[#223d69] to-[#132746] p-3 sm:rounded-xl sm:p-4 md:col-span-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#d4af37]">Total da venda</p>
                        <p class="mt-1 text-right text-3xl font-black tabular-nums text-[#d4af37] sm:text-4xl">
                            R$ <span x-text="formatMoneyBRL(total)"></span>
                        </p>
                    </div>
                </div>

                <div class="mt-2 grid grid-cols-1 gap-3 lg:mt-3 lg:grid-cols-12 lg:items-end">
                    <label class="lg:col-span-4">
                        <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Forma de pagamento</span>
                        <select
                            name="payment_method"
                            x-ref="paymentSelect"
                            required
                            class="pdv-touch-16 sf-select mt-2 !w-full !border-white/15 !bg-[#223d69] !py-2.5 !text-base !text-white lg:!py-2.5 lg:!text-sm"
                        >
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="lg:col-span-5">
                        <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#d4af37]">Observações</span>
                        <textarea
                            name="notes"
                            rows="2"
                            class="pdv-touch-16 sf-input mt-2 !max-h-[3.75rem] !min-h-[2.5rem] !w-full !resize-y !border-white/15 !bg-[#223d69] !py-2 !text-base !text-white placeholder:text-[#c7d2e3]/40 lg:!text-sm"
                            placeholder="Opcional"
                        >{{ old('notes') }}</textarea>
                    </label>
                    <div class="max-lg:sticky max-lg:bottom-0 max-lg:z-40 max-lg:-mx-3 max-lg:rounded-t-xl max-lg:border max-lg:border-white/10 max-lg:border-b-0 max-lg:bg-[#0f203b]/98 max-lg:px-3 max-lg:pt-2 max-lg:pb-[max(0.75rem,env(safe-area-inset-bottom,0px))] max-lg:shadow-[0_-12px_32px_rgba(0,0,0,0.45)] max-lg:backdrop-blur-sm lg:col-span-3 lg:mx-0 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none lg:backdrop-blur-none">
                        <button
                            x-ref="submitBtn"
                            type="submit"
                            class="sf-button-primary w-full !py-3 !text-sm !font-black !uppercase !tracking-wider sm:!py-3.5 sm:!text-base disabled:opacity-40"
                            :disabled="cart.length === 0 || submitting"
                        >
                            <span x-text="submitting ? 'Finalizando…' : 'Concluir venda'"></span>
                        </button>
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
                <input type="hidden" :name="`service_items[${idx}][service_id]`" :value="item.service_id">
            </template>
            <input type="hidden" name="discount" :value="formatMoneyBRL(discountApplied).replace(/\./g, '').replace(',', '.')">
            <template x-for="(item, idx) in productPayload" :key="`p-${idx}-${item.product_id}`">
                <div>
                    <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id">
                    <input type="hidden" :name="`items[${idx}][quantity]`" :value="item.quantity">
                </div>
            </template>

            {{-- 9. Barra de atalhos --}}
            <footer class="shrink-0 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 border-t border-white/10 bg-[#1b335b] px-2 py-1.5 text-[9px] font-semibold text-[#c7d2e3] sm:justify-between sm:px-3 sm:py-2 sm:text-[10px]">
                <div class="flex flex-wrap justify-center gap-x-3 gap-y-1 sm:justify-start">
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">F2</kbd> Cliente</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">F3</kbd> Busca</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">Enter</kbd> Adicionar</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">Del</kbd> Remover último</span>
                    <span class="hidden sm:inline"><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">↑↓</kbd> Lista</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">F8</kbd> Pagamento</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">Esc</kbd> Limpar busca</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">F12</kbd> Finalizar</span>
                    <span><kbd class="rounded border border-white/20 bg-[#223d69] px-1.5 py-0.5 text-[#d4af37]">/</kbd> Busca</span>
                </div>
            </footer>
        </form>
    </div>

    <script>
        function pdvScreen(catalogData, initialCartData) {
            const root = catalogData && typeof catalogData === 'object' ? catalogData : {};
            const products = Array.isArray(root.products) ? root.products : [];
            const services = Array.isArray(root.services) ? root.services : [];

            const bootCart = Array.isArray(initialCartData) && initialCartData.length
                ? initialCartData.map((row) => {
                    const id = row.id != null && row.id !== '' ? Number(row.id) : 0;
                    const isProduct = String(row.type || '').toLowerCase() === 'product';
                    const type = isProduct ? 'product' : 'service';
                    const serviceId = isProduct ? undefined : (row.service_id != null ? Number(row.service_id) : id);

                    return {
                        ...row,
                        type,
                        id,
                        price: Number(row.price || 0),
                        quantity: Math.max(1, Number(row.quantity || 1)),
                        source: row.source || null,
                        service_id: serviceId,
                        code: row.code || (isProduct ? 'P' + id : 'S' + (serviceId || id)),
                        image_url: row.image_url ?? null,
                    };
                })
                : [];

            return {
                submitting: false,
                search: '',
                highlightedIndex: 0,
                cart: bootCart,
                catalog: [...products, ...services],
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
                    return this.cart.reduce((acc, item) => acc + Math.max(1, Number(item.quantity || 1)), 0);
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
                    this.cart.push({
                        id: item.id,
                        service_id: isService ? item.id : undefined,
                        type: item.type,
                        code: item.code || (item.type === 'product' ? 'P' + item.id : 'S' + item.id),
                        name: item.name,
                        price: Number(item.price || 0),
                        quantity: 1,
                        source: null,
                        image_url: item.image_url ?? null,
                    });
                    this.search = '';
                    this.highlightedIndex = 0;
                },
                removeItem(index) {
                    if (this.cart[index]?.source === 'appointment') {
                        return;
                    }
                    this.cart.splice(index, 1);
                },
                removeLastItem() {
                    if (this.cart.length === 0) {
                        return;
                    }
                    const last = this.cart[this.cart.length - 1];
                    if (last?.source === 'appointment') {
                        return;
                    }
                    this.cart.pop();
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
                        .reduce((acc, item) => acc + item.price * Math.max(1, Number(item.quantity || 1)), 0);
                },
                get subtotalProducts() {
                    return this.cart
                        .filter((item) => String(item.type) === 'product')
                        .reduce((acc, item) => acc + item.price * Math.max(1, Number(item.quantity || 1)), 0);
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
                    const subtotal = this.subtotalServices + this.subtotalProducts;
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
                    const raw = this.subtotalServices + this.subtotalProducts - this.discountApplied;

                    return raw > 0 ? raw : 0;
                },
                get servicePayload() {
                    const rows = [];
                    this.cart
                        .filter((item) => String(item.type) === 'service')
                        .forEach((item) => {
                            const qty = Math.max(1, Number(item.quantity || 1));
                            const sid = item.service_id ?? item.id;
                            for (let i = 0; i < qty; i++) {
                                rows.push({ service_id: Number(sid) });
                            }
                        });
                    return rows;
                },
                get productPayload() {
                    return this.cart
                        .filter((item) => String(item.type) === 'product')
                        .map((item) => ({ product_id: item.id, quantity: Math.max(1, Number(item.quantity || 1)) }));
                },
            };
        }
    </script>
@endsection
