<x-app-layout>
    <x-slot name="header">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="sf-page-eyebrow">Clientes</p>
                <h2 class="sf-page-title mt-2">Clientes ausentes</h2>
            </div>
            <a href="{{ route('clients.index') }}" class="sf-button-ghost">Voltar</a>
        </div>
    </x-slot>

    <div class="space-y-5">
        <form class="sf-card flex flex-wrap items-end gap-3 p-4">
            <label>
                <span class="sf-label">Dias sem retorno</span>
                <input name="days" type="number" min="1" value="{{ $days }}" class="sf-input mt-1 w-32">
            </label>
            <button class="sf-button-primary">Filtrar</button>
            @foreach ([30, 60, 90] as $preset)
                <a href="{{ route('clients.absent', ['days' => $preset]) }}" class="sf-button-secondary">{{ $preset }} dias</a>
            @endforeach
        </form>

        <div class="sf-card overflow-x-auto p-0">
            <table class="min-w-full text-sm">
                <thead class="border-b border-white/10 text-left sf-muted">
                    <tr>
                        <th class="p-3">Cliente</th>
                        <th class="p-3">Telefone</th>
                        <th class="p-3">Ultima visita</th>
                        <th class="p-3">Dias sem retorno</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                        <tr class="border-b border-white/5">
                            <td class="p-3">{{ $client->name }}</td>
                            <td class="p-3">{{ $client->phone }}</td>
                            <td class="p-3">{{ $client->last_visit_at?->format('d/m/Y') ?? 'Sem visita registrada' }}</td>
                            <td class="p-3">{{ $client->last_visit_at ? (int) $client->last_visit_at->diffInDays(now()) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-4">{{ $clients->links() }}</div>
        </div>
    </div>
</x-app-layout>
