@extends('layouts.pdv')

@section('title', 'Historico PDV')

@section('content')
    <div class="space-y-4">
        <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">PDV</p>
                    <h1 class="mt-1 text-2xl font-semibold text-[var(--text-main)]">Historico de Vendas</h1>
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

        @if (session('status') === 'sale-force-deleted')
            <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                Venda excluida com registro de auditoria.
            </div>
        @endif

        @if ($orders->isEmpty())
            <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-8 text-center text-sm sf-text-muted">
                Nenhuma venda encontrada no periodo/filtro selecionado.
            </section>
        @else
            <section class="space-y-3 lg:hidden">
                @foreach ($orders as $order)
                    @php
                        $method = $order->payment?->payment_method ?? $order->productSale?->payment_method;
                        $isCancelled = $order->status === \App\Models\ServiceOrder::STATUS_CANCELLED;
                    @endphp
                    <article class="rounded-2xl border {{ $isCancelled ? 'border-rose-400/30 bg-rose-950/20' : 'border-white/10 bg-[var(--input-bg)]' }} p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">Comanda #{{ $order->id }}</p>
                                <p class="mt-1 text-xs sf-text-muted">{{ $order->client?->name ?? 'Cliente' }} - {{ $order->closed_at?->format('d/m/Y H:i') }}</p>
                                @if ($isCancelled)
                                    <span class="mt-2 inline-flex rounded-full border border-rose-300/30 bg-rose-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-rose-100">Cancelada</span>
                                @endif
                            </div>
                            <span class="text-sm font-semibold brand-text">R$ {{ number_format((float) $order->total, 2, ',', '.') }}</span>
                        </div>
                        <p class="mt-2 text-xs sf-text-muted">Pagamento: {{ $method ? \App\Models\Payment::labelForPaymentMethod($method) : '-' }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <a href="{{ route('pdv.sales.show', $order) }}" class="sf-button-secondary w-full justify-center !py-2">Detalhes</a>
                            <a href="{{ route('pdv.receipt', $order) }}" target="_blank" rel="noopener noreferrer" class="sf-button-secondary w-full justify-center !py-2">{{ $isCancelled ? 'Recibo cancelado' : 'Reimprimir' }}</a>
                            @if (! $isCancelled && $canCancelSales)
                                <button type="button" class="sf-button-secondary w-full justify-center !py-2 text-rose-100" data-cancel-action="{{ route('pdv.sales.cancel', $order) }}">Cancelar</button>
                            @endif
                            @if ($canForceDeleteSales)
                                <button type="button" class="sf-button-secondary w-full justify-center !py-2 text-rose-100" data-delete-action="{{ route('pdv.sales.force-delete', $order) }}">Excluir</button>
                            @endif
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
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] sf-text-muted">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @foreach ($orders as $order)
                            @php
                                $method = $order->payment?->payment_method ?? $order->productSale?->payment_method;
                                $isCancelled = $order->status === \App\Models\ServiceOrder::STATUS_CANCELLED;
                            @endphp
                            <tr class="{{ $isCancelled ? 'bg-rose-950/20' : '' }}">
                                <td class="px-4 py-3 text-sm text-[var(--text-main)]">
                                    #{{ $order->id }}
                                    @if ($isCancelled)
                                        <span class="ml-2 inline-flex rounded-full border border-rose-300/30 bg-rose-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-rose-100">Cancelada</span>
                                    @endif
                                    <br><span class="text-xs sf-text-muted">{{ $order->closed_at?->format('d/m/Y H:i') }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm sf-text-muted">{{ $order->client?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm sf-text-muted">{{ $order->professional?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm sf-text-muted">{{ $method ? \App\Models\Payment::labelForPaymentMethod($method) : '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $order->total, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <a href="{{ route('pdv.sales.show', $order) }}" class="sf-button-secondary !px-3 !py-2">Detalhes</a>
                                    <a href="{{ route('pdv.receipt', $order) }}" target="_blank" rel="noopener noreferrer" class="sf-button-secondary !px-3 !py-2">{{ $isCancelled ? 'Recibo cancelado' : 'Recibo' }}</a>
                                    @if (! $isCancelled && $canCancelSales)
                                        <button type="button" class="sf-button-secondary !px-3 !py-2 text-rose-100" data-cancel-action="{{ route('pdv.sales.cancel', $order) }}">Cancelar</button>
                                    @endif
                                    @if ($canForceDeleteSales)
                                        <button type="button" class="sf-button-secondary !px-3 !py-2 text-rose-100" data-delete-action="{{ route('pdv.sales.force-delete', $order) }}">Excluir</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <div class="pt-2">{{ $orders->links() }}</div>

            <dialog id="cancel-sale-dialog" class="w-full max-w-lg rounded-2xl border border-white/10 bg-[var(--input-bg)] p-0 text-[var(--text-main)] backdrop:bg-black/70">
                <form method="POST" id="cancel-sale-form" class="space-y-4 p-5">
                    @csrf
                    @method('PATCH')
                    <div>
                        <h2 class="text-lg font-semibold">Cancelar venda</h2>
                        <p class="mt-1 text-sm sf-text-muted">A venda sera marcada como cancelada, os itens ficam preservados e o valor sera estornado dos totais operacionais.</p>
                    </div>
                    <div>
                        <label for="cancel_reason" class="text-xs font-semibold text-[var(--text-main)]">Motivo obrigatorio</label>
                        <textarea id="cancel_reason" name="cancel_reason" rows="4" class="sf-input mt-1 block w-full" required minlength="5"></textarea>
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="sf-button-secondary" data-dialog-close>Voltar</button>
                        <button type="submit" class="sf-button-primary bg-rose-600 hover:bg-rose-500">Confirmar cancelamento</button>
                    </div>
                </form>
            </dialog>

            <dialog id="delete-sale-dialog" class="w-full max-w-lg rounded-2xl border border-white/10 bg-[var(--input-bg)] p-0 text-[var(--text-main)] backdrop:bg-black/70">
                <form method="POST" id="delete-sale-form" class="space-y-4 p-5">
                    @csrf
                    @method('DELETE')
                    <div>
                        <h2 class="text-lg font-semibold">Excluir venda</h2>
                        <p class="mt-1 text-sm sf-text-muted">Acao restrita a Super Admin. Um log sera registrado antes da exclusao.</p>
                    </div>
                    <div>
                        <label for="confirmation" class="text-xs font-semibold text-[var(--text-main)]">Digite EXCLUIR</label>
                        <input id="confirmation" name="confirmation" class="sf-input mt-1 block w-full" required pattern="EXCLUIR">
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="sf-button-secondary" data-dialog-close>Voltar</button>
                        <button type="submit" class="sf-button-primary bg-rose-600 hover:bg-rose-500">Excluir</button>
                    </div>
                </form>
            </dialog>

            <script>
                document.querySelectorAll('[data-cancel-action]').forEach((button) => {
                    button.addEventListener('click', () => {
                        document.getElementById('cancel-sale-form').action = button.dataset.cancelAction;
                        document.getElementById('cancel-sale-dialog').showModal();
                    });
                });
                document.querySelectorAll('[data-delete-action]').forEach((button) => {
                    button.addEventListener('click', () => {
                        document.getElementById('delete-sale-form').action = button.dataset.deleteAction;
                        document.getElementById('delete-sale-dialog').showModal();
                    });
                });
                document.querySelectorAll('[data-dialog-close]').forEach((button) => {
                    button.addEventListener('click', () => button.closest('dialog').close());
                });
            </script>
        @endif
    </div>
@endsection
