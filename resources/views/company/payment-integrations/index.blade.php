<x-app-layout>
    <x-slot name="header">
        @php
            $mercadoPagoIntegration = $mercadoPagoIntegration;
            $oauthConfigured = $mercadoPagoOauthConfigured;
            $isConnected = $mercadoPagoIntegration?->isConnected() ?? false;
            $requiresReconnect = ($mercadoPagoIntegration?->status === 'error')
                || ($mercadoPagoIntegration?->status === 'disconnected' && filled($mercadoPagoIntegration?->access_token))
                || ($mercadoPagoIntegration && ! $mercadoPagoIntegration->active && filled($mercadoPagoIntegration->access_token));
            $isExpiringSoon = $isConnected && ($mercadoPagoIntegration?->tokenExpiresSoon() ?? false);
            $hasIntegration = $mercadoPagoIntegration !== null;
            $accountLabel = $mercadoPagoIntegration?->account_identifier
                ? \App\Models\CompanyPaymentIntegration::maskSecret((string) $mercadoPagoIntegration->account_identifier)
                : '—';
            $lastSyncRaw = data_get($mercadoPagoIntegration?->metadata, 'last_refresh_at')
                ?? data_get($mercadoPagoIntegration?->metadata, 'last_oauth_exchange_at');
            $lastSyncAt = null;

            if (is_string($lastSyncRaw) && $lastSyncRaw !== '') {
                try {
                    $lastSyncAt = \Illuminate\Support\Carbon::parse($lastSyncRaw);
                } catch (\Throwable $e) {
                    $lastSyncAt = null;
                }
            }

            if ($isConnected && $isExpiringSoon) {
                $statusLabel = 'Expiracao proxima';
                $statusDescription = 'A conexao esta ativa, mas vale reconectar ou deixar o refresh agir em breve.';
                $statusClass = 'border-amber-400/30 bg-amber-500/10 text-amber-100';
                $statusDot = 'bg-amber-300';
                $statusIcon = '!';
            } elseif ($isConnected) {
                $statusLabel = 'Conectado';
                $statusDescription = 'Sua empresa ja pode receber cobrancas diretamente na conta conectada.';
                $statusClass = 'border-emerald-400/30 bg-emerald-500/10 text-emerald-100';
                $statusDot = 'bg-emerald-300';
                $statusIcon = 'OK';
            } elseif ($requiresReconnect) {
                $statusLabel = 'Requer reconexao';
                $statusDescription = 'A conexao precisa ser renovada para continuar usando o Mercado Pago com seguranca.';
                $statusClass = 'border-amber-400/30 bg-amber-500/10 text-amber-100';
                $statusDot = 'bg-amber-300';
                $statusIcon = '!';
            } else {
                $statusLabel = 'Nao conectado';
                $statusDescription = 'Conecte a conta da empresa para cobrar sem depender de token manual.';
                $statusClass = 'border-white/15 bg-white/5 text-[var(--text-muted)]';
                $statusDot = 'bg-white/35';
                $statusIcon = 'MP';
            }

            $environmentLabel = $mercadoPagoIntegration?->environment?->value
                ? ucfirst((string) $mercadoPagoIntegration->environment->value)
                : '—';
        @endphp

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="sf-page-eyebrow">Empresa</p>
                <h2 class="sf-page-title mt-1">Integracoes de pagamento</h2>
                <p class="sf-page-subtitle mt-2 max-w-3xl">
                    Central premium para conectar gateways, acompanhar o estado da conta e manter o recebimento da sua empresa sob controle.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('company.edit') }}" class="sf-button-ghost text-sm">Voltar a empresa</a>
                <a href="{{ route('company.payment-integrations.create') }}" class="sf-button-secondary text-sm">Configuracao manual</a>
            </div>
        </div>
    </x-slot>

    @if (session('status') === 'payment-integration-created')
        <div class="mb-4 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">Integracao criada.</div>
    @elseif (session('status') === 'payment-integration-updated')
        <div class="mb-4 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">Integracao atualizada.</div>
    @elseif (session('status') === 'payment-integration-tested')
        <div class="mb-4 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">Conexao testada com sucesso.</div>
    @elseif (session('status') === 'payment-integration-toggled')
        <div class="mb-4 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">Status da integracao atualizado.</div>
    @elseif (session('status') === 'mercado-pago-connected')
        <div class="mb-4 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">Mercado Pago conectado com sucesso.</div>
    @elseif (session('status') === 'mercado-pago-disconnected')
        <div class="mb-4 rounded-2xl border border-amber-300/20 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">Conta Mercado Pago desconectada.</div>
    @endif

    @if ($errors->has('test'))
        <div class="mb-4 rounded-2xl border border-rose-400/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{{ $errors->first('test') }}</div>
    @endif
    @if ($errors->has('oauth'))
        <div class="mb-4 rounded-2xl border border-rose-400/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{{ $errors->first('oauth') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
        <section class="space-y-4">
            <div class="sf-card p-5 sm:p-6">
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)] lg:items-start">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-start gap-4">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-[color:var(--card-border)] bg-[var(--input-bg)] text-xl font-semibold text-[var(--brand-primary)] shadow-[var(--shadow-soft)]">
                                MP
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="sf-section-title text-xl sm:text-2xl">Mercado Pago</h3>
                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] sf-text-muted">
                                        <span class="h-2 w-2 rounded-full {{ $statusDot }}"></span>
                                        {{ $statusLabel }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="inline-flex items-center rounded-full border border-[color:var(--card-border)] bg-[var(--badge-bg)] px-3 py-1 text-xs font-semibold text-[var(--badge-text)]">
                                        OAuth seguro
                                    </span>
                                    <span class="inline-flex items-center rounded-full border border-[color:var(--card-border)] bg-white/5 px-3 py-1 text-xs font-semibold sf-text-muted">
                                        Recebimento direto na conta da empresa
                                    </span>
                                </div>

                                <p class="mt-4 max-w-2xl text-sm leading-6 sf-text-muted">
                                    Conecte sua conta Mercado Pago para receber diretamente dos seus clientes. O StudioFlow nao recebe esse dinheiro; ele apenas registra a cobranca e organiza o fluxo da sua operacao.
                                </p>
                            </div>
                        </div>

                        <div class="rounded-2xl border {{ $statusClass }} px-4 py-4 shadow-[var(--shadow-soft)]">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-current/20 bg-black/10 text-xs font-bold uppercase">
                                    {{ $statusIcon }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">{{ $statusLabel }}</p>
                                    <p class="mt-1 text-sm leading-6 opacity-95">{{ $statusDescription }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if ($oauthConfigured)
                                <a href="{{ route('company.payment-integrations.mercado-pago.connect') }}"
                                    class="sf-button-primary text-sm"
                                    target="_blank"
                                    onclick="window.open(this.href, 'mercadoPagoOAuth', 'width=720,height=860,menubar=no,toolbar=no,location=yes,status=no,scrollbars=yes,resizable=yes'); return false;">
                                    {{ $isConnected ? ($requiresReconnect || $isExpiringSoon ? 'Reconectar Mercado Pago' : 'Gerenciar conexao') : 'Conectar Mercado Pago' }}
                                </a>
                            @else
                                <button type="button" class="sf-button-primary cursor-not-allowed text-sm opacity-50" disabled>
                                    Conectar Mercado Pago
                                </button>
                            @endif

                            @if ($hasIntegration)
                                <a href="{{ route('company.payment-integrations.edit', $mercadoPagoIntegration) }}" class="sf-button-ghost text-sm">Abrir configuracao manual</a>
                            @else
                                <a href="{{ route('company.payment-integrations.create') }}" class="sf-button-ghost text-sm">Abrir configuracao manual</a>
                            @endif

                            @if ($hasIntegration && ($isConnected || $requiresReconnect))
                                <form method="POST" action="{{ route('company.payment-integrations.mercado-pago.disconnect') }}">
                                    @csrf
                                    <button type="submit" class="sf-button-secondary text-sm">Desconectar</button>
                                </form>
                            @endif
                        </div>

                        @unless ($oauthConfigured)
                            <div class="rounded-2xl border border-amber-400/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                                O OAuth do Mercado Pago ainda nao foi configurado pelo administrador da plataforma.
                                <span class="block mt-1 opacity-90">Entre em contato com o suporte para habilitar a conexao automatica.</span>
                            </div>
                        @endunless
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-2">
                        <div class="sf-card-soft p-4 hover:-translate-y-[1px]">
                            <p class="sf-label">Status</p>
                            <p class="mt-2 text-base font-semibold text-[var(--text-main)]">{{ $statusLabel }}</p>
                        </div>
                        <div class="sf-card-soft p-4 hover:-translate-y-[1px]">
                            <p class="sf-label">Conta conectada</p>
                            <p class="mt-2 text-sm font-semibold text-[var(--text-main)]">{{ $accountLabel }}</p>
                        </div>
                        <div class="sf-card-soft p-4 hover:-translate-y-[1px]">
                            <p class="sf-label">Conectado em</p>
                            <p class="mt-2 text-sm font-semibold text-[var(--text-main)]">{{ $mercadoPagoIntegration?->connected_at?->format('d/m/Y H:i') ?? '—' }}</p>
                        </div>
                        <div class="sf-card-soft p-4 hover:-translate-y-[1px]">
                            <p class="sf-label">Expira em</p>
                            <p class="mt-2 text-sm font-semibold text-[var(--text-main)]">{{ $mercadoPagoIntegration?->expires_at?->format('d/m/Y H:i') ?? '—' }}</p>
                        </div>
                        <div class="sf-card-soft p-4 hover:-translate-y-[1px]">
                            <p class="sf-label">Ambiente</p>
                            <p class="mt-2 text-sm font-semibold text-[var(--text-main)]">{{ $environmentLabel }}</p>
                        </div>
                        <div class="sf-card-soft p-4 hover:-translate-y-[1px]">
                            <p class="sf-label">Ultima sincronizacao</p>
                            <p class="mt-2 text-sm font-semibold text-[var(--text-main)]">{{ $lastSyncAt?->format('d/m/Y H:i') ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            @if ($integrations->isEmpty())
                <div class="sf-card p-6 sm:p-7">
                    <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-[color:var(--card-border)] bg-[var(--input-bg)] text-lg font-semibold text-[var(--brand-primary)] shadow-[var(--shadow-soft)]">
                                +
                            </div>
                            <div>
                                <h3 class="sf-section-title text-xl">Nenhuma integracao cadastrada</h3>
                                <p class="mt-2 max-w-2xl text-sm leading-6 sf-text-muted">
                                    Conecte o Mercado Pago para comecar a receber pagamentos diretamente na conta da sua empresa e manter o fluxo de cobranca mais simples.
                                </p>
                            </div>
                        </div>

                        @if ($oauthConfigured)
                            <a href="{{ route('company.payment-integrations.mercado-pago.connect') }}"
                                class="sf-button-primary text-sm"
                                target="_blank"
                                onclick="window.open(this.href, 'mercadoPagoOAuth', 'width=720,height=860,menubar=no,toolbar=no,location=yes,status=no,scrollbars=yes,resizable=yes'); return false;">
                                Conectar Mercado Pago
                            </a>
                        @else
                            <a href="{{ route('company.payment-integrations.create') }}" class="sf-button-secondary text-sm">
                                Abrir configuracao manual
                            </a>
                        @endif
                    </div>
                </div>
            @else
                <div class="sf-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                        <div>
                            <p class="sf-page-eyebrow">Integracoes cadastradas</p>
                            <h3 class="sf-section-title mt-1">Panorama da empresa</h3>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold sf-text-muted">
                            {{ $integrations->count() }} {{ \Illuminate\Support\Str::plural('integracao', $integrations->count()) }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10 text-sm">
                            <thead class="bg-[var(--input-bg)]">
                                <tr>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase sf-text-muted">Gateway</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase sf-text-muted">Nome</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase sf-text-muted">Ambiente</th>
                                    <th class="px-5 py-4 text-center text-xs font-semibold uppercase sf-text-muted">Padrao</th>
                                    <th class="px-5 py-4 text-center text-xs font-semibold uppercase sf-text-muted">Ativo</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase sf-text-muted">Status</th>
                                    <th class="px-5 py-4 text-right text-xs font-semibold uppercase sf-text-muted"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @foreach ($integrations as $integration)
                                    @php
                                        $configured = filled($integration->access_token) || filled($integration->api_key);
                                        $integrationStatus = $integration->status ?: ($configured && $integration->active ? 'connected' : 'pending');
                                    @endphp
                                    <tr class="transition hover:bg-white/5">
                                        <td class="px-5 py-4 font-medium text-[var(--text-main)]">{{ strtoupper(str_replace('_', ' ', $integration->provider->value)) }}</td>
                                        <td class="px-5 py-4 sf-text-muted">{{ $integration->name ?? '—' }}</td>
                                        <td class="px-5 py-4 sf-text-muted">{{ $integration->environment->value }}</td>
                                        <td class="px-5 py-4 text-center sf-text-muted">{{ $integration->default_for_memberships ? 'Sim' : 'Nao' }}</td>
                                        <td class="px-5 py-4 text-center sf-text-muted">{{ $integration->active ? 'Sim' : 'Nao' }}</td>
                                        <td class="px-5 py-4">
                                            @if ($integrationStatus === 'connected')
                                                <span class="inline-flex rounded-full border border-emerald-400/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-100">Conectado</span>
                                            @elseif ($integrationStatus === 'error')
                                                <span class="inline-flex rounded-full border border-rose-400/30 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-100">Erro</span>
                                            @elseif ($configured && ! $integration->active)
                                                <span class="inline-flex rounded-full border border-amber-400/30 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-100">Inativo</span>
                                            @else
                                                <span class="inline-flex rounded-full border border-white/15 bg-white/5 px-2.5 py-1 text-xs font-semibold sf-text-muted">Pendente</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 text-right text-xs">
                                            <a href="{{ route('company.payment-integrations.edit', $integration) }}" class="brand-text hover:underline">Editar</a>
                                            <span class="mx-1 text-white/30">·</span>
                                            <form method="POST" action="{{ route('company.payment-integrations.test', $integration) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="brand-text hover:underline">Testar</button>
                                            </form>
                                            <span class="mx-1 text-white/30">·</span>
                                            <form method="POST" action="{{ route('company.payment-integrations.toggle', $integration) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="brand-text hover:underline">{{ $integration->active ? 'Desativar' : 'Ativar' }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        <aside class="space-y-4">
            <div class="sf-card p-5">
                <p class="sf-page-eyebrow">Fluxo recomendado</p>
                <div class="mt-4 space-y-3">
                    <div class="flex gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--badge-bg)] text-xs font-bold text-[var(--badge-text)]">1</div>
                        <div>
                            <p class="text-sm font-semibold text-[var(--text-main)]">Inicie a conexao</p>
                            <p class="mt-1 text-sm leading-6 sf-text-muted">Clique em conectar para autorizar o StudioFlow na conta Mercado Pago da empresa.</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--badge-bg)] text-xs font-bold text-[var(--badge-text)]">2</div>
                        <div>
                            <p class="text-sm font-semibold text-[var(--text-main)]">Autorize com seguranca</p>
                            <p class="mt-1 text-sm leading-6 sf-text-muted">A empresa confirma o acesso uma unica vez, sem precisar colar token manual.</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--badge-bg)] text-xs font-bold text-[var(--badge-text)]">3</div>
                        <div>
                            <p class="text-sm font-semibold text-[var(--text-main)]">Receba na conta correta</p>
                            <p class="mt-1 text-sm leading-6 sf-text-muted">As cobrancas passam a usar a conta conectada da empresa, sem misturar com o StudioFlow.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sf-card p-5">
                <p class="sf-page-eyebrow">Configuracao manual avancada</p>
                <p class="mt-3 text-sm leading-6 sf-text-muted">
                    Use somente se souber exatamente o que esta fazendo. O recomendado e conectar pelo botao Mercado Pago.
                </p>
                <div class="mt-4 flex flex-col gap-2">
                    <a href="{{ $hasIntegration ? route('company.payment-integrations.edit', $mercadoPagoIntegration) : route('company.payment-integrations.create') }}"
                        class="sf-button-ghost w-full justify-center text-sm">
                        Abrir configuracao manual
                    </a>
                    <p class="text-xs sf-text-muted">
                        O modo manual continua disponivel como fallback, sem expor segredos na tela principal.
                    </p>
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
