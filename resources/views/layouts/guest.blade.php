<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ ($tenantThemeLight ?? false) ? 'light' : 'dark' }}" style="{{ $tenantBranding['root_style'] ?? '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>
            @if (! empty($tenantBranding['company_name']) && $tenantBranding['company_name'] !== 'StudioFlow')
                {{ $tenantBranding['company_name'] }} · StudioFlow
            @else
                StudioFlow
            @endif
        </title>

        @if (! empty($tenantFaviconHref))
            <link rel="icon" href="{{ $tenantFaviconHref }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="app-chrome-body font-sans antialiased">
        <div class="min-h-screen bg-[var(--app-shell-bg)] bg-[radial-gradient(circle_at_top,_color-mix(in_srgb,var(--brand-primary)_18%,transparent),_transparent_30%),linear-gradient(180deg,_var(--brand-secondary)_0%,_var(--app-shell-bg)_38%,_var(--brand-accent)_100%)]">
            <div class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-4 py-8 sm:px-6 lg:px-8">
                <div class="grid items-center gap-8 lg:grid-cols-[minmax(0,1fr)_440px]">
                    <section class="hidden lg:block">
                        <div class="max-w-xl">
                            <div class="inline-flex items-center gap-3 rounded-full border border-white/10 bg-[color-mix(in_srgb,var(--brand-accent)_88%,black)] px-4 py-2 text-sm sf-text-muted shadow-[0_18px_38px_rgba(0,0,0,0.14)]">
                                @if (! empty($tenantBranding['logo_url']))
                                    <img src="{{ $tenantBranding['logo_url'] }}" alt="" class="h-8 w-8 rounded-lg object-cover ring-1 ring-white/10" loading="lazy" decoding="async">
                                @else
                                    <x-application-logo class="h-8 w-8 brand-text" />
                                @endif
                                <span class="font-semibold">{{ $tenantBranding['company_name'] }}</span>
                            </div>

                            <h1 class="sf-page-title mt-6 text-[var(--text-main)]">
                                {{ $tenantBranding['hero_title'] ?? 'Agenda inteligente para barbearias, salões e estética' }}
                            </h1>

                            <p class="sf-page-subtitle mt-4 max-w-lg">
                                {{ $tenantBranding['hero_subtitle'] ?? 'Organize atendimentos, acompanhe clientes e compartilhe seu link público de agendamento em uma experiência elegante e profissional.' }}
                            </p>

                            @if (! empty($tenantBranding['welcome_message']))
                                <p class="mt-4 max-w-lg rounded-2xl border border-white/10 bg-[color-mix(in_srgb,var(--brand-accent)_70%,transparent)] px-4 py-3 text-sm leading-relaxed sf-text-muted">
                                    {{ $tenantBranding['welcome_message'] }}
                                </p>
                            @endif

                            <div class="mt-8 grid max-w-lg gap-4 sm:grid-cols-2">
                                <div class="rounded-[24px] border border-white/8 bg-[color-mix(in_srgb,var(--brand-secondary)_92%,black)] px-5 py-4 shadow-[var(--shadow-card)]">
                                    <p class="sf-page-eyebrow">Operação</p>
                                    <p class="mt-2 text-lg font-semibold text-[var(--text-main)]">Agenda, equipe e clientes no mesmo fluxo</p>
                                </div>

                                <div class="rounded-[24px] border border-white/8 bg-[color-mix(in_srgb,var(--brand-secondary)_92%,black)] px-5 py-4 shadow-[var(--shadow-card)]">
                                    <p class="sf-page-eyebrow">Conversão</p>
                                    <p class="mt-2 text-lg font-semibold text-[var(--text-main)]">Autoagendamento e financeiro sem trocar de sistema</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="w-full overflow-hidden rounded-[28px] border border-white/10 bg-[color-mix(in_srgb,var(--brand-secondary)_94%,black)] shadow-[var(--shadow-elevated)]">
                        <div class="border-b border-white/10 px-6 py-6 sm:px-8">
                            <a href="/" class="inline-flex items-center gap-3">
                                @if (! empty($tenantBranding['logo_url']))
                                    <img src="{{ $tenantBranding['logo_url'] }}" alt="" class="h-11 w-11 rounded-2xl object-cover ring-1 ring-white/10" loading="lazy" decoding="async">
                                @else
                                    <x-application-logo class="h-11 w-11 brand-text" />
                                @endif
                                <div>
                                    <p class="text-lg font-semibold text-[var(--text-main)]">{{ $tenantBranding['company_name'] }}</p>
                                    <p class="text-sm sf-text-muted">{{ $tenantBranding['hero_subtitle'] ?? $tenantBranding['description_fallback'] ?? 'Agenda inteligente para barbearias, salões e estética' }}</p>
                                </div>
                            </a>
                        </div>

                        <div class="px-6 py-6 sm:px-8 sm:py-8">
                            {{ $slot }}
                        </div>

                        @if (! empty($tenantBranding['custom_footer_text']))
                            <div class="border-t border-white/10 px-6 py-4 text-center text-xs sf-text-muted/90 sm:px-8">
                                {{ $tenantBranding['custom_footer_text'] }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
