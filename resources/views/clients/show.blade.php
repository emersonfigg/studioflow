<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Clientes</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">{{ $client->name }}</h2>
                <p class="mt-2 text-sm sf-text-muted">
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
                    'membership-created' => 'Assinatura criada com sucesso.',
                    'client-unblocked' => 'Cliente desbloqueado.',
                    default => session('status'),
                } }}
            </div>
        @endif

        @if ($errors->has('gateway'))
            <div class="rounded-2xl border border-rose-400/25 bg-rose-500/10 px-5 py-4 text-sm text-rose-100">
                {{ $errors->first('gateway') }}
            </div>
        @endif

        @if ($errors->has('membership'))
            <div class="rounded-2xl border border-rose-400/25 bg-rose-500/10 px-5 py-4 text-sm text-rose-100">
                {{ $errors->first('membership') }}
            </div>
        @endif

        @if (isset($pendingMembershipPayment) && $pendingMembershipPayment)
            <article class="sf-card border border-[color-mix(in_srgb,var(--brand-primary)_30%,transparent)] p-5" @if(session('membership_payment_id') == $pendingMembershipPayment->id) id="membership-payment-{{ $pendingMembershipPayment->id }}" @endif>
                <h3 class="text-lg font-semibold text-[var(--text-main)]">Pagamento da assinatura pendente</h3>
                <p class="mt-2 text-sm sf-text-muted">Plano: <span class="text-[var(--text-main)]">{{ $pendingMembershipPayment->membership?->plan?->name }}</span> · Valor: <span class="text-[var(--text-main)]">R$ {{ number_format((float) $pendingMembershipPayment->amount, 2, ',', '.') }}</span></p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($pendingMembershipPayment->invoice_url)
                        <a href="{{ $pendingMembershipPayment->invoice_url }}" target="_blank" rel="noopener" class="sf-button-secondary text-sm">Abrir link de pagamento</a>
                    @endif
                    @if ($pendingMembershipPayment->pix_copy_paste)
                        <button type="button" class="sf-button-primary text-sm" onclick="navigator.clipboard.writeText(@js($pendingMembershipPayment->pix_copy_paste)); this.textContent='Copiado!';">Copiar PIX</button>
                    @endif
                </div>
                @if ($pendingMembershipPayment->pix_copy_paste)
                    <p class="mt-3 break-all rounded-lg border border-white/10 bg-[var(--input-bg)] p-3 font-mono text-xs sf-text-muted">{{ $pendingMembershipPayment->pix_copy_paste }}</p>
                @endif
            </article>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Total gasto</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($totalSpent, 2, ',', '.') }}</p>
                <p class="mt-2 text-sm sf-text-muted">Serviços: R$ {{ number_format($serviceSpent, 2, ',', '.') }} · Produtos: R$ {{ number_format($productSpent, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Total visitas</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">{{ $totalVisits }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Última visita</p>
                <p class="mt-3 text-2xl font-semibold text-[var(--text-main)]">{{ $lastVisitAt?->format('d/m/Y H:i') ?? '-' }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Ticket médio</p>
                <p class="mt-3 text-3xl font-semibold text-[var(--text-main)]">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
            </article>
        </section>

        @if (isset($activeBlock) && $activeBlock)
            <article class="sf-card border-rose-400/30 p-5">
                <h3 class="text-lg font-semibold text-rose-100">Bloqueio ativo</h3>
                <p class="mt-2 text-sm text-rose-100/90">Tipo: {{ $activeBlock->type }} até {{ $activeBlock->ends_at?->format('d/m/Y H:i') ?? '—' }}</p>
                <p class="mt-1 text-sm text-rose-100/80">{{ $activeBlock->reason }}</p>
                @if (auth()->user()->hasFinancialPrivileges())
                    <form method="POST" action="{{ route('clients.unblock', $client) }}" class="mt-4" onsubmit="return confirm('Remover bloqueio ativo deste cliente?');">
                        @csrf
                        <button type="submit" class="sf-button-secondary text-xs">Desbloquear cliente</button>
                    </form>
                @endif
            </article>
        @endif

        @if (isset($membershipSummary) && ! empty($membershipSummary['active']))
            <article class="sf-card p-5">
                <h3 class="text-lg font-semibold text-[var(--text-main)]">Assinatura</h3>
                <p class="mt-2 text-sm sf-text-muted">Plano: <span class="text-[var(--text-main)]">{{ $membershipSummary['plan_name'] }}</span>
                    @if (! empty($membershipSummary['billing_cycle_label']))
                        <span class="sf-text-muted"> · </span><span class="text-[var(--text-main)]">{{ $membershipSummary['billing_cycle_label'] }}</span>
                    @endif
                </p>
                <p class="mt-1 text-sm sf-text-muted">Período vigente: <span class="text-[var(--text-main)]">{{ $membershipSummary['cycle_label'] }}</span></p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm sf-text-muted">
                    @foreach ($membershipSummary['service_rules'] as $rule)
                        <li>
                            {{ $rule['name'] }} —
                            @if (! empty($rule['included']))
                                incluso
                            @elseif (! empty($rule['discount_percent']))
                                desconto {{ $rule['discount_percent'] }}%
                            @endif
                            @if (isset($rule['remaining']) && $rule['remaining'] !== null)
                                · restam {{ $rule['remaining'] }} no ciclo
                            @endif
                        </li>
                    @endforeach
                </ul>
            </article>
        @endif

        @if (auth()->user()->isAdmin() && isset($membershipPlans) && $membershipPlans->isNotEmpty())
            @if (! ($hasMembershipPaymentGateway ?? false))
                <div class="sf-card border border-amber-400/30 bg-amber-500/10 p-4 text-sm text-amber-100">
                    Configure uma integração de pagamento antes de vender assinaturas online.
                    <a href="{{ route('company.payment-integrations.index') }}" class="ms-1 font-semibold text-[var(--text-main)] underline">Abrir integrações</a>
                </div>
            @endif
            <article class="sf-card p-5">
                <h3 class="text-lg font-semibold text-[var(--text-main)]">Nova assinatura</h3>
                <form method="POST" action="{{ route('clients.memberships.store', $client) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <label class="text-xs sf-text-muted">Plano</label>
                        <select name="membership_plan_id" class="sf-select mt-1 block w-full" required>
                            @foreach ($membershipPlans as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} — {{ $p->billing_cycle_label }} — R$ {{ number_format((float) $p->price, 2, ',', '.') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs sf-text-muted">Início</label>
                        <input type="date" name="starts_at" value="{{ old('starts_at', now()->toDateString()) }}" class="sf-input mt-1 block w-full">
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="flex items-center gap-2 text-sm sf-text-muted">
                            <input type="checkbox" name="accepted_terms" value="1" class="rounded border-white/20">
                            Termos aceitos
                        </label>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="sf-button-primary text-sm" @disabled(! ($hasMembershipPaymentGateway ?? false))>Criar assinatura</button>
                    </div>
                </form>
            </article>
        @endif

        @if (isset($memberships) && $memberships->isNotEmpty())
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Histórico de assinaturas</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10 text-sm">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase sf-text-muted">Plano</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase sf-text-muted">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase sf-text-muted">Período vigente</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase sf-text-muted">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @foreach ($memberships as $m)
                                <tr>
                                    <td class="px-4 py-3 text-[var(--text-main)]">{{ $m->plan?->name }}</td>
                                    <td class="px-4 py-3 sf-text-muted">{{ $m->status_label }}</td>
                                    <td class="px-4 py-3 sf-text-muted">{{ $m->current_cycle_starts_at?->format('d/m/Y') }} — {{ $m->current_cycle_ends_at?->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if (auth()->user()->isAdmin())
                                            @if ($m->status === 'active')
                                                <form method="POST" action="{{ route('customer-memberships.pause', $m) }}" class="inline">@csrf @method('PATCH')<button type="submit" class="text-xs text-amber-200 hover:underline">Pausar</button></form>
                                            @elseif ($m->status === 'paused')
                                                <form method="POST" action="{{ route('customer-memberships.resume', $m) }}" class="inline">@csrf @method('PATCH')<button type="submit" class="text-xs text-emerald-200 hover:underline">Retomar</button></form>
                                            @endif
                                            @if (in_array($m->status, ['active', 'paused'], true))
                                                <form method="POST" action="{{ route('customer-memberships.cancel', $m) }}" class="inline ms-2" onsubmit="return confirm('Cancelar assinatura?');">@csrf @method('PATCH')<button type="submit" class="text-xs text-rose-200 hover:underline">Cancelar</button></form>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        @endif

        @if (isset($membershipUsages) && $membershipUsages->isNotEmpty())
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Consumo do plano</h3>
                </div>
                <ul class="divide-y divide-white/10 px-5 py-2 text-sm sf-text-muted">
                    @foreach ($membershipUsages as $u)
                        <li class="py-2">
                            {{ $u->used_at?->format('d/m/Y H:i') }} — {{ $u->service?->name ?? 'Serviço' }}
                            @if ($u->appointment_id)
                                · agendamento #{{ $u->appointment_id }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </article>
        @endif

        @if (isset($noShows) && $noShows->isNotEmpty())
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Historico de faltas</h3>
                </div>
                <ul class="divide-y divide-white/10 px-5 py-2 text-sm sf-text-muted">
                    @foreach ($noShows as $ns)
                        <li class="py-2">
                            {{ $ns->occurred_at?->format('d/m/Y H:i') }}
                            @if ($ns->appointment_id)
                                · agendamento #{{ $ns->appointment_id }}
                            @endif
                            @if ($ns->reason)
                                — {{ $ns->reason }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </article>
        @endif

        @if (isset($clientReviews) && $clientReviews->isNotEmpty())
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Avaliacoes enviadas</h3>
                </div>
                <ul class="divide-y divide-white/10 px-5 py-2 text-sm sf-text-muted">
                    @foreach ($clientReviews as $r)
                        <li class="py-2">
                            {{ $r->submitted_at?->format('d/m/Y') }} — nota {{ $r->rating }}
                            @if ($r->comment)
                                <span class="block text-xs sf-text-muted">{{ \Illuminate\Support\Str::limit($r->comment, 120) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </article>
        @endif

        @if (isset($clientRecommendations) && $clientRecommendations->isNotEmpty())
            @include('partials.client-opportunities', [
                'recommendations' => $clientRecommendations,
                'title' => 'Oportunidades ativas',
                'subtitle' => 'Itens que entraram no prazo de recompra ou retorno.',
            ])
        @endif

        @if (isset($commercialHistories) && $commercialHistories->isNotEmpty())
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Histórico comercial</h3>
                    <p class="mt-1 text-sm sf-text-muted">Produtos comprados e serviços realizados pelo cliente.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[var(--input-bg)]">
                            <tr>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Data</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Item</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Tipo</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Profissional</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Próxima previsão</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @foreach ($commercialHistories as $history)
                                @php
                                    $typeLabel = $history->item_type === 'product' ? 'Produto' : 'Serviço';
                                    $isCanceled = $history->isCanceled();
                                @endphp
                                <tr class="transition hover:bg-white/5">
                                    <td class="px-5 py-3 text-sm text-[var(--text-main)]">{{ $history->occurred_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="px-5 py-3 text-sm sf-text-muted">
                                        {{ $history->item_name_snapshot }}
                                        @if ($isCanceled)
                                            <span class="ml-2 inline-flex rounded-full border border-rose-400/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-200">Cancelado</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-sm sf-text-muted">{{ $typeLabel }}</td>
                                    <td class="px-5 py-3 text-sm sf-text-muted">{{ $history->professional?->name ?? '-' }}</td>
                                    <td class="px-5 py-3 text-sm sf-text-muted">
                                        {{ $history->next_recommendation_date?->format('d/m/Y') ?? '-' }}
                                    </td>
                                    <td class="px-5 py-3 text-sm font-semibold text-[var(--text-main)]">
                                        {{ $history->total_amount_snapshot !== null
                                            ? 'R$ '.number_format((float) $history->total_amount_snapshot, 2, ',', '.')
                                            : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        @endif

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <div class="space-y-6">
                <article class="sf-card overflow-hidden">
                    <div class="border-b border-white/10 px-5 py-4">
                        <h3 class="text-lg font-semibold text-[var(--text-main)]">Histórico de atendimentos</h3>
                        <p class="mt-1 text-sm sf-text-muted">{{ $appointmentsThisMonth }} agendamento(s) neste mês.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[var(--input-bg)]">
                                <tr>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Data</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Serviço</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Profissional</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Valor</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse ($appointments as $appointment)
                                    @php
                                        $serviceLabel = $appointment->bookedServices()->pluck('name')->join(', ');
                                        $grossAmount = $appointment->payment?->gross_amount ?? $appointment->totalPriceAmount();
                                    @endphp
                                    <tr class="transition hover:bg-white/5">
                                        <td class="px-5 py-4 text-sm text-[var(--text-main)]">{{ $appointment->start_time->format('d/m/Y H:i') }}</td>
                                        <td class="px-5 py-4 text-sm sf-text-muted">{{ $serviceLabel !== '' ? $serviceLabel : ($appointment->service?->name ?? '-') }}</td>
                                        <td class="px-5 py-4 text-sm sf-text-muted">{{ $appointment->user?->name ?? '-' }}</td>
                                        <td class="px-5 py-4 text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $grossAmount, 2, ',', '.') }}</td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                                {{ $appointment->statusLabel() }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-10 text-center text-sm sf-text-muted">
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
                        <h3 class="text-lg font-semibold text-[var(--text-main)]">Histórico de compras</h3>
                        <p class="mt-1 text-sm sf-text-muted">{{ $productSalesThisMonth }} venda(s) de produto neste mês.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-[var(--input-bg)]">
                                <tr>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Data</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Produtos</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Profissional</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Pagamento</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse ($productSales as $sale)
                                    <tr class="transition hover:bg-white/5">
                                        <td class="px-5 py-4 text-sm text-[var(--text-main)]">{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                                        <td class="px-5 py-4 text-sm sf-text-muted">
                                            <div class="space-y-2">
                                                @foreach ($sale->items as $item)
                                                    <div class="flex items-center gap-3">
                                                        @if ($item->product->image_url)
                                                            <img src="{{ $item->product->image_url }}" alt="Imagem de {{ $item->product->name }}" class="h-10 w-10 rounded-xl object-cover ring-1 ring-white/10">
                                                        @else
                                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-dashed border-white/10 bg-[var(--input-bg)] brand-text">
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
                                        <td class="px-5 py-4 text-sm sf-text-muted">{{ $sale->user?->name ?? '-' }}</td>
                                        <td class="px-5 py-4 text-sm sf-text-muted">{{ ucfirst($sale->payment_method) }}</td>
                                        <td class="px-5 py-4 text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $sale->gross_amount, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-10 text-center text-sm sf-text-muted">
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
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Observações internas</h3>
                    <p class="mt-4 whitespace-pre-line text-sm leading-7 sf-text-muted">{{ $client->notes ?: 'Nenhuma observação registrada ainda.' }}</p>
                </article>

                <article class="sf-card p-5">
                    <h3 class="text-lg font-semibold text-[var(--text-main)]">Dados do cliente</h3>
                    <dl class="mt-4 space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm sf-text-muted">Código</dt>
                            <dd class="text-sm font-semibold text-[var(--text-main)]">{{ $client->client_code ?? '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm sf-text-muted">Telefone</dt>
                            <dd class="text-sm font-semibold text-[var(--text-main)]">{{ $client->phone }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm sf-text-muted">CPF</dt>
                            <dd class="text-sm font-semibold text-[var(--text-main)]">{{ $client->cpf ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $client->cpf) : '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm sf-text-muted">Aniversário</dt>
                            <dd class="text-sm font-semibold text-[var(--text-main)]">{{ $client->birthday?->format('d/m/Y') ?? '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm sf-text-muted">Última visita</dt>
                            <dd class="text-sm font-semibold text-[var(--text-main)]">{{ $lastVisitAt?->format('d/m/Y H:i') ?? '-' }}</dd>
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
