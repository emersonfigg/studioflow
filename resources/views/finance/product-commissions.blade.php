<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Relatórios</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Comissões de produtos</h2>
                <p class="mt-2 text-sm sf-text-muted">Ranking de vendedores e detalhamento das comissões pagas por item.</p>
            </div>

            <form method="GET" action="{{ route('finance.product-commissions') }}" class="flex flex-wrap items-end gap-3">
                <label class="flex flex-col gap-1 text-[10px] font-semibold uppercase tracking-[0.18em] sf-text-muted">
                    Início
                    <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="sf-input min-w-[170px]">
                </label>
                <label class="flex flex-col gap-1 text-[10px] font-semibold uppercase tracking-[0.18em] sf-text-muted">
                    Fim
                    <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="sf-input min-w-[170px]">
                </label>
                @if ($canFilterProfessionals)
                    <label class="flex flex-col gap-1 text-[10px] font-semibold uppercase tracking-[0.18em] sf-text-muted">
                        Vendedor
                        <select name="user_id" class="sf-select min-w-[220px]">
                            <option value="">Todos os vendedores</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected($selectedUserId === $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="flex flex-col gap-1 text-[10px] font-semibold uppercase tracking-[0.18em] sf-text-muted">
                    Produto
                    <select name="product_id" class="sf-select min-w-[220px]">
                        <option value="">Todos os produtos</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected($selectedProductId === $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-[10px] font-semibold uppercase tracking-[0.18em] sf-text-muted">
                    Visualização
                    <select name="group_by" class="sf-select min-w-[180px]">
                        <option value="detail" @selected($groupBy === 'detail')>Detalhe por item</option>
                        <option value="seller" @selected($groupBy === 'seller')>Agrupado por vendedor</option>
                        <option value="product" @selected($groupBy === 'product')>Agrupado por produto</option>
                    </select>
                </label>
                <button class="sf-button-ghost">Filtrar</button>
            </form>
        </div>
    </x-slot>

    @include('finance.partials.nav', ['page' => $page])

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Total vendido (comissionado)</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($totalGrossCommissioned, 2, ',', '.') }}</p>
            <p class="mt-2 text-xs sf-text-muted">Soma dos itens de produto que geraram comissão.</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Total de comissões</p>
            <p class="mt-3 text-3xl font-semibold text-emerald-200">R$ {{ number_format($totalCommission, 2, ',', '.') }}</p>
            <p class="mt-2 text-xs sf-text-muted">Valor a repassar aos vendedores no período.</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Itens comissionados</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ number_format($totalQuantity, 0, ',', '.') }}</p>
            <p class="mt-2 text-xs sf-text-muted">Quantidade total de unidades vendidas.</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Vendedor destaque</p>
            @if ($topSeller && $topSeller['seller'])
                <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">{{ $topSeller['seller']->name }}</p>
                <p class="mt-2 text-xs sf-text-muted">Comissão acumulada: <span class="font-semibold brand-text">R$ {{ number_format((float) $topSeller['commission'], 2, ',', '.') }}</span></p>
            @else
                <p class="mt-3 text-base sf-text-muted">Nenhum vendedor com comissão no período.</p>
            @endif
        </article>
    </section>

    <section class="sf-card mt-6 overflow-hidden">
        <div class="border-b border-white/10 px-6 py-5">
            <h3 class="text-base font-semibold text-[var(--text-main)]">
                @switch($groupBy)
                    @case('seller') Agrupado por vendedor @break
                    @case('product') Agrupado por produto @break
                    @default Detalhe por item vendido
                @endswitch
            </h3>
            <p class="mt-1 text-sm sf-text-muted">Somente itens de vendas finalizadas. Valores históricos são preservados conforme a configuração do produto no momento da venda.</p>
        </div>

        @if ($items->isEmpty())
            <div class="px-6 py-12 text-center text-sm sf-text-muted">
                Nenhuma comissão de produto encontrada para os filtros selecionados.
            </div>
        @else
            <div class="overflow-x-auto">
                @if ($groupBy === 'seller')
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Vendedor</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Qtd</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Total vendido</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Comissão total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                            @foreach ($byUserTotals as $row)
                                <tr class="transition hover:bg-white/[0.03]">
                                    <td class="px-6 py-4 text-sm font-medium text-[var(--text-main)]">{{ $row['seller']?->name ?? '— Sem vendedor —' }}</td>
                                    <td class="px-6 py-4 text-right text-sm tabular-nums sf-text-muted">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right text-sm tabular-nums text-[var(--text-main)]">R$ {{ number_format($row['gross'], 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-emerald-200">R$ {{ number_format($row['commission'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif ($groupBy === 'product')
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Produto</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Qtd</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Total vendido</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Comissão total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                            @foreach ($byProductTotals as $row)
                                <tr class="transition hover:bg-white/[0.03]">
                                    <td class="px-6 py-4 text-sm font-medium text-[var(--text-main)]">{{ $row['product']?->name ?? '— Produto removido —' }}</td>
                                    <td class="px-6 py-4 text-right text-sm tabular-nums sf-text-muted">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right text-sm tabular-nums text-[var(--text-main)]">R$ {{ number_format($row['gross'], 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-emerald-200">R$ {{ number_format($row['commission'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Data</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Vendedor</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Produto</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Cliente</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Qtd</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Total vendido</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Regra</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Comissão</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8 bg-[var(--card-bg)]">
                            @foreach ($items as $item)
                                @php($sale = $item->sale)
                                <tr class="transition hover:bg-white/[0.03]">
                                    <td class="px-6 py-4 text-sm tabular-nums sf-text-muted">{{ $sale?->sold_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-[var(--text-main)]">{{ $item->seller?->name ?? '— Sem vendedor —' }}</td>
                                    <td class="px-6 py-4 text-sm text-[var(--text-main)]">{{ $item->product?->name ?? '— Produto removido —' }}</td>
                                    <td class="px-6 py-4 text-sm sf-text-muted">{{ $sale?->client?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 text-right text-sm tabular-nums sf-text-muted">{{ number_format((int) $item->quantity, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right text-sm tabular-nums text-[var(--text-main)]">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-sm sf-text-muted">
                                        @if ($item->commission_type_snapshot === 'fixed')
                                            R$ {{ number_format((float) $item->commission_value_snapshot, 2, ',', '.') }} / un.
                                        @elseif ($item->commission_type_snapshot === 'percentage')
                                            {{ number_format((float) $item->commission_value_snapshot, 2, ',', '.') }}%
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-emerald-200">R$ {{ number_format((float) $item->commission_amount, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </section>
</x-app-layout>
