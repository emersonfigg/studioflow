<x-app-layout>
    <x-slot name="header">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Avaliações internas</p>
        <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">Feedback pós-atendimento</h2>
    </x-slot>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="sf-card p-5">
            <p class="text-xs uppercase brand-text">Média geral</p>
            <p class="mt-2 text-3xl font-semibold text-[var(--text-main)]">{{ number_format($summary['avg_rating'], 2, ',', '.') }}</p>
            <p class="mt-1 text-sm sf-text-muted">{{ $summary['count'] }} avaliações</p>
        </div>
        <div class="sf-card p-5">
            <p class="text-xs uppercase brand-text">Notas baixas (≤2)</p>
            <p class="mt-2 text-3xl font-semibold text-rose-200">{{ $summary['low_count'] }}</p>
        </div>
        <div class="sf-card p-5">
            <p class="text-xs uppercase brand-text">Por profissional</p>
            <ul class="mt-2 max-h-32 space-y-1 overflow-y-auto text-sm sf-text-muted">
                @foreach ($byProfessional as $row)
                    <li>
                        {{ $row->professional_id ? ($professionals->firstWhere('id', $row->professional_id)?->name ?? 'Profissional #'.$row->professional_id) : '—' }}:
                        {{ number_format((float) $row->avg_rating, 1, ',', '.') }} ({{ $row->reviews_count }})
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <form method="GET" action="{{ route('reviews.index') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4">
        <div>
            <label class="text-xs sf-text-muted">De</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="sf-input mt-1 block text-sm">
        </div>
        <div>
            <label class="text-xs sf-text-muted">Até</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="sf-input mt-1 block text-sm">
        </div>
        <div>
            <label class="text-xs sf-text-muted">Profissional</label>
            <select name="professional_id" class="sf-select mt-1 block text-sm">
                <option value="">Todos</option>
                @foreach ($professionals as $pro)
                    <option value="{{ $pro->id }}" @selected(($filters['professional_id'] ?? null) === $pro->id)>{{ $pro->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs sf-text-muted">Nota máx. (alerta)</label>
            <input type="number" name="rating_max" min="1" max="5" value="{{ $filters['rating_max'] ?? '' }}" class="sf-input mt-1 block w-24 text-sm" placeholder="ex: 2">
        </div>
        <button type="submit" class="sf-button-secondary text-sm">Filtrar</button>
    </form>

    <div class="sf-card overflow-hidden">
        <table class="min-w-full divide-y divide-white/10 text-sm">
            <thead class="bg-[var(--input-bg)]">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase sf-text-muted">Data</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase sf-text-muted">Cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase sf-text-muted">Nota</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase sf-text-muted">Serviço</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase sf-text-muted">Comentário</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                @foreach ($reviews as $review)
                    @php
                        $low = (int) $review->rating <= 2;
                    @endphp
                    <tr class="{{ $low ? 'bg-rose-500/5' : '' }}">
                        <td class="px-4 py-3 sf-text-muted">{{ $review->submitted_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-[var(--text-main)]">{{ $review->client?->name }}</td>
                        <td class="px-4 py-3 font-semibold text-[var(--text-main)]">{{ $review->rating }}</td>
                        <td class="px-4 py-3 sf-text-muted">
                            {{ $review->appointment ? $review->appointment->bookedServices()->pluck('name')->filter()->implode(', ') : '' }}
                        </td>
                        <td class="px-4 py-3 sf-text-muted">{{ \Illuminate\Support\Str::limit((string) $review->comment, 160) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $reviews->links() }}</div>
    </div>
</x-app-layout>
