<x-app-layout>
    <x-slot name="header">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="sf-page-eyebrow">Clientes</p>
                <h2 class="sf-page-title mt-2">Aniversariantes</h2>
            </div>
            <a href="{{ route('clients.index') }}" class="sf-button-ghost">Voltar</a>
        </div>
    </x-slot>

    <div class="space-y-5">
        <form class="sf-card flex flex-wrap gap-3 p-4">
            @foreach (['day' => 'Hoje', 'week' => 'Semana', 'month' => 'Mes'] as $value => $label)
                <button name="range" value="{{ $value }}" class="{{ $range === $value ? 'sf-button-primary' : 'sf-button-secondary' }}" type="submit">{{ $label }}</button>
            @endforeach
        </form>

        <div class="sf-card overflow-x-auto p-0">
            <table class="min-w-full text-sm">
                <thead class="border-b border-white/10 text-left sf-muted">
                    <tr>
                        <th class="p-3">Cliente</th>
                        <th class="p-3">Telefone</th>
                        <th class="p-3">Nascimento</th>
                        <th class="p-3">Ultima visita</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr class="border-b border-white/5">
                            <td class="p-3">{{ $client->name }}</td>
                            <td class="p-3">{{ $client->phone }}</td>
                            <td class="p-3">{{ $client->birthday?->format('d/m') }}</td>
                            <td class="p-3">{{ $client->last_visit_at?->format('d/m/Y') ?? '-' }}</td>
                            <td class="p-3 text-right">
                                @if ($client->phone)
                                    <a class="sf-button-secondary !py-1 text-xs" href="https://wa.me/{{ preg_replace('/\D+/', '', $client->phone) }}" target="_blank">WhatsApp</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center sf-muted">Nenhum aniversariante no periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
