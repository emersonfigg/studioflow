<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Relatórios</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Relatório financeiro</h2>
                <p class="mt-2 text-sm sf-text-muted">Cruze serviços, produtos, repasses e caixa por período.</p>
            </div>

            <form method="GET" action="{{ route('finance.report') }}" class="flex flex-wrap items-center gap-3">
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="sf-input min-w-[170px]">
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="sf-input min-w-[170px]">
                @if ($canFilterProfessionals)
                    <select name="user_id" class="sf-select min-w-[220px]">
                        <option value="">Todos os profissionais</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($selectedUserId === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="sf-button-ghost">Filtrar</button>
            </form>
        </div>
    </x-slot>

    @include('finance.partials.nav', ['page' => $page])

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Serviços</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($serviceRevenue, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Produtos</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($productRevenue, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Receita total</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($totalRevenue, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Acertos</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($settlementsAmount, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Saldo caixa</p>
            <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($cashInflows - $cashOutflows, 2, ',', '.') }}</p>
        </article>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Recebimentos de serviços</h3>
            </div>
            <div class="space-y-3 px-5 py-5">
                @forelse ($payments as $payment)
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">{{ $payment->client->name }}</p>
                                <p class="mt-1 text-sm sf-text-muted">{{ $payment->service->name }} · {{ $payment->user->name }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold brand-text">R$ {{ number_format((float) $payment->gross_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs sf-text-muted">{{ $payment->paid_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm sf-text-muted">Nenhum serviço pago no período.</div>
                @endforelse
            </div>
        </section>

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Vendas de produtos</h3>
            </div>
            <div class="space-y-3 px-5 py-5">
                @forelse ($productSales as $sale)
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">{{ $sale->client->name }}</p>
                                <p class="mt-1 text-sm sf-text-muted">{{ $sale->items->map(fn ($item) => $item->product->name . ' x' . $item->quantity)->join(', ') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold brand-text">R$ {{ number_format((float) $sale->gross_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs sf-text-muted">{{ $sale->sold_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm sf-text-muted">Nenhuma venda de produto no período.</div>
                @endforelse
            </div>
        </section>

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Acertos com profissionais</h3>
            </div>
            <div class="space-y-3 px-5 py-5">
                @forelse ($settlements as $settlement)
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">{{ $settlement->user->name }}</p>
                                <p class="mt-1 text-sm sf-text-muted">{{ $settlement->start_date->format('d/m') }} até {{ $settlement->end_date->format('d/m') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-rose-200">R$ {{ number_format((float) $settlement->commission_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs sf-text-muted">{{ $settlement->paid_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm sf-text-muted">Nenhum acerto no período.</div>
                @endforelse
            </div>
        </section>

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Resumo de caixas</h3>
            </div>
            <div class="space-y-3 px-5 py-5">
                @forelse ($cashRegisters as $register)
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">{{ $register->date->format('d/m/Y') }}</p>
                                <p class="mt-1 text-sm sf-text-muted">{{ $register->closed_at ? 'Fechado' : 'Aberto' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold brand-text">R$ {{ number_format($register->expectedBalance(), 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs sf-text-muted">{{ $register->movements->count() }} movimentacoes</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm sf-text-muted">Nenhum caixa encontrado no período.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
