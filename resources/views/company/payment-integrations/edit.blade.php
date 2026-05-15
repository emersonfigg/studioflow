<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="sf-page-eyebrow">Empresa</p>
                <h2 class="sf-page-title mt-1">Editar integracao</h2>
                <p class="sf-page-subtitle mt-2 max-w-3xl">
                    Ajuste o fallback manual, revise o estado atual da conexao e mantenha a operacao segura sem expor segredos em tela.
                </p>
            </div>
            <a href="{{ route('company.payment-integrations.index') }}" class="sf-button-ghost text-sm">Voltar as integracoes</a>
        </div>
    </x-slot>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="sf-card p-6 sm:p-7">
            <form method="POST" action="{{ route('company.payment-integrations.update', $integration) }}" class="space-y-5">
                @csrf
                @method('PATCH')

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="sf-label">Gateway</label>
                        <select name="provider" class="sf-select mt-2 block w-full" required>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->value }}" @selected(old('provider', $integration->provider->value) === $provider->value)>{{ strtoupper(str_replace('_', ' ', $provider->value)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="sf-label">Nome interno</label>
                        <input type="text" name="name" value="{{ old('name', $integration->name) }}" class="sf-input mt-2 block w-full">
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4 text-xs sf-text-muted">
                    <p class="sf-label">Credenciais salvas</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <p>Access token: <span class="font-semibold text-[var(--text-main)]">{{ $integration->access_token_masked ?? '—' }}</span></p>
                        <p>Refresh token: <span class="font-semibold text-[var(--text-main)]">{{ $integration->refresh_token_masked ?? '—' }}</span></p>
                        <p>API key: <span class="font-semibold text-[var(--text-main)]">{{ $integration->api_key_masked ?? '—' }}</span></p>
                        <p>Public key: <span class="font-semibold text-[var(--text-main)]">{{ $integration->public_key_masked ?? '—' }}</span></p>
                        <p class="sm:col-span-2">Webhook secret: <span class="font-semibold text-[var(--text-main)]">{{ $integration->webhook_secret_masked ?? '—' }}</span></p>
                    </div>
                </div>

                @if ($integration->provider === \App\Enums\PaymentProvider::MercadoPago)
                    <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <p class="sf-label">Mercado Pago</p>
                        <p class="mt-2 text-sm leading-6 sf-text-muted">
                            O fluxo recomendado continua sendo o OAuth. Os campos abaixo seguem disponiveis como fallback avancado para ajustes pontuais.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($mercadoPagoOauthConfigured)
                                <a href="{{ route('company.payment-integrations.mercado-pago.connect') }}"
                                    class="sf-button-primary text-sm"
                                    target="_blank"
                                    onclick="window.open(this.href, 'mercadoPagoOAuth', 'width=720,height=860,menubar=no,toolbar=no,location=yes,status=no,scrollbars=yes,resizable=yes'); return false;">
                                    Reconectar Mercado Pago
                                </a>
                            @endif
                            <form method="POST" action="{{ route('company.payment-integrations.mercado-pago.disconnect') }}">
                                @csrf
                                <button type="submit" class="sf-button-secondary text-sm">Desconectar</button>
                            </form>
                        </div>
                    </div>
                @endif

                <div>
                    <label class="sf-label">Novo access token</label>
                    <input type="password" name="access_token" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    <p class="mt-1 text-xs sf-text-muted">Deixe em branco para manter o valor atual.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="sf-label">Novo refresh token</label>
                        <input type="password" name="refresh_token" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="sf-label">Nova API key</label>
                        <input type="password" name="api_key" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="sf-label">Nova public key</label>
                        <input type="password" name="public_key" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="sf-label">Novo segredo do webhook</label>
                        <input type="password" name="webhook_secret" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="sf-label">Identificador da conta</label>
                        <input type="text" name="account_identifier" value="{{ old('account_identifier', $integration->account_identifier) }}" class="sf-input mt-2 block w-full">
                    </div>
                    <div>
                        <label class="sf-label">Ambiente</label>
                        <select name="environment" class="sf-select mt-2 block w-full" required>
                            @foreach ($environments as $environment)
                                <option value="{{ $environment->value }}" @selected(old('environment', $integration->environment->value) === $environment->value)>{{ $environment->value }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm sf-text-muted">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" class="rounded border-white/20" @checked(old('active', $integration->active))>
                        Ativar esta integracao
                    </label>
                    <label class="flex items-center gap-2 text-sm sf-text-muted">
                        <input type="hidden" name="default_for_memberships" value="0">
                        <input type="checkbox" name="default_for_memberships" value="1" class="rounded border-white/20" @checked(old('default_for_memberships', $integration->default_for_memberships))>
                        Usar como gateway padrao de assinaturas
                    </label>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('company.payment-integrations.index') }}" class="sf-button-secondary">Cancelar</a>
                    <x-primary-button>Salvar</x-primary-button>
                </div>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="sf-card p-5">
                <p class="sf-page-eyebrow">Estado atual</p>
                <h3 class="sf-section-title mt-1 text-xl">Resumo da conexao</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="sf-card-soft p-4">
                        <dt class="sf-text-muted">Status</dt>
                        <dd class="mt-1 font-semibold text-[var(--text-main)]">{{ $integration->status ?: 'pending' }}</dd>
                    </div>
                    <div class="sf-card-soft p-4">
                        <dt class="sf-text-muted">Conectado em</dt>
                        <dd class="mt-1 font-semibold text-[var(--text-main)]">{{ $integration->connected_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="sf-card-soft p-4">
                        <dt class="sf-text-muted">Expira em</dt>
                        <dd class="mt-1 font-semibold text-[var(--text-main)]">{{ $integration->expires_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="sf-card-soft p-4">
                        <dt class="sf-text-muted">Ambiente</dt>
                        <dd class="mt-1 font-semibold text-[var(--text-main)]">{{ $integration->environment->value }}</dd>
                    </div>
                </dl>
            </div>

            <div class="sf-card p-5">
                <p class="sf-page-eyebrow">Boas praticas</p>
                <ul class="mt-3 space-y-3 text-sm leading-6 sf-text-muted">
                    <li>• Prefira OAuth sempre que o gateway suportar.</li>
                    <li>• Use o modo manual apenas para fallback tecnico.</li>
                    <li>• Nao compartilhe credenciais fora do fluxo seguro da plataforma.</li>
                </ul>
            </div>
        </aside>
    </div>
</x-app-layout>
