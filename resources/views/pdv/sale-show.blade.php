@extends('layouts.pdv')

@section('title', 'Detalhe venda PDV')

@section('content')
    @php($isCancelled = $order->status === \App\Models\ServiceOrder::STATUS_CANCELLED)

    <div class="space-y-4">
        @if (session('status') === 'payment-method-updated')
            <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                Forma de pagamento corrigida com sucesso.
            </div>
        @endif

        @if (session('status') === 'sale-cancelled')
            <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                Venda cancelada e estornada com sucesso.
            </div>
        @endif

        <section class="rounded-2xl border {{ $isCancelled ? 'border-rose-400/30 bg-rose-950/20' : 'border-white/10 bg-[var(--input-bg)]' }} p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] brand-text">Comanda #{{ $order->id }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold text-[var(--text-main)]">{{ $isCancelled ? 'Detalhe da venda cancelada' : 'Detalhe da venda finalizada' }}</h1>
                        @if ($isCancelled)
                            <span class="inline-flex rounded-full border border-rose-300/30 bg-rose-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-rose-100">Cancelada</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm sf-text-muted">{{ $order->client?->name ?? 'Cliente' }} - {{ $order->closed_at?->format('d/m/Y H:i') }}</p>
                    @if ($isCancelled)
                        <p class="mt-2 text-xs text-rose-100">Cancelada por {{ $order->cancelledBy?->name ?? 'usuario' }} em {{ $order->cancelled_at?->format('d/m/Y H:i') }}. Motivo: {{ $order->cancel_reason }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('pdv.sales') }}" class="sf-button-secondary">Voltar historico</a>
                    <a href="{{ route('pdv.receipt', $order) }}" target="_blank" rel="noopener noreferrer" class="sf-button-primary">{{ $isCancelled ? 'Recibo cancelado' : 'Reimprimir recibo' }}</a>
                    @if (! $isCancelled && $canCancelSales)
                        <button type="button" class="sf-button-secondary text-rose-100" onclick="document.getElementById('cancel-sale-dialog').showModal()">Cancelar</button>
                    @endif
                    @if ($canForceDeleteSales)
                        <button type="button" class="sf-button-secondary text-rose-100" onclick="document.getElementById('delete-sale-dialog').showModal()">Excluir</button>
                    @endif
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
                @php($method = $order->payment_method ?? $order->payment?->payment_method ?? $order->productSale?->payment_method)
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
                        @php($itemTypeLabel = match ($item->type) {
                            \App\Models\ServiceOrderItem::TYPE_SERVICE => 'Servico',
                            \App\Models\ServiceOrderItem::TYPE_MEMBERSHIP => 'Assinatura',
                            default => 'Produto',
                        })
                        <p class="mt-1 text-xs sf-text-muted">{{ $itemTypeLabel }} - Qtd {{ $item->quantity }} - Unit R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($canCorrectPaymentMethod && ! $isCancelled)
            <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4 sm:p-5">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] brand-text">Corrigir forma de pagamento</h2>
                <p class="mt-1 text-sm sf-text-muted">Essa acao altera apenas metodo de pagamento em registros vinculados, sem mudar valores, itens ou comissao.</p>
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
                        <label for="reason" class="text-xs font-semibold text-[var(--text-main)]">Motivo da correcao</label>
                        <textarea id="reason" name="reason" rows="3" class="sf-input mt-1 block w-full" required>{{ old('reason') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('reason')" />
                    </div>
                    <button type="submit" class="sf-button-primary w-full sm:w-auto">Salvar correcao</button>
                </form>
            </section>
        @endif

        @if (! $isCancelled && $canCancelSales)
            <dialog id="cancel-sale-dialog" class="w-full max-w-lg rounded-2xl border border-white/10 bg-[var(--input-bg)] p-0 text-[var(--text-main)] backdrop:bg-black/70">
                <form method="POST" action="{{ route('pdv.sales.cancel', $order) }}" class="space-y-4 p-5">
                    @csrf
                    @method('PATCH')
                    <div>
                        <h2 class="text-lg font-semibold">Cancelar venda</h2>
                        <p class="mt-1 text-sm sf-text-muted">A venda sera marcada como cancelada, os itens ficam preservados e o valor sera estornado dos totais operacionais.</p>
                    </div>
                    <div>
                        <label for="cancel_reason" class="text-xs font-semibold text-[var(--text-main)]">Motivo obrigatorio</label>
                        <textarea id="cancel_reason" name="cancel_reason" rows="4" class="sf-input mt-1 block w-full" required minlength="5">{{ old('cancel_reason') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('cancel_reason')" />
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="sf-button-secondary" onclick="this.closest('dialog').close()">Voltar</button>
                        <button type="submit" class="sf-button-primary bg-rose-600 hover:bg-rose-500">Confirmar cancelamento</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($canForceDeleteSales)
            <dialog id="delete-sale-dialog" class="w-full max-w-lg rounded-2xl border border-white/10 bg-[var(--input-bg)] p-0 text-[var(--text-main)] backdrop:bg-black/70">
                <form method="POST" action="{{ route('pdv.sales.force-delete', $order) }}" class="space-y-4 p-5">
                    @csrf
                    @method('DELETE')
                    <div>
                        <h2 class="text-lg font-semibold">Excluir venda</h2>
                        <p class="mt-1 text-sm sf-text-muted">Acao restrita a Super Admin. Um log sera registrado antes da exclusao.</p>
                    </div>
                    <div>
                        <label for="confirmation" class="text-xs font-semibold text-[var(--text-main)]">Digite EXCLUIR</label>
                        <input id="confirmation" name="confirmation" class="sf-input mt-1 block w-full" required pattern="EXCLUIR">
                        <x-input-error class="mt-1" :messages="$errors->get('confirmation')" />
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="sf-button-secondary" onclick="this.closest('dialog').close()">Voltar</button>
                        <button type="submit" class="sf-button-primary bg-rose-600 hover:bg-rose-500">Excluir</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
@endsection
