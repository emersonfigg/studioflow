<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Financeiro</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Fazer acerto de comissão
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">
                    Confirme os atendimentos incluídos, o total bruto e o valor de comissão a pagar.
                </p>
            </div>

            <a href="{{ route('finance.commissions', ['from' => $startDate->format('Y-m-d'), 'to' => $endDate->format('Y-m-d'), 'user_id' => $professional->id]) }}" class="sf-button-secondary">
                Voltar para comissões
            </a>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('finance.commissions.settlements.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        @csrf
        <input type="hidden" name="user_id" value="{{ $professional->id }}">
        <input type="hidden" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
        <input type="hidden" name="end_date" value="{{ $endDate->format('Y-m-d') }}">

        <section class="sf-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ $professional->name }}</h3>
                        <p class="mt-1 text-sm text-[#c7d2e3]">
                            Periodo de {{ $startDate->format('d/m/Y') }} até {{ $endDate->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="rounded-full border border-[#d4af37]/25 bg-[#d4af37]/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-[#f6e7b3]">
                        {{ $payments->count() }} atendimentos incluídos
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10">
                    <thead class="bg-[#132746]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Serviço</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Bruto</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Comissão</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/8 bg-[#223d69]">
                        @foreach ($payments as $payment)
                            <tr class="transition hover:bg-white/[0.03]">
                                <td class="px-6 py-4 text-sm text-white">{{ $payment->paid_at->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $payment->client->name }}</td>
                                <td class="px-6 py-4 text-sm text-[#c7d2e3]">{{ $payment->service->name }}</td>
                                <td class="px-6 py-4 text-sm text-white">R$ {{ number_format((float) $payment->gross_amount, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm text-[#d4af37]">R$ {{ number_format((float) $payment->commission_amount, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Resumo</p>
                <div class="mt-4 space-y-3">
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-sm text-[#c7d2e3]">Total bruto</p>
                        <p class="mt-2 text-2xl font-semibold text-white">R$ {{ number_format($grossAmount, 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-sm text-[#c7d2e3]">Comissão a pagar</p>
                        <p class="mt-2 text-2xl font-semibold text-[#d4af37]">R$ {{ number_format($commissionAmount, 2, ',', '.') }}</p>
                    </div>
                </div>
            </section>

            <section class="sf-card p-5 space-y-4">
                <div>
                    <x-input-label for="payment_method" value="Forma de pagamento" />
                    <select id="payment_method" name="payment_method" class="sf-select mt-2 block w-full" required>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
                </div>

                <div>
                    <x-input-label for="notes" value="Observações" />
                    <textarea id="notes" name="notes" rows="4" class="sf-input mt-2 block w-full">{{ old('notes') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                </div>

                <button type="submit" class="sf-button-primary w-full">
                    Confirmar acerto
                </button>
            </section>
        </aside>
    </form>
</x-app-layout>
