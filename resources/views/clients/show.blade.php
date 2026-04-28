<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Clientes</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">{{ $client->name }}</h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">
                    {{ $client->phone }} · cliente desde {{ $client->created_at->format('d/m/Y') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('appointments.create', ['client_id' => $client->id]) }}" class="sf-button-primary">Novo agendamento</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('clients.edit', $client) }}" class="sf-button-secondary">Editar cliente</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                {{ session('status') === 'client-updated' ? 'Cliente atualizado com sucesso.' : session('status') }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Total gasto</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($totalSpent, 2, ',', '.') }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Total visitas</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $totalVisits }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Ultima visita</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ $lastVisitAt?->format('d/m/Y H:i') ?? '-' }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Ticket medio</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <article class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h3 class="text-lg font-semibold text-white">Historico de atendimentos</h3>
                    <p class="mt-1 text-sm text-[#c7d2e3]">{{ $appointmentsThisMonth }} agendamento(s) neste mes.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-[#132746]">
                            <tr>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Data</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Servico</th>
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

            <div class="space-y-6">
                <article class="sf-card p-5">
                    <h3 class="text-lg font-semibold text-white">Observacoes internas</h3>
                    <p class="mt-4 whitespace-pre-line text-sm leading-7 text-[#c7d2e3]">{{ $client->notes ?: 'Nenhuma observacao registrada ainda.' }}</p>
                </article>

                <article class="sf-card p-5">
                    <h3 class="text-lg font-semibold text-white">Dados do cliente</h3>
                    <dl class="mt-4 space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Telefone</dt>
                            <dd class="text-sm font-semibold text-white">{{ $client->phone }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Aniversario</dt>
                            <dd class="text-sm font-semibold text-white">{{ $client->birthday?->format('d/m/Y') ?? '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-[#c7d2e3]">Ultima visita</dt>
                            <dd class="text-sm font-semibold text-white">{{ $lastVisitAt?->format('d/m/Y H:i') ?? '-' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('clients.index') }}" class="sf-button-ghost">Voltar para clientes</a>
                        @if (auth()->user()->isAdmin())
                            <form method="POST" action="{{ route('clients.destroy', $client) }}">
                                @csrf
                                @method('DELETE')
                                <x-danger-button onclick="return confirm('Excluir este cliente?')">
                                    Excluir
                                </x-danger-button>
                            </form>
                        @endif
                    </div>
                </article>
            </div>
        </section>
    </div>
</x-app-layout>
