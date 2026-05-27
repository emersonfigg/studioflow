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
            @foreach (['day' => 'Hoje', 'week' => 'Semana', 'month' => 'Mês'] as $value => $label)
                <button name="range" value="{{ $value }}" class="{{ $range === $value ? 'sf-button-primary' : 'sf-button-secondary' }}" type="submit">{{ $label }}</button>
            @endforeach
        </form>

        @if ($range === 'day' && $clients->isNotEmpty())
            <x-birthday-congratulations
                :clients="$clients"
                :company="$company"
                :message-template="$birthdayCongratulationsMessage"
                :save-url="auth()->user()->isAdmin() ? route('company.birthday-message.update') : null"
                :can-save="auth()->user()->isAdmin()"
                :show-all-link="false"
            />
        @endif

        <div class="sf-card overflow-x-auto p-0">
            <table class="min-w-full text-sm">
                <thead class="border-b border-white/10 text-left sf-muted">
                    <tr>
                        <th class="p-3">Cliente</th>
                        <th class="p-3">Telefone</th>
                        <th class="p-3">Nascimento</th>
                        <th class="p-3">Última visita</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        @php
                            $congratulationsMessage = app(\App\Services\BirthdayService::class)->resolveMessage($company, $client, $birthdayCongratulationsMessage);
                            $whatsAppUrl = app(\App\Services\BirthdayService::class)->whatsAppUrl($client, $congratulationsMessage);
                        @endphp
                        <tr class="border-b border-white/5">
                            <td class="p-3">{{ $client->name }}</td>
                            <td class="p-3">{{ $client->phone }}</td>
                            <td class="p-3">{{ $client->birthday?->format('d/m') }}</td>
                            <td class="p-3">{{ $client->last_visit_at?->format('d/m/Y') ?? '-' }}</td>
                            <td class="p-3 text-right">
                                @if ($whatsAppUrl)
                                    <a class="sf-button-secondary !py-1 text-xs" href="{{ $whatsAppUrl }}" target="_blank" rel="noreferrer">WhatsApp</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center sf-muted">Nenhum aniversariante no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
