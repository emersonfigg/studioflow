<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Clientes</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Clientes da empresa
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Gerencie relacionamento e histórico da base.</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <form method="GET" action="{{ route('clients.index') }}" class="w-full sm:w-[320px]">
                    <label for="clients-search" class="sr-only">Buscar por nome, telefone, CPF ou código</label>
                    <div class="relative">
                        <input
                            id="clients-search"
                            name="search"
                            type="text"
                            value="{{ $search }}"
                            placeholder="Buscar por nome, telefone, CPF ou código"
                            class="sf-input w-full pr-12"
                        >
                        <button type="submit" class="absolute inset-y-0 right-2 my-2 inline-flex w-9 items-center justify-center rounded-xl border border-white/10 bg-[#1b335b] text-[#d4af37] transition hover:border-[#d4af37]/30 hover:bg-[#223d69]">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 013.982 9.295l3.111 3.112a.75.75 0 01-1.06 1.06l-3.112-3.11A5.5 5.5 0 119 3.5zm0 1.5a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </form>

                @if (auth()->user()->isAdmin() && auth()->user()->company_id)
                    <a href="{{ route('clients.create') }}" class="sf-button-primary whitespace-nowrap">
                        Novo cliente
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                @switch(session('status'))
                    @case('client-created')
                        Cliente criado com sucesso.
                        @break
                    @case('client-updated')
                        Cliente atualizado com sucesso.
                        @break
                    @case('client-deleted')
                        Cliente excluido com sucesso.
                        @break
                    @case('client-deactivated')
                        Cliente desativado. Ele deixara de aparecer em novas vendas e agendamentos.
                        @break
                    @case('client-reactivated')
                        Cliente reativado com sucesso.
                        @break
                    @case('client-delete-blocked')
                        Nao foi possivel excluir: o cliente possui historico operacional (agendamentos, comandas ou pagamentos). Use desativar.
                        @break
                    @default
                        {{ session('status') }}
                @endswitch
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Total clientes</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $totalClients }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Novos no mês</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $newClientsThisMonth }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Retornaram no mês</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $returningClientsThisMonth }}</p>
            </article>
            <article class="sf-card p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Ticket medio</p>
                <p class="mt-3 text-3xl font-semibold text-white">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
            </article>
        </section>

        <section class="sf-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10">
                    <thead class="bg-[#132746]">
                        <tr>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Código</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Cliente</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Telefone</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">CPF</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Status</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Última visita</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Total gasto</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Visitas</th>
                            <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-[0.18em] text-[#c7d2e3]">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse ($clients as $client)
                            <tr class="transition hover:bg-white/5">
                                <td class="px-5 py-4 text-sm font-semibold text-white">{{ $client->client_code ?? '-' }}</td>
                                <td class="px-5 py-4">
                                    <div>
                                        <p class="text-sm font-semibold text-white">{{ $client->name }}</p>
                                        <p class="mt-1 text-xs text-[#c7d2e3]">Cliente desde {{ $client->created_at->format('d/m/Y') }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm text-[#c7d2e3]">{{ $client->phone }}</td>
                                <td class="px-5 py-4 text-sm text-[#c7d2e3]">{{ $client->cpf ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $client->cpf) : '-' }}</td>
                                <td class="px-5 py-4 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $client->active ? 'bg-emerald-500/20 text-emerald-100' : 'bg-rose-500/20 text-rose-100' }}">
                                        {{ $client->active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-[#c7d2e3]">
                                    {{ $client->last_visit_at?->format('d/m/Y H:i') ?? '-' }}
                                </td>
                                <td class="px-5 py-4 text-sm font-semibold text-white">
                                    R$ {{ number_format((float) ($client->total_spent ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="px-5 py-4 text-sm text-[#c7d2e3]">{{ $client->visits_count }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ route('clients.show', $client) }}" class="sf-button-ghost !px-4 !py-2.5">Visualizar</a>
                                        @if (auth()->user()->isAdmin())
                                            <a href="{{ route('clients.edit', $client) }}" class="sf-button-secondary !px-4 !py-2.5">Editar</a>
                                            @if ($client->active)
                                                <form method="POST" action="{{ route('clients.deactivate', $client) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="sf-button-secondary !px-4 !py-2.5" onclick="return confirm('Desativar este cliente?')">Inativar</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('clients.reactivate', $client) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="sf-button-secondary !px-4 !py-2.5">Reativar</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="sf-button-ghost !px-4 !py-2.5 text-rose-200" onclick="return confirm('Excluir permanentemente este cliente?')">Excluir</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-10 text-center text-sm text-[#c7d2e3]">
                                    Nenhum cliente encontrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($clients->hasPages())
                <div class="border-t border-white/10 px-5 py-4">
                    {{ $clients->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
