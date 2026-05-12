<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Relatórios</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Relatório financeiro</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Cruze serviços, produtos, repasses e caixa por período.</p>
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
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Serviços</p>
            <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($serviceRevenue, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Produtos</p>
            <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($productRevenue, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Receita total</p>
            <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($totalRevenue, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Acertos</p>
            <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($settlementsAmount, 2, ',', '.') }}</p>
        </article>
        <article class="sf-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Saldo caixa</p>
            <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($cashInflows - $cashOutflows, 2, ',', '.') }}</p>
        </article>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-white">Recebimentos de serviços</h3>
            </div>
            <div class="space-y-3 px-5 py-5">
                @forelse ($payments as $payment)
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $payment->client->name }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">{{ $payment->service->name }} · {{ $payment->user->name }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-[#d4af37]">R$ {{ number_format((float) $payment->gross_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs text-[#c7d2e3]">{{ $payment->paid_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-[#c7d2e3]">Nenhum serviço pago no período.</div>
                @endforelse
            </div>
        </section>

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-white">Vendas de produtos</h3>
            </div>
            <div class="space-y-3 px-5 py-5">
                @forelse ($productSales as $sale)
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $sale->client->name }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">{{ $sale->items->map(fn ($item) => $item->product->name . ' x' . $item->quantity)->join(', ') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-[#d4af37]">R$ {{ number_format((float) $sale->gross_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs text-[#c7d2e3]">{{ $sale->sold_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-[#c7d2e3]">Nenhuma venda de produto no período.</div>
                @endforelse
            </div>
        </section>

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-white">Acertos com barbeiros</h3>
            </div>
            <div class="space-y-3 px-5 py-5">
                @forelse ($settlements as $settlement)
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $settlement->user->name }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">{{ $settlement->start_date->format('d/m') }} até {{ $settlement->end_date->format('d/m') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-rose-200">R$ {{ number_format((float) $settlement->commission_amount, 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs text-[#c7d2e3]">{{ $settlement->paid_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-[#c7d2e3]">Nenhum acerto no período.</div>
                @endforelse
            </div>
        </section>

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h3 class="text-base font-semibold text-white">Resumo de caixas</h3>
            </div>
            <div class="space-y-3 px-5 py-5">
                @forelse ($cashRegisters as $register)
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $register->date->format('d/m/Y') }}</p>
                                <p class="mt-1 text-sm text-[#c7d2e3]">{{ $register->closed_at ? 'Fechado' : 'Aberto' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-[#d4af37]">R$ {{ number_format($register->expectedBalance(), 2, ',', '.') }}</p>
                                <p class="mt-1 text-xs text-[#c7d2e3]">{{ $register->movements->count() }} movimentacoes</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-[#c7d2e3]">Nenhum caixa encontrado no período.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
