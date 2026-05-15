@php
    $backToAgendaUrl = route('appointments.index');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">{{ __('Appointments') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                    {{ __('Appointment') }}
                </h2>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                        {{ $appointment->statusLabel() }}
                    </span>
                    @if ($appointment->status === 'completed')
                        <span class="inline-flex items-center rounded-full border border-emerald-400/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-100">
                            Atendimento concluido
                        </span>
                    @endif
                    @if ($appointment->payment)
                        <span class="inline-flex items-center rounded-full border border-[color-mix(in_srgb,var(--brand-primary)_30%,transparent)] bg-[var(--brand-primary)]/10 px-2.5 py-1 text-xs font-semibold text-[var(--text-main)]">
                            Pagamento registrado
                        </span>
                    @endif
                    @if (! empty($membershipSummary['active']))
                        <span class="inline-flex items-center rounded-full border border-[color-mix(in_srgb,var(--brand-primary)_40%,transparent)] bg-[var(--brand-primary)]/10 px-2.5 py-1 text-xs font-semibold text-[var(--text-main)]">
                            Assinante · {{ $membershipSummary['plan_name'] ?? 'Plano' }}@if (! empty($membershipSummary['billing_cycle_label'] ?? null)) · {{ $membershipSummary['billing_cycle_label'] }}@endif
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ $backToAgendaUrl }}" class="sf-button-ghost">
                    Voltar para agenda
                </a>

                <a href="{{ route('appointments.create') }}" class="sf-button-primary">
                    Novo agendamento
                </a>

                @if (! $appointment->payment && $appointment->status !== 'cancelled' && (auth()->user()->isAdmin() || auth()->id() === $appointment->user_id))
                    <a href="{{ route('appointments.orders.show', $appointment) }}" class="sf-button-secondary">
                        Abrir comanda
                    </a>
                @endif

                @if ($appointment->status === 'completed' && ! $appointment->payment && (auth()->user()->isAdmin() || auth()->id() === $appointment->user_id))
                    <a href="{{ route('appointments.payments.create', $appointment) }}" class="sf-button-secondary">
                        Registrar pagamento
                    </a>
                @elseif ($appointment->status !== 'completed' && $appointment->status !== 'cancelled' && ! $appointment->payment && (auth()->user()->isAdmin() || auth()->id() === $appointment->user_id))
                    <a href="{{ route('pdv.index', ['appointment_id' => $appointment->id]) }}" class="sf-button-primary">
                        Concluir atendimento
                    </a>
                @endif

                @if (auth()->user()->isAdmin())
                    <a href="{{ route('appointments.edit', $appointment) }}" class="sf-button-secondary">
                        {{ __('Edit') }}
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @if (isset($activeBlock) && $activeBlock)
        <div class="mx-auto mb-4 max-w-5xl rounded-2xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-100">
            <p class="font-semibold">Cliente bloqueado até {{ $activeBlock->ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
            <p class="mt-1 text-rose-100/90">Motivo: {{ $activeBlock->reason ?? 'Bloqueio ativo' }} ({{ $activeBlock->type }})</p>
        </div>
    @endif

    @if (session('status') === 'appointment-no-show')
        <div class="mx-auto mb-4 max-w-5xl rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
            Falta registrada e bloqueio aplicado conforme politica da empresa.
        </div>
    @endif

    @if (session('status') === 'payment-method-updated')
        <div class="mx-auto mb-4 max-w-5xl rounded-2xl border border-emerald-400/25 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
            Forma de pagamento atualizada com sucesso.
        </div>
    @endif

    @if (isset($clientRecommendations) && $clientRecommendations->isNotEmpty())
        <div class="mx-auto mb-4 max-w-5xl">
            @include('partials.client-opportunities', [
                'recommendations' => $clientRecommendations,
                'title' => 'Oportunidades para este cliente',
                'subtitle' => 'Use estas sugestões para conversar com o cliente no início do atendimento.',
            ])
        </div>
    @endif

    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="sf-card p-6 sm:p-7">
            <dl class="grid gap-6 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium sf-text-muted">{{ __('Client') }}</dt>
                    <dd class="mt-1 text-sm text-[var(--text-main)]">{{ $appointment->client->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium sf-text-muted">{{ __('Service') }}</dt>
                    <dd class="mt-1 text-sm text-[var(--text-main)]">{{ $appointment->service->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium sf-text-muted">{{ __('Staff') }}</dt>
                    <dd class="mt-1 text-sm text-[var(--text-main)]">{{ $appointment->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium sf-text-muted">{{ __('Status') }}</dt>
                    <dd class="mt-1 text-sm text-[var(--text-main)]">{{ $appointment->statusLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium sf-text-muted">{{ __('Source') }}</dt>
                    <dd class="mt-1 text-sm text-[var(--text-main)]">{{ __(str_replace('_', ' ', ucfirst($appointment->source))) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium sf-text-muted">{{ __('Start') }}</dt>
                    <dd class="mt-1 text-sm text-[var(--text-main)]">{{ $appointment->start_time->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium sf-text-muted">{{ __('End') }}</dt>
                    <dd class="mt-1 text-sm text-[var(--text-main)]">{{ $appointment->end_time->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium sf-text-muted">{{ __('Notes') }}</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-[var(--text-main)]">{{ $appointment->notes ?: '-' }}</dd>
                </div>
            </dl>

            @if (! empty($membershipSummary['active'] ?? false))
                <div class="mt-6 rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)] bg-[var(--brand-primary)]/5 px-4 py-4 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Assinatura ativa</p>
                    <p class="mt-2 text-[var(--text-main)]">{{ $membershipSummary['plan_name'] }}
                        @if (! empty($membershipSummary['billing_cycle_label'] ?? null))
                            · {{ $membershipSummary['billing_cycle_label'] }}
                        @endif
                        · período {{ $membershipSummary['cycle_label'] }}
                    </p>
                    <ul class="mt-3 list-disc space-y-1 pl-5 sf-text-muted">
                        @foreach ($membershipSummary['service_rules'] as $rule)
                            <li>
                                {{ $rule['name'] }}:
                                @if (! empty($rule['included']))
                                    incluso no plano
                                @elseif (! empty($rule['discount_percent']))
                                    desconto {{ $rule['discount_percent'] }}%
                                @endif
                                @if (isset($rule['remaining']) && $rule['remaining'] !== null)
                                    · restam {{ $rule['remaining'] }} uso(s) no ciclo
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($appointment->appointmentReview && auth()->user()->hasFinancialPrivileges() && $appointment->appointmentReview->token && $appointment->appointmentReview->submitted_at === null)
                <div class="mt-6 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-3 text-sm sf-text-muted">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Avaliacao pendente</p>
                    <p class="mt-2 break-all text-xs text-[var(--text-main)]">
                        <a href="{{ route('public-reviews.show', $appointment->appointmentReview->token) }}" class="brand-text underline" target="_blank" rel="noopener">{{ route('public-reviews.show', $appointment->appointmentReview->token) }}</a>
                    </p>
                </div>
            @endif

            @if (in_array($appointment->status, ['scheduled', 'confirmed', 'in_progress'], true) && ! $appointment->payment && (auth()->user()->isAdmin() || auth()->id() === $appointment->user_id))
                @if (! $appointment->serviceOrder || $appointment->serviceOrder->status !== 'paid')
                    <form method="POST" action="{{ route('appointments.no-show', $appointment) }}" class="mt-6 space-y-2 rounded-2xl border border-orange-400/25 bg-orange-500/5 px-4 py-4" onsubmit="return confirm('Marcar falta registra no-show e bloqueia o cliente por 24h. Continuar?');">
                        @csrf
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-orange-200">Controle de faltas</p>
                        <label class="block text-xs sf-text-muted">Motivo (opcional)</label>
                        <textarea name="reason" rows="2" class="sf-input w-full text-sm"></textarea>
                        <button type="submit" class="sf-button-secondary text-xs">Marcar falta (no-show)</button>
                    </form>
                @endif
            @endif

            <div class="mt-8 flex flex-col gap-4 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ $backToAgendaUrl }}" class="sf-button-secondary">
                    Voltar para agenda
                </a>

                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('appointments.destroy', $appointment) }}">
                        @csrf
                        @method('DELETE')
                        <x-danger-button onclick="return confirm('{{ __('Delete this appointment?') }}')">
                            {{ __('Delete') }}
                        </x-danger-button>
                    </form>
                @endif
            </div>
        </div>

        <aside class="sf-card p-6">
            <h3 class="text-base font-semibold text-[var(--text-main)]">Comanda e pagamento</h3>
            @if ($appointment->serviceOrder)
                <div class="mt-4 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Total da comanda</p>
                    <p class="mt-2 text-2xl font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $appointment->serviceOrder->total, 2, ',', '.') }}</p>
                    <p class="mt-1 text-sm sf-text-muted">{{ $appointment->serviceOrder->status === 'paid' ? 'Comanda paga' : 'Comanda aberta' }}</p>
                </div>
            @endif
            @if ($appointment->payment)
                <dl class="mt-5 space-y-4">
                    <div>
                        <dt class="text-sm sf-text-muted">Forma de pagamento</dt>
                        <dd class="mt-1 text-sm font-semibold text-[var(--text-main)]">{{ \App\Models\Payment::labelForPaymentMethod((string) $appointment->payment->payment_method) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm sf-text-muted">Valor bruto</dt>
                        <dd class="mt-1 text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $appointment->payment->gross_amount, 2, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm sf-text-muted">Comissão</dt>
                        <dd class="mt-1 text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $appointment->payment->commission_amount, 2, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm sf-text-muted">Liquido da empresa</dt>
                        <dd class="mt-1 text-sm font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $appointment->payment->net_amount, 2, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm sf-text-muted">Pago em</dt>
                        <dd class="mt-1 text-sm font-semibold text-[var(--text-main)]">{{ $appointment->payment->paid_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
                @if ($appointment->status === 'completed' && auth()->user()->hasFinancialPrivileges())
                    <details class="mt-5 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-3 text-sm sf-text-muted">
                        <summary class="cursor-pointer text-xs font-bold uppercase tracking-[0.16em] brand-text">Corrigir forma de pagamento</summary>
                        <form method="POST" action="{{ route('appointments.payment-method.update', $appointment) }}" class="mt-4 space-y-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="payment_method_fix" class="text-xs font-semibold text-[var(--text-main)]">Nova forma</label>
                                <select id="payment_method_fix" name="payment_method" class="sf-select mt-2 block w-full" required>
                                    @foreach (\App\Models\Payment::paymentMethodOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_method', $appointment->payment->payment_method) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
                            </div>
                            <button type="submit" class="sf-button-secondary w-full text-xs">Salvar correção</button>
                            <p class="text-[11px] leading-relaxed sf-text-muted/80">O valor e a comissão não são alterados; apenas a forma registrada e o caixa vinculado.</p>
                        </form>
                    </details>
                @endif
            @else
                <p class="mt-4 text-sm leading-6 sf-text-muted">
                    Ainda não há pagamento registrado para este atendimento.
                </p>

                @if ($appointment->status === 'completed' && (auth()->user()->isAdmin() || auth()->id() === $appointment->user_id))
                    <div class="mt-5">
                        <a href="{{ route('appointments.payments.create', $appointment) }}" class="sf-button-primary">
                            Registrar pagamento
                        </a>
                    </div>
                @endif
            @endif
        </aside>
    </div>
</x-app-layout>
