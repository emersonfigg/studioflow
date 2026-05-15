@extends('layouts.pdv')

@section('title', 'Detalhe venda PDV')

@section('content')
    <div class="space-y-4">
        @if (session('status') === 'payment-method-updated')
            <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                Forma de pagamento corrigida com sucesso.
            </div>
        @endif

        <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] brand-text">Comanda #{{ $order->id }}</p>
                    <h1 class="mt-1 text-xl font-semibold text-[var(--text-main)]">Detalhe da venda finalizada</h1>
                    <p class="mt-1 text-sm sf-text-muted">{{ $order->client?->name ?? 'Cliente' }} · {{ $order->closed_at?->format('d/m/Y H:i') }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('pdv.sales') }}" class="sf-button-secondary">Voltar histórico</a>
                    <a href="{{ route('pdv.receipt', $order) }}" target="_blank" rel="noopener noreferrer" class="sf-button-primary">Reimprimir recibo</a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                <p class="text-xs uppercase tracking-[0.16em] sf-text-muted">Total</p>
                <p class="mt-1 text-2xl font-semibold brand-text">R$ {{ number_format((float) $order->total, 2, ',', '.') }}</p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                <p class="text-xs uppercase tracking-[0.16em] sf-text-muted">Pagamento atual</p>
                @php($method = $order->payment?->payment_method ?? $order->productSale?->payment_method)
                <p class="mt-1 text-lg font-semibold text-[var(--text-main)]">{{ $method ? \App\Models\Payment::labelForPaymentMethod($method) : '-' }}</p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
                <p class="text-xs uppercase tracking-[0.16em] sf-text-muted">Profissional</p>
                <p class="mt-1 text-lg font-semibold text-[var(--text-main)]">{{ $order->professional?->name ?? '-' }}</p>
            </article>
        </section>

        <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4 sm:p-5">
            <h2 class="text-sm font-semibold uppercase tracking-[0.16em] brand-text">Itens</h2>
            <div class="mt-3 space-y-2">
                @foreach ($order->items as $item)
                    <div class="rounded-xl border border-white/10 bg-[var(--app-shell-bg)] px-3 py-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-semibold text-[var(--text-main)]">{{ $item->description }}</p>
                            <p class="brand-text">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</p>
                        </div>
                        <p class="mt-1 text-xs sf-text-muted">{{ $item->type === 'service' ? 'Serviço' : 'Produto' }} · Qtd {{ $item->quantity }} · Unit R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($canCorrectPaymentMethod)
            <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4 sm:p-5">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] brand-text">Corrigir forma de pagamento</h2>
                <p class="mt-1 text-sm sf-text-muted">Essa ação altera apenas método de pagamento em registros vinculados, sem mudar valores, itens ou comissão.</p>
                <form method="POST" action="{{ route('pdv.sales.payment-method.update', $order) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="payment_method" class="text-xs font-semibold text-[var(--text-main)]">Nova forma</label>
                        <select id="payment_method" name="payment_method" class="sf-select mt-1 block w-full" required>
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', $method) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('payment_method')" />
                    </div>
                    <div>
                        <label for="reason" class="text-xs font-semibold text-[var(--text-main)]">Motivo da correção</label>
                        <textarea id="reason" name="reason" rows="3" class="sf-input mt-1 block w-full" required>{{ old('reason') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('reason')" />
                    </div>
                    <button type="submit" class="sf-button-primary w-full sm:w-auto">Salvar correção</button>
                </form>
            </section>
        @endif
    </div>
@endsection

