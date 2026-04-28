<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Financeiro</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Concluir atendimento
                </h2>
            </div>
            <p class="max-w-xl text-sm leading-6 text-[#c7d2e3]">
                Registre o pagamento e finalize o atendimento com comissao calculada automaticamente.
            </p>
        </div>
    </x-slot>

    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
        <aside class="sf-card p-6">
            <h3 class="text-base font-semibold text-white">Resumo do atendimento</h3>
            <dl class="mt-5 space-y-4">
                <div>
                    <dt class="text-sm text-[#c7d2e3]">Cliente</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $appointment->client->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[#c7d2e3]">Profissional</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $appointment->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[#c7d2e3]">Servico</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $appointment->service->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[#c7d2e3]">Horario</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">{{ $appointment->start_time->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[#c7d2e3]">Comissao configurada</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">
                        @if ($appointment->user->commission_type === 'percent')
                            {{ number_format((float) $appointment->user->commission_value, 2, ',', '.') }}%
                        @elseif ($appointment->user->commission_type === 'fixed')
                            R$ {{ number_format((float) $appointment->user->commission_value, 2, ',', '.') }}
                        @else
                            Sem comissao
                        @endif
                    </dd>
                </div>
            </dl>
        </aside>

        <section class="sf-card p-6 sm:p-7">
            <form method="POST" action="{{ route('appointments.payments.store', $appointment) }}" class="space-y-6">
                @csrf

                <div>
                    <x-input-label for="gross_amount" value="Valor bruto" />
                    <x-text-input
                        id="gross_amount"
                        name="gross_amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        class="mt-1 block w-full"
                        :value="old('gross_amount', $defaultGrossAmount)"
                        required
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('gross_amount')" />
                </div>

                <div>
                    <x-input-label for="payment_method" value="Forma de pagamento" />
                    <select id="payment_method" name="payment_method" class="sf-select mt-1 block w-full" required>
                        <option value="">Selecione</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
                </div>

                <div>
                    <x-input-label for="notes" value="Observacoes" />
                    <textarea id="notes" name="notes" rows="4" class="sf-input mt-1 block w-full">{{ old('notes') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                </div>

                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route('appointments.show', $appointment) }}" class="text-sm font-medium text-[#c7d2e3] transition hover:text-white">
                        Voltar
                    </a>
                    <x-primary-button>
                        Confirmar pagamento
                    </x-primary-button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
