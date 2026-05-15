<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="sf-page-eyebrow">Empresa</p>
                <h2 class="sf-page-title mt-1">Nova integracao de pagamento</h2>
                <p class="sf-page-subtitle mt-2 max-w-3xl">
                    Cadastre um fallback manual sem expor segredos na interface principal. Para Mercado Pago, prefira a conexao OAuth sempre que ela estiver disponivel.
                </p>
            </div>
            <a href="{{ route('company.payment-integrations.index') }}" class="sf-button-ghost text-sm">Voltar as integracoes</a>
        </div>
    </x-slot>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="sf-card p-6 sm:p-7">
            <form method="POST" action="{{ route('company.payment-integrations.store') }}" class="space-y-5">
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="sf-label">Gateway</label>
                        <select name="provider" class="sf-select mt-2 block w-full" required>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->value }}" @selected(old('provider') === $provider->value)>{{ strtoupper(str_replace('_', ' ', $provider->value)) }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('provider')" />
                    </div>

                    <div>
                        <label class="sf-label">Nome interno</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="sf-input mt-2 block w-full" placeholder="Ex.: Mercado Pago principal">
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                    <p class="sf-label">Configuracao manual avancada</p>
                    <p class="mt-2 text-sm leading-6 sf-text-muted">
                        Use somente se souber exatamente o que esta fazendo. O recomendado e conectar pelo botao Mercado Pago e deixar o StudioFlow salvar o acesso automaticamente.
                    </p>
                </div>

                <div>
                    <label class="sf-label">Access token / chave principal</label>
                    <input type="password" name="access_token" value="{{ old('access_token') }}" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    <p class="mt-1 text-xs sf-text-muted">Esse valor e salvo de forma criptografada e nao fica visivel depois do cadastro.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('access_token')" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="sf-label">Refresh token</label>
                        <input type="password" name="refresh_token" value="{{ old('refresh_token') }}" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="sf-label">API key alternativa</label>
                        <input type="password" name="api_key" value="{{ old('api_key') }}" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="sf-label">Public key</label>
                        <input type="password" name="public_key" value="{{ old('public_key') }}" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="sf-label">Segredo do webhook</label>
                        <input type="password" name="webhook_secret" value="{{ old('webhook_secret') }}" class="sf-input mt-2 block w-full font-mono text-sm" autocomplete="new-password">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="sf-label">Identificador da conta</label>
                        <input type="text" name="account_identifier" value="{{ old('account_identifier') }}" class="sf-input mt-2 block w-full" placeholder="Opcional">
                    </div>
                    <div>
                        <label class="sf-label">Ambiente</label>
                        <select name="environment" class="sf-select mt-2 block w-full" required>
                            @foreach ($environments as $environment)
                                <option value="{{ $environment->value }}" @selected(old('environment', 'production') === $environment->value)>{{ $environment->value }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm sf-text-muted">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" class="rounded border-white/20" @checked(old('active', true))>
                        Ativar esta integracao
                    </label>
                    <label class="flex items-center gap-2 text-sm sf-text-muted">
                        <input type="hidden" name="default_for_memberships" value="0">
                        <input type="checkbox" name="default_for_memberships" value="1" class="rounded border-white/20" @checked(old('default_for_memberships'))>
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
                <p class="sf-page-eyebrow">Mercado Pago</p>
                <h3 class="sf-section-title mt-1 text-xl">Fluxo recomendado</h3>
                <p class="mt-3 text-sm leading-6 sf-text-muted">
                    O fluxo recomendado e conectar por OAuth. Assim a conta da empresa recebe direto e o StudioFlow faz a renovacao do acesso com menos friccao.
                </p>

                @if ($mercadoPagoOauthConfigured)
                    <a href="{{ route('company.payment-integrations.mercado-pago.connect') }}"
                        class="sf-button-primary mt-5 w-full justify-center text-sm"
                        target="_blank"
                        onclick="window.open(this.href, 'mercadoPagoOAuth', 'width=720,height=860,menubar=no,toolbar=no,location=yes,status=no,scrollbars=yes,resizable=yes'); return false;">
                        Conectar Mercado Pago
                    </a>
                @else
                    <div class="mt-5 rounded-2xl border border-amber-400/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                        O OAuth do Mercado Pago ainda nao foi configurado pelo administrador da plataforma.
                        <span class="mt-1 block opacity-90">Entre em contato com o suporte para habilitar a conexao automatica.</span>
                    </div>
                @endif
            </div>

            <div class="sf-card p-5">
                <p class="sf-page-eyebrow">Quando usar o modo manual</p>
                <ul class="mt-3 space-y-3 text-sm leading-6 sf-text-muted">
                    <li>• Recuperacao emergencial de credenciais.</li>
                    <li>• Ambientes homologados fora do fluxo principal.</li>
                    <li>• Ajustes tecnicos feitos por quem ja domina o gateway.</li>
                </ul>
            </div>
        </aside>
    </div>
</x-app-layout>
