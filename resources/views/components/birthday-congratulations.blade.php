@props([
    'clients',
    'company',
    'messageTemplate',
    'saveUrl' => null,
    'canSave' => false,
    'showAllLink' => true,
])

@php
    $clientRows = $clients->map(fn ($client) => [
        'id' => $client->id,
        'name' => $client->name,
        'phone' => $client->phone,
        'birthday_label' => $client->birthday?->format('d/m') ?? '-',
    ])->values();
@endphp

<section
    {{ $attributes->merge(['class' => 'sf-card border border-pink-400/30 p-5 sm:p-6']) }}
    x-data="{
        template: @js($messageTemplate),
        clients: @js($clientRows),
        companyName: @js($company->name),
        saving: false,
        saved: false,
        canSave: @js($canSave),
        saveUrl: @js($saveUrl),
        resolveMessage(name) {
            return this.template
                .replaceAll('{nome}', name)
                .replaceAll('{empresa}', this.companyName);
        },
        whatsAppUrl(phone, name) {
            const digits = String(phone || '').replace(/\D+/g, '');
            if (!digits) {
                return null;
            }
            return 'https://wa.me/' + digits + '?text=' + encodeURIComponent(this.resolveMessage(name));
        },
        async saveTemplate() {
            if (!this.canSave || !this.saveUrl) {
                return;
            }
            this.saving = true;
            this.saved = false;
            try {
                const response = await fetch(this.saveUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @js(csrf_token()),
                    },
                    body: JSON.stringify({ birthday_congratulations_message: this.template }),
                });
                if (!response.ok) {
                    throw new Error('Falha ao salvar');
                }
                this.saved = true;
                setTimeout(() => this.saved = false, 2500);
            } catch (error) {
                alert('Não foi possível salvar a mensagem padrão. Tente novamente.');
            } finally {
                this.saving = false;
            }
        },
    }"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-pink-200">Aniversário</p>
            <h3 class="mt-2 text-lg font-semibold text-[var(--text-main)]">Aniversariante{{ $clients->count() === 1 ? '' : 's' }} do dia!</h3>
            <p class="mt-1 text-sm sf-text-muted">
                {{ $clients->count() }} cliente{{ $clients->count() === 1 ? '' : 's' }} faz{{ $clients->count() === 1 ? '' : 'em' }} aniversário hoje. Envie uma mensagem de parabéns pelo WhatsApp.
            </p>
        </div>
        @if ($showAllLink)
            <a href="{{ route('clients.birthdays', ['range' => 'day']) }}" class="sf-button-ghost shrink-0 text-sm">Ver relatório</a>
        @endif
    </div>

    <ul class="mt-4 divide-y divide-white/10 rounded-xl border border-white/10">
        <template x-for="client in clients" :key="client.id">
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                <div class="min-w-0">
                    <p class="font-semibold text-[var(--text-main)]" x-text="client.name"></p>
                    <p class="text-xs sf-text-muted">
                        <span x-text="'Aniversário: ' + client.birthday_label"></span>
                        <span x-show="client.phone" x-text="' · ' + client.phone"></span>
                    </p>
                </div>
                <a
                    x-show="whatsAppUrl(client.phone, client.name)"
                    :href="whatsAppUrl(client.phone, client.name)"
                    target="_blank"
                    rel="noreferrer"
                    class="sf-button-primary shrink-0 !py-2 text-xs"
                >
                    Enviar WhatsApp
                </a>
                <span x-show="!whatsAppUrl(client.phone, client.name)" class="text-xs sf-text-muted">Sem telefone</span>
            </li>
        </template>
    </ul>

    <div class="mt-5 rounded-xl border border-white/10 bg-[var(--input-bg)] p-4">
        <label for="birthday-message-template" class="text-sm font-medium text-[var(--text-main)]">Mensagem de felicitações</label>
        <p class="mt-1 text-xs sf-text-muted">Use <code class="rounded bg-white/10 px-1">{nome}</code> e <code class="rounded bg-white/10 px-1">{empresa}</code> para personalizar.</p>
        <textarea
            id="birthday-message-template"
            x-model="template"
            rows="4"
            class="sf-input mt-3 block w-full resize-y text-sm"
        ></textarea>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="sf-button-secondary text-sm"
                x-show="canSave"
                x-on:click="saveTemplate()"
                :disabled="saving"
            >
                <span x-show="!saving">Salvar mensagem padrão</span>
                <span x-show="saving">Salvando...</span>
            </button>
            <p class="text-xs text-emerald-300" x-show="saved" x-cloak>Mensagem padrão salva.</p>
        </div>
    </div>
</section>
