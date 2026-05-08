<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Clientes</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">{{ $client->name }}</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">
                    {{ $client->client_code ?? '-' }} · {{ $client->phone }} · cliente desde {{ $client->created_at->format('d/m/Y') }}
                    @unless ($client->active)
                        <span class="ml-2 inline-flex rounded-full border border-rose-400/30 bg-rose-500/10 px-2 py-0.5 text-xs font-semibold text-rose-100">Inativo</span>
                    @endunless
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('appointments.create', ['client_id' => $client->id]) }}" class="sf-button-primary">Novo agendamento</a>
                <a href="{{ route('product-sales.create', ['client_id' => $client->id]) }}" class="sf-button-secondary">Nova venda</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('clients.edit', $client) }}" class="sf-button-secondary">Editar cliente</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                {{ match (session('status')) {
                    'client-updated' => 'Cliente atualizado com sucesso.',
                    'product-sale-created' => 'Venda de produto registrada com sucesso.',
                    'client-deactivated' => 'Cliente desativado.',
                    'client-reactivated' => 'Cliente reativado.',
                    'client-delete-blocked' => 'Exclusão bloqueada: há histórico operacional. Use desativar.',
                    default => session('status'),
                } }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Total gasto</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($totalSpent, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm text-[#c7d2e3]">Serviços: R$ {{ number_format($serviceSpent, 2, ',', '.') }} · Produtos: R$ {{ number_format($productSpent, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Total visitas</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $totalVisits }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Última visita</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ $lastVisitAt?->format('d/m/Y H:i') ?? '-' }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Ticket medio</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <div class="space-y-6">
                <article class="sf-card overflow-hidden">
                    <div class="border-b border-white/10 px-5 py-4">
                        <h3 class="text-lg font-semibold text-white">Histórico de atendimentos</h3>
                        <p class="mt-1 text-sm text-[#c7d2e3]">{{ $appointmentsThisMonth }} agendamento(s) neste mês.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[#132746]">
                                <tr>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Data</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Serviço</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Profissional</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Valor</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse ($appointments as $appointment)
                                    @php
                                        $serviceLabel = $appointment->bookedServices()->pluck('name')->join(', ');
                                        $grossAmount = $appointment->payment?->gross_amount ?? $appointment->totalPriceAmount();
                                    @endphp
                                    <tr class="transition hover:bg-white/5">
                                        <td class="px-5 py-4 text-sm text-white">{{ $appointment->start_time->format('d/m/Y H:i') }}</td>
                                        <td class="px-5 py-4 text-sm text-[#c7d2e3]">{{ $serviceLabel !== '' ? $serviceLabel : ($appointment->service?->name ?? '-') }}</td>
                                        <td class="px-5 py-4 text-sm text-[#c7d2e3]">{{ $appointment->user?->name ?? '-' }}</td>
                                        <td class="px-5 py-4 text-sm font-semibold text-white">R$ {{ number_format((float) $grossAmount, 2, ',', '.') }}</td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                                {{ $appointment->statusLabel() }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-10 text-center text-sm text-[#c7d2e3]">
                                            Nenhum atendimento encontrado para este cliente.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="sf-card overflow-hidden">
                    <div class="border-b border-white/10 px-5 py-4">
                        <h3 class="text-lg font-semibold text-white">Histórico de compras</h3>
                        <p class="mt-1 text-sm text-[#c7d2e3]">{{ $productSalesThisMonth }} venda(s) de produto neste mês.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[#132746]">
                                <tr>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Data</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Produtos</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Profissional</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Pagamento</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse ($productSales as $sale)
                                    <tr class="transition hover:bg-white/5">
                                        <td class="px-5 py-4 text-sm text-white">{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                                        <td class="px-5 py-4 text-sm text-[#c7d2e3]">
                                            <div class="space-y-2">
                                                @foreach ($sale->items as $item)
                                                    <div class="flex items-center gap-3">
                                                        @if ($item->product->image_url)
                                                            <img src="{{ $item->product->image_url }}" alt="Imagem de {{ $item->product->name }}" class="h-10 w-10 rounded-xl object-cover ring-1 ring-white/10">
                                                        @else
                                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-dashed border-white/10 bg-[#132746] text-[#d4af37]">
                                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                                    <path d="M7.5 6A2.5 2.5 0 005 8.5v7A2.5 2.5 0 007.5 18h9a2.5 2.5 0 002.5-2.5v-7A2.5 2.5 0 0016.5 6h-9zm0 1.5h9A1 1 0 0117.5 8.5v4.085l-2.23-2.23a1.75 1.75 0 00-2.475 0l-2.92 2.92-1.17-1.17a1.75 1.75 0 00-2.475 0L6.5 13.835V8.5a1 1 0 011-1zm8.75 2.25a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zM7.29 13.166a.25.25 0 01.354 0l1.7 1.7a.75.75 0 001.06 0l3.451-3.45a.25.25 0 01.354 0l2.291 2.29v1.794a1 1 0 01-1 1h-9a1 1 0 01-1-1v-.544l1.79-1.79z" />
                                                                </svg>
                                                            </div>
                                                        @endif
                                                        <span>{{ $item->product->name }} x{{ $item->quantity }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-sm text-[#c7d2e3]">{{ $sale->user?->name ?? '-' }}</td>
                                        <td class="px-5 py-4 text-sm text-[#c7d2e3]">{{ ucfirst($sale->payment_method) }}</td>
                                        <td class="px-5 py-4 text-sm font-semibold text-white">R$ {{ number_format((float) $sale->gross_amount, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-10 text-center text-sm text-[#c7d2e3]">
                                            Nenhuma compra de produto registrada para este cliente.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>

            <div class="space-y-6">
                <article class="sf-card p-5">
                    <h3 class="text-lg font-semibold text-white">Observações internas</h3>
                    <p class="mt-4 whitespace-pre-line text-sm leading-7 text-[#c7d2e3]">{{ $client->notes ?: 'Nenhuma observação registrada ainda.' }}</p>
                </article>

                <article class="sf-card p-5">
                    <h3 class="text-lg font-semibold text-white">Dados do cliente</h3>
                    <dl class="mt-4 space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Código</dt>
                            <dd class="text-sm font-semibold text-white">{{ $client->client_code ?? '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Telefone</dt>
                            <dd class="text-sm font-semibold text-white">{{ $client->phone }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">CPF</dt>
                            <dd class="text-sm font-semibold text-white">{{ $client->cpf ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $client->cpf) : '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Aniversário</dt>
                            <dd class="text-sm font-semibold text-white">{{ $client->birthday?->format('d/m/Y') ?? '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Última visita</dt>
                            <dd class="text-sm font-semibold text-white">{{ $lastVisitAt?->format('d/m/Y H:i') ?? '-' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('clients.index') }}" class="sf-button-ghost">Voltar para clientes</a>
                        @if (auth()->user()->isAdmin())
                            @if ($client->active)
                                <form method="POST" action="{{ route('clients.deactivate', $client) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="sf-button-secondary" onclick="return confirm('Desativar este cliente?')">
                                        Desativar
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('clients.reactivate', $client) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="sf-button-secondary">
                                        Reativar
                                    </button>
                                </form>
                            @endif
                            @if (! $client->hasOperationalHistory())
                                <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button onclick="return confirm('Excluir permanentemente este cliente?')">
                                        Excluir
                                    </x-danger-button>
                                </form>
                            @endif
                        @endif
                    </div>
                </article>
            </div>
        </section>
    </div>
</x-app-layout>
