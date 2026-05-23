<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">Estoque</p>
                <h1 class="mt-1 text-2xl font-semibold sf-text">Auditoria</h1>
            </div>
            <a href="{{ route('stock.index') }}" class="sf-button-secondary">Voltar</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <form method="GET" class="sf-card grid gap-4 p-5 md:grid-cols-6">
            <div><x-input-label for="date_from" value="Inicial" /><x-text-input id="date_from" name="date_from" type="date" class="mt-2 block w-full" :value="request('date_from', $dateFrom->toDateString())" /></div>
            <div><x-input-label for="date_to" value="Final" /><x-text-input id="date_to" name="date_to" type="date" class="mt-2 block w-full" :value="request('date_to', $dateTo->toDateString())" /></div>
            <div>
                <x-input-label for="product_id" value="Produto" />
                <select id="product_id" name="product_id" class="mt-2 block w-full rounded-xl border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--input-bg)] sf-text"><option value="">Todos</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected((int) request('product_id') === $product->id)>{{ $product->name }}</option>@endforeach</select>
            </div>
            <div>
                <x-input-label for="category" value="Categoria" />
                <select id="category" name="category" class="mt-2 block w-full rounded-xl border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--input-bg)] sf-text"><option value="">Todas</option>@foreach ($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach</select>
            </div>
            <label class="mt-8 flex items-center gap-2 text-sm sf-text-muted"><input type="checkbox" name="only_divergent" value="1" @checked(request()->boolean('only_divergent'))> Divergentes</label>
            <div class="flex items-end"><button class="sf-button-primary w-full">Filtrar</button></div>
        </form>

        <div class="sf-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-sm">
                    <thead class="text-left text-xs uppercase tracking-[0.16em] sf-text-muted">
                        <tr>
                            <th class="px-5 py-3">Produto</th>
                            <th class="px-5 py-3">Inicial</th>
                            <th class="px-5 py-3">Entradas</th>
                            <th class="px-5 py-3">Vendas</th>
                            <th class="px-5 py-3">Outras saidas</th>
                            <th class="px-5 py-3">Ajustes</th>
                            <th class="px-5 py-3">Esperado</th>
                            <th class="px-5 py-3">Atual</th>
                            <th class="px-5 py-3">Diferenca</th>
                            <th class="px-5 py-3">Diaria pendente</th>
                            <th class="px-5 py-3">Diaria aplicada</th>
                            <th class="px-5 py-3">Conf. pendente</th>
                            <th class="px-5 py-3">Conf. aplicada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-5 py-4 font-semibold sf-text">{{ $row['product']->name }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ number_format($row['opening'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 text-emerald-200">{{ number_format($row['in'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 text-rose-200">{{ number_format($row['sale_out'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 text-rose-200">{{ number_format($row['other_out'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ number_format($row['adjustments'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 sf-text-muted">{{ number_format($row['expected'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 sf-text">{{ number_format($row['current'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 {{ abs($row['difference']) > 0.000001 ? 'text-[var(--brand-primary)]' : 'sf-text-muted' }}">{{ number_format($row['difference'], 2, ',', '.') }}</td>
                                <td class="px-5 py-4 {{ abs($row['pending_daily_difference']) > 0.000001 ? 'text-amber-200' : 'sf-text-muted' }}">
                                    {{ number_format($row['pending_daily_difference'], 2, ',', '.') }}
                                    <span class="block text-xs sf-text-muted">R$ {{ number_format($row['pending_daily_value'], 2, ',', '.') }}</span>
                                </td>
                                <td class="px-5 py-4 {{ abs($row['applied_daily_difference']) > 0.000001 ? 'text-[var(--brand-primary)]' : 'sf-text-muted' }}">
                                    {{ number_format($row['applied_daily_difference'], 2, ',', '.') }}
                                    <span class="block text-xs sf-text-muted">R$ {{ number_format($row['applied_daily_value'], 2, ',', '.') }}</span>
                                </td>
                                <td class="px-5 py-4 {{ abs($row['pending_count_difference']) > 0.000001 ? 'text-amber-200' : 'sf-text-muted' }}">
                                    {{ number_format($row['pending_count_difference'], 2, ',', '.') }}
                                    <span class="block text-xs sf-text-muted">R$ {{ number_format($row['pending_count_value'], 2, ',', '.') }}</span>
                                </td>
                                <td class="px-5 py-4 {{ abs($row['applied_count_difference']) > 0.000001 ? 'text-[var(--brand-primary)]' : 'sf-text-muted' }}">
                                    {{ number_format($row['applied_count_difference'], 2, ',', '.') }}
                                    <span class="block text-xs sf-text-muted">R$ {{ number_format($row['applied_count_value'], 2, ',', '.') }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
