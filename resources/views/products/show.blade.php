<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Produtos</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">{{ $product->name }}</h2>
                <p class="mt-2 text-sm sf-text-muted">Estoque, custos e histórico de movimentações.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('products.index') }}" class="sf-button-ghost">Voltar</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('products.edit', $product) }}" class="sf-button-secondary">Editar cadastro</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'stock-adjusted')
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                Estoque ajustado e movimentação registrada.
            </div>
        @endif

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Preço</p>
                <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $product->price, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Estoque atual</p>
                <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">{{ $product->stock_quantity }} @if ($product->unit) <span class="text-base sf-text-muted">{{ $product->unit }}</span> @endif</p>
                @if ($product->minimum_stock !== null)
                    <p class="mt-2 text-sm sf-text-muted">Mínimo: {{ $product->minimum_stock }}</p>
                @endif
                @if ($product->isLowStock())
                    <span class="mt-3 inline-flex rounded-full border border-amber-400/30 bg-amber-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-100">Estoque baixo</span>
                @endif
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Controle</p>
                <p class="mt-3 text-sm sf-text-muted">
                    {{ $product->tracksStock() ? 'Este produto bloqueia vendas quando não houver saldo.' : 'Este produto não bloqueia operação por estoque.' }}
                </p>
                @if ($product->cost_price !== null)
                    <p class="mt-2 text-sm text-[var(--text-main)]">Custo: R$ {{ number_format((float) $product->cost_price, 2, ',', '.') }}</p>
                @endif
            </article>
        </section>

        @if (auth()->user()->isAdmin())
            <section class="sf-card p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-[var(--text-main)]">Ajustar estoque</h3>
                <p class="mt-1 text-sm sf-text-muted">Informe a nova quantidade absoluta. O sistema registra uma movimentação do tipo ajuste.</p>
                <form method="POST" action="{{ route('products.stock-adjust', $product) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label for="adj_stock_quantity" value="Nova quantidade" />
                        <x-text-input id="adj_stock_quantity" name="stock_quantity" type="number" min="0" step="0.01" class="mt-2 block w-full" required />
                        <x-input-error class="mt-2" :messages="$errors->get('stock_quantity')" />
                    </div>
                    <div>
                        <x-input-label for="adj_reason" value="Motivo (opcional)" />
                        <x-text-input id="adj_reason" name="reason" type="text" class="mt-2 block w-full" maxlength="500" />
                        <x-input-error class="mt-2" :messages="$errors->get('reason')" />
                    </div>
                    <div class="md:col-span-2">
                        <x-primary-button>Salvar ajuste</x-primary-button>
                    </div>
                </form>
            </section>
        @endif

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-5 py-4">
                <h3 class="text-lg font-semibold text-[var(--text-main)]">Histórico de movimentações</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                    <thead class="bg-[var(--input-bg)] text-xs uppercase tracking-wide sf-text-muted">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 text-right">Qtd</th>
                            <th class="px-4 py-3 text-right">Antes</th>
                            <th class="px-4 py-3 text-right">Depois</th>
                            <th class="px-4 py-3">Usuário</th>
                            <th class="px-4 py-3">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 sf-text-muted">
                        @forelse ($movements as $movement)
                            <tr class="hover:bg-white/5">
                                <td class="px-4 py-3 text-[var(--text-main)]">{{ $movement->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ match ($movement->type) {
                                    'in' => 'Entrada',
                                    'out' => 'Saída',
                                    'adjustment' => 'Ajuste',
                                    'sale' => 'Venda',
                                    'service_consumption' => 'Consumo (serviço)',
                                    default => $movement->type,
                                } }}</td>
                                <td class="px-4 py-3 text-right text-[var(--text-main)]">{{ $movement->quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ $movement->previous_quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ $movement->new_quantity }}</td>
                                <td class="px-4 py-3">{{ $movement->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $movement->reason }}">{{ $movement->reason ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center sf-text-muted">Nenhuma movimentação registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-white/10 px-5 py-4">
                {{ $movements->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
