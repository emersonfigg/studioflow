@extends('layouts.pdv')

@section('title', 'Histórico PDV')

@section('content')
    <div class="space-y-4">
        <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">PDV</p>
                    <h1 class="mt-1 text-2xl font-semibold text-[var(--text-main)]">Histórico de Vendas</h1>
                </div>
                <a href="{{ route('pdv.index') }}" class="sf-button-secondary w-full justify-center sm:w-auto">Nova venda</a>
            </div>

            <form method="GET" action="{{ route('pdv.sales') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="sf-input">
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="sf-input">
                <select name="client_id" class="sf-select">
                    <option value="">Cliente (todos)</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($selectedClientId === $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
                <select name="professional_id" class="sf-select">
                    <option value="">Profissional (todos)</option>
                    @foreach ($professionals as $professional)
                        <option value="{{ $professional->id }}" @selected($selectedProfessionalId === $professional->id)>{{ $professional->name }}</option>
                    @endforeach
                </select>
                <select name="payment_method" class="sf-select">
                    <option value="">Pagamento (todos)</option>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected($selectedPaymentMethod === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="sf-button-primary sm:col-span-2 lg:col-span-5">Filtrar</button>
            </form>
        </section>

        @if ($orders->isEmpty())
            <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-8 text-center text-sm sf-text-muted">
                Nenhuma venda encontrada no período/filtro selecionado.
            </section>
        @else
            <section class="space-y-3 lg:hidden">
                @foreach ($orders as $order)
                    @php
                        $method = $order->payment?->payment_method ?? $order->productSale?->payment_method;
                    @endphp
                    <article class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">Comanda #{{ $order->id }}</p>
                                <p class="mt-1 text-xs sf-text-muted">{{ $order->client?->name ?? 'Cliente' }} · {{ $order->closed_at?->format('d/m/Y H:i') }}</p>
                            </div>
                            <span class="text-sm font-semibold brand-text">R$ {{ number_format((float) $order->total, 2, ',', '.') }}</span>
                        </div>
                        <p class="mt-2 text-xs sf-text-muted">Pagamento: {{ $method ? \App\Models\Payment::labelForPaymentMethod($method) : '-' }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <a href="{{ route('pdv.sales.show', $order) }}" class="sf-button-secondary w-full justify-center !py-2">Detalhes</a>
                            <a href="{{ route('pdv.receipt', $order) }}" target="_blank" rel="noopener noreferrer" class="sf-button-secondary w-full justify-center !py-2">Reimprimir</a>
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="hidden overflow-hidden rounded-2xl border border-white/10 bg-[var(--input-bg)] lg:block">
                <table class="min-w-full divide-y divide-white/10">
                    <thead class="bg-[var(--app-shell-bg)]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Comanda</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Profissional</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Pagamento</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Total</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @foreach ($orders as $order)
                            @php
                                $method = $order->payment?->payment_method ?? $order->productSale?->payment_method;
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-sm text-[var(--text-main)]">#{{ $order->id }}<br><span class="text-xs sf-text-muted">{{ $order->closed_at?->format('d/m/Y H:i') }}</span></td>
                                <td class="px-4 py-3 text-sm sf-text-muted">{{ $order->client?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm sf-text-muted">{{ $order->professional?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm sf-text-muted">{{ $method ? \App\Models\Payment::labelForPaymentMethod($method) : '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $order->total, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <a href="{{ route('pdv.sales.show', $order) }}" class="sf-button-secondary !px-3 !py-2">Detalhes</a>
                                    <a href="{{ route('pdv.receipt', $order) }}" target="_blank" rel="noopener noreferrer" class="sf-button-secondary !px-3 !py-2">Recibo</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <div class="pt-2">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection

