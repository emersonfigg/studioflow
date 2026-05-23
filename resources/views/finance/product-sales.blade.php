<x-app-layout>
    <x-slot name="header">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="sf-page-eyebrow">Relatorios</p>
                <h2 class="sf-page-title mt-2">Vendas de produtos</h2>
            </div>
            <a href="{{ route('finance.index') }}" class="sf-button-ghost">Voltar</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <form class="sf-card grid gap-3 p-4 lg:grid-cols-6">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="sf-input">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="sf-input">
            <select name="product_id" class="sf-select">
                <option value="">Produto</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected($selectedProductId === $product->id)>{{ $product->name }}</option>
                @endforeach
            </select>
            <select name="category" class="sf-select">
                <option value="">Categoria</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
                @endforeach
            </select>
            <select name="payment_method" class="sf-select">
                <option value="">Pagamento</option>
                @foreach ($paymentMethods as $value => $label)
                    <option value="{{ $value }}" @selected($selectedPaymentMethod === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="sf-button-primary">Filtrar</button>
        </form>

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="sf-card p-4"><p class="sf-page-eyebrow">Itens vendidos</p><p class="mt-2 text-2xl font-black brand-text">{{ $totalQuantity }}</p></div>
            <div class="sf-card p-4"><p class="sf-page-eyebrow">Bruto</p><p class="mt-2 text-2xl font-black brand-text">R$ {{ number_format($totalGross, 2, ',', '.') }}</p></div>
            <div class="sf-card p-4"><p class="sf-page-eyebrow">Descontos</p><p class="mt-2 text-2xl font-black brand-text">R$ {{ number_format($totalDiscount, 2, ',', '.') }}</p></div>
            <div class="sf-card p-4"><p class="sf-page-eyebrow">Liquido</p><p class="mt-2 text-2xl font-black brand-text">R$ {{ number_format($totalNet, 2, ',', '.') }}</p></div>
        </div>

        <div class="sf-card overflow-x-auto p-0">
            <table class="min-w-full text-sm">
                <thead class="border-b border-white/10 text-left sf-muted">
                    <tr>
                        <th class="p-3">Data</th>
                        <th class="p-3">Produto</th>
                        <th class="p-3">Qtd</th>
                        <th class="p-3">Vendedor</th>
                        <th class="p-3">Pagamento</th>
                        <th class="p-3 text-right">Bruto</th>
                        <th class="p-3 text-right">Desc.</th>
                        <th class="p-3 text-right">Liquido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($item = $row['item'])
                        <tr class="border-b border-white/5">
                            <td class="p-3">{{ $item->sale?->sold_at?->format('d/m/Y H:i') }}</td>
                            <td class="p-3">{{ $item->product?->name }}</td>
                            <td class="p-3">{{ $item->quantity }}</td>
                            <td class="p-3">{{ $item->seller?->name ?? '-' }}</td>
                            <td class="p-3">{{ $item->sale?->payment_method }}</td>
                            <td class="p-3 text-right">R$ {{ number_format($row['gross'], 2, ',', '.') }}</td>
                            <td class="p-3 text-right">R$ {{ number_format($row['discount'], 2, ',', '.') }}</td>
                            <td class="p-3 text-right">R$ {{ number_format($row['net'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="sf-card p-5">
            <h3 class="sf-section-title">Ranking de produtos</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                @foreach ($ranking->take(6) as $rank)
                    <div class="rounded-xl border border-white/10 bg-[var(--input-bg)] p-4">
                        <p class="font-semibold">{{ $rank['product']?->name }}</p>
                        <p class="mt-1 text-sm sf-muted">{{ $rank['quantity'] }} vendidos · R$ {{ number_format($rank['net'], 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
