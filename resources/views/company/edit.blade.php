@php
    $brandPresets = [
        ['label' => 'Luxo Dourado', 'primary' => '#C9A227', 'secondary' => '#1A1A1A', 'accent' => '#0D0D0D'],
        ['label' => 'Classic Barber', 'primary' => '#8B0000', 'secondary' => '#F5F1E8', 'accent' => '#FFFFFF'],
        ['label' => 'Neon Night', 'primary' => '#00F5D4', 'secondary' => '#09090B', 'accent' => '#18181B'],
        ['label' => 'Minimal Clean', 'primary' => '#111827', 'secondary' => '#F9FAFB', 'accent' => '#FFFFFF'],
        ['label' => 'Royal Blue', 'primary' => '#2563EB', 'secondary' => '#F8FAFC', 'accent' => '#FFFFFF'],
        ['label' => 'Red Premium', 'primary' => '#750006', 'secondary' => '#FAFAFA', 'accent' => '#FFFFFF'],
        ['label' => 'Dark Gold', 'primary' => '#D4AF37', 'secondary' => '#050505', 'accent' => '#141414'],
        ['label' => 'Forest', 'primary' => '#166534', 'secondary' => '#F7FAF7', 'accent' => '#FFFFFF'],
    ];

    $brandingConfig = [
        'previewUrl' => route('company.branding-preview'),
        'csrfToken' => csrf_token(),
        'brandPrimary' => $formValues['primary_color'] ?? '',
        'brandSecondary' => $formValues['secondary_color'] ?? '',
        'brandAccent' => $formValues['accent_color'] ?? '',
        'brandEnabled' => (bool) ($formValues['brand_enabled'] ?? true),
        'previewStyleVars' => $brandingPreviewVars ?? [],
        'defaults' => [
            'primary' => \App\Services\BrandingService::DEFAULT_PRIMARY,
            'secondary' => \App\Services\BrandingService::DEFAULT_SECONDARY,
            'accent' => \App\Services\BrandingService::DEFAULT_ACCENT,
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="sf-page-eyebrow">{{ $isOnboarding ? 'ONBOARDING' : 'EMPRESA' }}</p>
                <h2 class="sf-page-title mt-2">
                    {{ $isOnboarding ? 'Vamos configurar sua empresa' : 'Dados da empresa' }}
                </h2>
                <p class="sf-page-subtitle mt-3 max-w-2xl">
                    {{ $isOnboarding ? 'Preencha os dados principais para deixar seu StudioFlow com a cara da sua marca.' : 'Personalize nome, logo e informacoes da sua operacao.' }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @unless($isOnboarding)
                    <a href="{{ route('dashboard') }}" class="sf-button-ghost">
                        Voltar
                    </a>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('company.payment-integrations.index') }}" class="sf-button-secondary text-sm">Integracoes de pagamento</a>
                    @endif
                @endunless
                <button type="submit" form="company-edit-form" class="brand-cta">
                    {{ $isOnboarding ? 'Concluir configuracao' : 'Salvar empresa' }}
                </button>
            </div>
        </div>
    </x-slot>

    <div x-data="companyBranding(window.companyBrandingConfig)" x-init="init()" class="company-branding-shell grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="sf-card p-5 sm:p-6">
            @if (session('status') === 'company-updated')
                <div class="mb-5 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                    Dados da empresa atualizados com sucesso.
                </div>
            @elseif (session('status') === 'company-onboarded')
                <div class="mb-5 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                    Sua empresa foi configurada com sucesso.
                </div>
            @endif

            <form id="company-edit-form" method="POST" action="{{ route('company.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                    <div class="flex items-center gap-4">
                        @if ($company->logo_url)
                            <img src="{{ $company->logo_url }}" alt="Logo de {{ $company->name }}" class="h-20 w-20 rounded-2xl object-cover ring-1 ring-white/10">
                        @else
                            <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-2xl font-semibold brand-text">
                                {{ strtoupper(substr($formValues['name'] ?: $company->name, 0, 1)) }}
                            </div>
                        @endif

                        <div>
                            <p class="sf-section-title">Logo da empresa</p>
                            <p class="mt-1 text-sm sf-muted">Envie JPG, JPEG, PNG ou WEBP com ate 2MB.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="logo" class="text-sm font-medium text-[var(--text-main)]">Upload da logo</label>
                        <input
                            id="logo"
                            name="logo"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="sf-input mt-2 block w-full px-3 py-3"
                            @change="updateFileLabel($event, 'logo-file-name')"
                        >
                        <p id="logo-file-name" class="mt-2 hidden text-xs sf-text-muted"></p>
                        <x-input-error class="mt-2" :messages="$errors->get('logo')" />
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="lg:col-span-2">
                        <label for="name" class="text-sm font-medium text-[var(--text-main)]">Nome da empresa</label>
                        <input id="name" name="name" type="text" value="{{ $formValues['name'] }}" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()" required>
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <label for="phone" class="text-sm font-medium text-[var(--text-main)]">Telefone</label>
                        <input id="phone" name="phone" type="text" value="{{ $formValues['phone'] }}" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()">
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>

                    <div>
                        <label for="cnpj" class="text-sm font-medium text-[var(--text-main)]">CNPJ</label>
                        <input id="cnpj" name="cnpj" type="text" value="{{ $formValues['cnpj'] }}" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()">
                        <x-input-error class="mt-2" :messages="$errors->get('cnpj')" />
                    </div>

                    <div class="lg:col-span-2">
                        <label for="instagram" class="text-sm font-medium text-[var(--text-main)]">Instagram</label>
                        <input id="instagram" name="instagram" type="text" value="{{ $formValues['instagram'] }}" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()">
                        <x-input-error class="mt-2" :messages="$errors->get('instagram')" />
                    </div>

                    <div class="lg:col-span-2">
                        <label for="address" class="text-sm font-medium text-[var(--text-main)]">Endereco</label>
                        <textarea id="address" name="address" rows="3" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()">{{ $formValues['address'] }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('address')" />
                    </div>

                    <div class="lg:col-span-2">
                        <label for="description" class="text-sm font-medium text-[var(--text-main)]">Descricao curta</label>
                        <textarea id="description" name="description" rows="3" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()">{{ $formValues['description'] }}</textarea>
                        <p class="mt-2 text-xs sf-muted">Essa frase pode aparecer na navegacao e na pagina publica de agendamento.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('description')" />
                    </div>

                    <div class="lg:col-span-2 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <p class="sf-page-eyebrow">Marca publica</p>
                        <p class="sf-page-subtitle mt-2">Ajuste as cores pelo seletor visual ou pelo codigo HEX. Deixe em branco para usar o tema StudioFlow nos campos de cor.</p>

                        <div class="mt-5">
                            <p class="sf-label sf-muted">Temas rapidos</p>
                            <p class="mt-1 text-xs sf-muted/80">Aplica a paleta na pre-visualizacao; salve quando estiver satisfeito.</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ($brandPresets as $preset)
                                    <button
                                        type="button"
                                        class="group rounded-2xl border px-3 py-3 text-left text-xs font-semibold sf-text transition hover:bg-[color-mix(in_srgb,var(--text-main)_6%,transparent)]"
                                        :class="presetMatches({ primary: '{{ $preset['primary'] }}', secondary: '{{ $preset['secondary'] }}', accent: '{{ $preset['accent'] }}' }) ? 'brand-preset-active' : 'border-[color-mix(in_srgb,var(--text-main)_12%,transparent)] bg-[var(--card-soft-bg)]'"
                                        @click="applyPreset({ primary: '{{ $preset['primary'] }}', secondary: '{{ $preset['secondary'] }}', accent: '{{ $preset['accent'] }}' })"
                                    >
                                        <span class="flex items-center justify-between gap-3">
                                            <span class="block">
                                                <span class="block text-sm font-semibold sf-text">{{ $preset['label'] }}</span>
                                                <span class="mt-1 block text-[11px] font-medium sf-muted">{{ $preset['primary'] }} · {{ $preset['secondary'] }} · {{ $preset['accent'] }}</span>
                                            </span>
                                            <span class="flex items-center gap-1.5">
                                                <span class="h-4 w-4 rounded-full border border-black/10 shadow-sm" style="background-color: {{ $preset['primary'] }}"></span>
                                                <span class="h-4 w-4 rounded-full border border-black/10 shadow-sm" style="background-color: {{ $preset['secondary'] }}"></span>
                                                <span class="h-4 w-4 rounded-full border border-black/10 shadow-sm" style="background-color: {{ $preset['accent'] }}"></span>
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-6 space-y-5">
                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <label for="primary_color_hex" class="text-sm font-medium text-[var(--text-main)]">Cor primaria</label>
                                    <button type="button" class="text-xs font-semibold text-[var(--brand-primary)] hover:underline" @click="resetColor('primary')">Resetar</button>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                    <input
                                        type="color"
                                        class="sf-color-picker"
                                        aria-label="Seletor cor primaria"
                                        :value="pickerHex(brandPrimary, window.companyBrandingConfig.defaults.primary)"
                                        @input="onPickerInput('primary', $event)"
                                    >
                                    <input
                                        id="primary_color_hex"
                                        name="primary_color"
                                        type="text"
                                        class="sf-input min-h-[2.75rem] min-w-0 flex-1 font-mono text-sm"
                                        placeholder="#D4AF37"
                                        maxlength="7"
                                        :value="brandPrimary"
                                        @input="onHexInput('primary', $event)"
                                    >
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('primary_color')" />
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <label for="secondary_color_hex" class="text-sm font-medium text-[var(--text-main)]">Cor secundaria</label>
                                    <button type="button" class="text-xs font-semibold text-[var(--brand-primary)] hover:underline" @click="resetColor('secondary')">Resetar</button>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                    <input
                                        type="color"
                                        class="sf-color-picker"
                                        aria-label="Seletor cor secundaria"
                                        :value="pickerHex(brandSecondary, window.companyBrandingConfig.defaults.secondary)"
                                        @input="onPickerInput('secondary', $event)"
                                    >
                                    <input
                                        id="secondary_color_hex"
                                        name="secondary_color"
                                        type="text"
                                        class="sf-input min-h-[2.75rem] min-w-0 flex-1 font-mono text-sm"
                                        placeholder="#223D69"
                                        maxlength="7"
                                        :value="brandSecondary"
                                        @input="onHexInput('secondary', $event)"
                                    >
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('secondary_color')" />
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <label for="accent_color_hex" class="text-sm font-medium text-[var(--text-main)]">Cor de destaque</label>
                                    <button type="button" class="text-xs font-semibold text-[var(--brand-primary)] hover:underline" @click="resetColor('accent')">Resetar</button>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                    <input
                                        type="color"
                                        class="sf-color-picker"
                                        aria-label="Seletor cor de destaque"
                                        :value="pickerHex(brandAccent, window.companyBrandingConfig.defaults.accent)"
                                        @input="onPickerInput('accent', $event)"
                                    >
                                    <input
                                        id="accent_color_hex"
                                        name="accent_color"
                                        type="text"
                                        class="sf-input min-h-[2.75rem] min-w-0 flex-1 font-mono text-sm"
                                        placeholder="#132746"
                                        maxlength="7"
                                        :value="brandAccent"
                                        @input="onHexInput('accent', $event)"
                                    >
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('accent_color')" />
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] pt-4">
                                <p class="text-xs sf-muted">Limpe as cores ou restaure o tema StudioFlow. Salve para aplicar no sistema.</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="sf-button-ghost text-xs" @click="resetColor('all')">Limpar todas</button>
                                    <button type="button" class="sf-button-secondary text-xs" @click="restoreStudioflowTheme()">Restaurar tema StudioFlow</button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div class="lg:col-span-2">
                                <label for="public_headline" class="text-sm font-medium text-[var(--text-main)]">Frase principal (publico)</label>
                                <input id="public_headline" name="public_headline" type="text" value="{{ $formValues['public_headline'] }}" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()" maxlength="255">
                                <x-input-error class="mt-2" :messages="$errors->get('public_headline')" />
                            </div>

                            <div class="lg:col-span-2">
                                <label for="public_subheadline" class="text-sm font-medium text-[var(--text-main)]">Subtitulo (publico)</label>
                                <input id="public_subheadline" name="public_subheadline" type="text" value="{{ $formValues['public_subheadline'] }}" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()" maxlength="500">
                                <x-input-error class="mt-2" :messages="$errors->get('public_subheadline')" />
                            </div>

                            <div class="lg:col-span-2">
                                <label for="welcome_message" class="text-sm font-medium text-[var(--text-main)]">Mensagem de boas-vindas</label>
                                <textarea id="welcome_message" name="welcome_message" rows="3" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()">{{ $formValues['welcome_message'] }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('welcome_message')" />
                            </div>

                            <div class="lg:col-span-2">
                                <label for="custom_footer_text" class="text-sm font-medium text-[var(--text-main)]">Rodape personalizado (publico)</label>
                                <input id="custom_footer_text" name="custom_footer_text" type="text" value="{{ $formValues['custom_footer_text'] }}" class="sf-input mt-2 block w-full" @input="refreshPreviewContent()" maxlength="500">
                                <x-input-error class="mt-2" :messages="$errors->get('custom_footer_text')" />
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="favicon" class="text-sm font-medium text-[var(--text-main)]">Favicon</label>
                                <input id="favicon" name="favicon" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="sf-input mt-2 block w-full px-3 py-3" @change="updateFileLabel($event, 'favicon-file-name')">
                                <p id="favicon-file-name" class="mt-2 hidden text-xs sf-text-muted"></p>
                                <x-input-error class="mt-2" :messages="$errors->get('favicon')" />
                            </div>

                            <div>
                                <label for="cover_image" class="text-sm font-medium text-[var(--text-main)]">Imagem de capa (publico)</label>
                                <input id="cover_image" name="cover_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="sf-input mt-2 block w-full px-3 py-3" @change="updateFileLabel($event, 'cover-file-name')">
                                <p id="cover-file-name" class="mt-2 hidden text-xs sf-text-muted"></p>
                                <x-input-error class="mt-2" :messages="$errors->get('cover_image')" />
                            </div>
                        </div>

                        <label class="mt-4 flex cursor-pointer items-start gap-3 text-sm sf-text-muted">
                            <input type="hidden" name="brand_enabled" value="0">
                            <input type="checkbox" name="brand_enabled" value="1" class="mt-1 rounded border-[color-mix(in_srgb,var(--text-main)_14%,transparent)] bg-[var(--input-bg)] text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]" x-model="brandEnabled" @change="schedulePreview()">
                            <span>Usar cores e textos personalizados na area interna e publica. Desmarque para voltar ao tema StudioFlow nas cores.</span>
                        </label>
                    </div>

                    <div class="lg:col-span-2 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <p class="sf-page-eyebrow">PDV</p>
                        <label class="mt-3 flex cursor-pointer items-start gap-3 text-sm sf-text-muted">
                            <input type="hidden" name="auto_print_receipt" value="0">
                            <input
                                type="checkbox"
                                name="auto_print_receipt"
                                value="1"
                                class="mt-1 rounded border-white/20 bg-[var(--app-shell-bg)] text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                                @checked($formValues['auto_print_receipt'])
                            >
                            <span>Abrir comprovante automaticamente apos cada venda no PDV (nova aba).</span>
                        </label>
                        <p class="mt-2 text-xs sf-muted/80">Requer permissao de pop-up no navegador. O botao manual de impressao continua disponivel.</p>
                    </div>

                    <div class="lg:col-span-2 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-4">
                        <p class="sf-page-eyebrow">Pagamentos no agendamento</p>
                        <p class="mt-2 text-sm sf-muted">Defina se o cliente precisa pagar um sinal ou o valor total para reservar horarios online.</p>

                        <div class="mt-5 grid gap-4 lg:grid-cols-2">
                            <div class="lg:col-span-2">
                                <label class="flex cursor-pointer items-start gap-3 text-sm sf-text-muted">
                                    <input type="hidden" name="online_booking_payment_enabled" value="0">
                                    <input
                                        type="checkbox"
                                        name="online_booking_payment_enabled"
                                        value="1"
                                        class="mt-1 rounded border-white/20 bg-[var(--app-shell-bg)] text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                                        @checked($formValues['online_booking_payment_enabled'])
                                    >
                                    <span>Ativar pagamento online no agendamento publico usando a conta Mercado Pago conectada da empresa.</span>
                                </label>
                            </div>

                            <div>
                                <label for="booking_payment_requirement" class="text-sm font-medium text-[var(--text-main)]">Quando cobrar online</label>
                                <select id="booking_payment_requirement" name="booking_payment_requirement" class="sf-select mt-2 block w-full">
                                    <option value="disabled" @selected($formValues['booking_payment_requirement'] === 'disabled')>Desativado</option>
                                    <option value="optional" @selected($formValues['booking_payment_requirement'] === 'optional')>Cliente escolhe pagar agora ou no local</option>
                                    <option value="required" @selected($formValues['booking_payment_requirement'] === 'required')>Pagamento online obrigatorio</option>
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('booking_payment_requirement')" />
                            </div>

                            <div>
                                <label for="booking_payment_mode" class="text-sm font-medium text-[var(--text-main)]">Modo de cobranca</label>
                                <select id="booking_payment_mode" name="booking_payment_mode" class="sf-select mt-2 block w-full">
                                    <option value="none" @selected($formValues['booking_payment_mode'] === 'none')>Desativado</option>
                                    <option value="deposit" @selected($formValues['booking_payment_mode'] === 'deposit')>Cobrar sinal para reservar</option>
                                    <option value="full" @selected($formValues['booking_payment_mode'] === 'full')>Cobrar valor total antecipado</option>
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('booking_payment_mode')" />
                            </div>

                            <div>
                                <label for="booking_payment_expiration_minutes" class="text-sm font-medium text-[var(--text-main)]">Prazo do pagamento</label>
                                <input
                                    id="booking_payment_expiration_minutes"
                                    name="booking_payment_expiration_minutes"
                                    type="number"
                                    min="5"
                                    max="180"
                                    value="{{ $formValues['booking_payment_expiration_minutes'] }}"
                                    class="sf-input mt-2 block w-full"
                                >
                                <p class="mt-2 text-xs sf-muted">Em minutos. O horario pode ser liberado apos esse prazo.</p>
                                <x-input-error class="mt-2" :messages="$errors->get('booking_payment_expiration_minutes')" />
                            </div>

                            <div>
                                <label for="booking_deposit_type" class="text-sm font-medium text-[var(--text-main)]">Tipo do sinal</label>
                                <select id="booking_deposit_type" name="booking_deposit_type" class="sf-select mt-2 block w-full">
                                    <option value="fixed" @selected($formValues['booking_deposit_type'] === 'fixed')>Valor fixo (R$)</option>
                                    <option value="percentage" @selected($formValues['booking_deposit_type'] === 'percentage')>Percentual do servico</option>
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('booking_deposit_type')" />
                            </div>

                            <div>
                                <label for="booking_deposit_value" class="text-sm font-medium text-[var(--text-main)]">Valor do sinal</label>
                                <input
                                    id="booking_deposit_value"
                                    name="booking_deposit_value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value="{{ $formValues['booking_deposit_value'] }}"
                                    class="sf-input mt-2 block w-full"
                                >
                                <p class="mt-2 text-xs sf-muted">Se usar percentual, informe apenas o numero. Ex.: 30 para 30%.</p>
                                <x-input-error class="mt-2" :messages="$errors->get('booking_deposit_value')" />
                            </div>

                            <div class="lg:col-span-2">
                                <label class="flex cursor-pointer items-start gap-3 text-sm sf-text-muted">
                                    <input type="hidden" name="booking_auto_cancel_unpaid" value="0">
                                    <input
                                        type="checkbox"
                                        name="booking_auto_cancel_unpaid"
                                        value="1"
                                        class="mt-1 rounded border-white/20 bg-[var(--app-shell-bg)] text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                                        @checked($formValues['booking_auto_cancel_unpaid'])
                                    >
                                    <span>Cancelar automaticamente o agendamento se o pagamento nao for confirmado dentro do prazo.</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <aside class="space-y-6">
            <div class="space-y-6" :style="previewStyleVars">
                <section class="sf-card overflow-hidden">
                    <div class="border-b border-white/10 px-5 py-5">
                        <p class="sf-page-eyebrow">Preview</p>
                        <h3 class="sf-page-title mt-2 text-2xl">Como sua marca aparece</h3>
                        <p class="sf-page-subtitle mt-2">Atualiza ao vivo e mostra o shell interno, componentes e a pagina publica com a sua marca.</p>
                    </div>

                    <div class="space-y-4 px-5 py-5">
                        <div class="preview-brand-shell overflow-hidden px-3 py-3">
                            <div class="grid grid-cols-[84px_minmax(0,1fr)] gap-3">
                                <div class="space-y-2 rounded-2xl bg-[var(--sidebar-bg)] px-2 py-3">
                                    <div class="mx-auto h-8 w-8 rounded-xl bg-[var(--sidebar-card-bg)] ring-1 ring-[color-mix(in_srgb,var(--text-main)_10%,transparent)]"></div>
                                    <div class="rounded-xl border border-[var(--active-menu-border)] bg-[var(--active-menu-bg)] px-2 py-2 text-center text-[10px] font-semibold text-[var(--active-menu-text)]">Painel</div>
                                    <div class="rounded-xl bg-[color-mix(in_srgb,var(--text-main)_5%,transparent)] px-2 py-2 text-center text-[10px] sf-muted">Agenda</div>
                                </div>
                                <div class="space-y-3 rounded-2xl bg-[var(--app-shell-bg)] px-3 py-3">
                                    <div class="flex items-center justify-between rounded-2xl bg-[var(--topbar-bg)] px-3 py-2 shadow-[var(--shadow-soft)]">
                                        <div>
                                            <p class="text-[10px] font-semibold tracking-[0.18em] text-[var(--brand-primary)]">TOPO</p>
                                            <p class="mt-1 text-xs font-semibold text-[var(--text-main)]" x-text="fieldValue('name', 'Sua empresa')"></p>
                                        </div>
                                        <div class="preview-brand-shell__mini-badge px-2 py-1 text-[10px] font-semibold">VIP</div>
                                    </div>
                                    <div class="preview-brand-shell__mini-card px-3 py-3">
                                        <p class="text-[10px] font-semibold tracking-[0.18em] text-[var(--brand-primary)]">CARD</p>
                                        <p class="mt-1 text-sm font-semibold text-[var(--text-main)]" x-text="fieldValue('public_headline', fieldValue('name', 'Headline'))"></p>
                                        <p class="mt-1 text-[11px] leading-relaxed text-[var(--text-muted)]" x-text="fieldValue('description', 'Descricao curta da empresa.')"></p>
                                    </div>
                                    <div class="preview-brand-shell__mini-input px-3 py-3 text-xs text-[var(--text-muted)]">Input com foco e contraste</div>
                                    <button type="button" class="brand-cta w-full text-xs" tabindex="-1">Botao principal</button>
                                </div>
                            </div>
                        </div>
                        <div class="preview-brand-card px-4 py-4">
                            <p class="sf-label">Empresa</p>
                            <p class="mt-2 text-lg font-semibold sf-text" x-text="fieldValue('name', 'Sua empresa completa')"></p>
                        </div>

                        <div class="preview-brand-card px-4 py-4">
                            <p class="sf-label">Contato</p>
                            <p class="mt-2 text-sm font-medium sf-text" x-text="fieldValue('phone', 'Telefone nao informado')"></p>
                            <p class="mt-2 text-sm brand-muted" x-text="fieldValue('address', 'Endereco ainda nao preenchido')"></p>
                        </div>

                        <div class="preview-brand-card px-4 py-4">
                            <p class="sf-label">Documento</p>
                            <p class="mt-2 text-sm font-medium sf-text" x-text="fieldValue('cnpj', 'CNPJ nao informado')"></p>
                        </div>

                        <div class="preview-brand-card px-4 py-4">
                            <p class="sf-label">Marca</p>
                            <p class="mt-2 text-sm font-medium sf-text" x-text="fieldValue('instagram', 'Instagram nao informado')"></p>
                            <p class="mt-2 text-sm brand-muted" x-text="fieldValue('description', 'Descricao curta ainda nao preenchida.')"></p>
                        </div>

                        <div class="preview-brand-card px-4 py-4">
                            <p class="sf-label">Simulacao · pagina publica</p>
                            <p class="mt-2 text-xs font-semibold uppercase tracking-[0.14em] text-[var(--text-muted)]">Painel interno</p>
                            <div class="mt-2 flex overflow-hidden rounded-xl border border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] text-[0.7rem] leading-tight">
                                <div class="w-16 shrink-0 space-y-2 bg-[var(--sidebar-bg)] px-2 py-3 text-[var(--text-muted)]">
                                    <div class="mx-auto h-6 w-6 rounded-lg bg-[var(--sidebar-card-bg)] ring-1 ring-[color-mix(in_srgb,var(--text-main)_8%,transparent)]"></div>
                                    <div class="rounded-md border border-[color-mix(in_srgb,var(--brand-primary)_30%,transparent)] bg-[var(--active-menu-bg)] px-1 py-1 text-center font-semibold text-[var(--active-menu-text)]">Painel</div>
                                </div>
                                <div class="min-w-0 flex-1 space-y-2 bg-[var(--app-shell-bg)] px-3 py-3 text-[var(--text-main)]">
                                    <div class="h-2 max-w-[8rem] rounded bg-[var(--card-border)]"></div>
                                    <div class="rounded-lg border border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--card-bg)] px-2 py-2 text-[var(--text-on-card)]">Cartao</div>
                                    <button type="button" class="w-full rounded-lg border border-[var(--btn-primary-border)] bg-[var(--btn-primary-bg)] py-1.5 text-center font-semibold text-[var(--btn-primary-text)]" tabindex="-1">Botao</button>
                                </div>
                            </div>
                            <div class="company-preview-landing mt-3 overflow-hidden">
                                <div class="px-4 py-3">
                                    <p class="text-xs font-semibold text-[var(--brand-primary)]">Agendamento · <span x-text="fieldValue('name', 'Empresa')"></span></p>
                                    <p class="mt-1 text-base font-semibold sf-text" x-text="fieldValue('public_headline', fieldValue('name', 'Titulo principal'))"></p>
                                    <p class="mt-1 text-xs brand-muted" x-text="fieldValue('public_subheadline', fieldValue('description', 'Subtitulo ou descricao curta'))"></p>
                                </div>
                                <div class="company-preview-landing__footer px-4 py-3">
                                    <button type="button" class="brand-cta w-full text-sm" tabindex="-1">Agendar horario</button>
                                </div>
                            </div>
                            <p class="mt-2 text-xs brand-muted">Atualiza ao editar os campos, antes de salvar.</p>
                        </div>

                        <div class="preview-brand-card px-4 py-4">
                            <p class="sf-label">Link publico</p>
                            <p class="mt-2 break-all text-sm font-medium sf-text">{{ route('public-bookings.create', $company) }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </aside>
    </div>

    <script>
        window.companyBrandingConfig = @js($brandingConfig);
    </script>
</x-app-layout>
