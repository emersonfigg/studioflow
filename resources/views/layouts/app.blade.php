<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ ($tenantThemeLight ?? false) ? 'light' : 'dark' }}" style="{{ $tenantBranding['root_style'] ?? '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @if (! empty($tenantFaviconHref))
            <link rel="icon" type="image/png" href="{{ $tenantFaviconHref }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="app-chrome-body font-sans antialiased">
        <div class="app-chrome-shell min-h-screen">
            @include('layouts.navigation')

            <div class="mx-auto flex max-w-[1600px] gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <aside class="hidden lg:flex lg:w-72 lg:flex-col">
                    <div class="sf-card sticky top-24 p-5">
                        <div class="flex items-center gap-3">
                            @if (! auth()->user()->isSuperAdmin() && ! empty($tenantBranding['logo_url']))
                                <img src="{{ $tenantBranding['logo_url'] }}" alt="Logo de {{ auth()->user()->company?->name }}" class="h-12 w-12 rounded-2xl object-cover ring-1 ring-white/10" loading="lazy" decoding="async">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_14%,transparent)] text-[var(--brand-primary)]">
                                    <x-application-logo class="h-8 w-8" />
                                </div>
                            @endif
                            <div>
                                <p class="text-base font-semibold sf-text">{{ auth()->user()->isSuperAdmin() ? 'StudioFlow' : (auth()->user()->company?->name ?? 'StudioFlow') }}</p>
                                <p class="text-xs leading-5 sf-text-muted">{{ auth()->user()->isSuperAdmin() ? 'Agenda inteligente para negócios de serviços' : (auth()->user()->company?->safeDescription() ?: 'Agenda inteligente para negócios de serviços') }}</p>
                            </div>
                        </div>

                        <div class="mt-6 rounded-2xl border border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--sidebar-card-bg)] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand-primary)]">{{ auth()->user()->isSuperAdmin() ? 'Escopo' : 'Empresa' }}</p>
                            <p class="mt-2 text-sm font-semibold sf-text">{{ auth()->user()->isSuperAdmin() ? 'Painel Global' : (auth()->user()->company?->name ?? 'Sem empresa vinculada') }}</p>
                            <p class="mt-1 text-sm sf-text-muted">{{ auth()->user()->name }}</p>
                        </div>

                        <nav class="mt-6 space-y-2">
                            @php
                                if (auth()->user()->isSuperAdmin()) {
                                    $sidebarLinks = [
                                        ['label' => 'Painel Global', 'route' => 'super-admin.dashboard', 'match' => 'super-admin.dashboard', 'icon' => 'M10.75 3.75a.75.75 0 00-1.5 0v5.5h-5.5a.75.75 0 000 1.5h5.5v5.5a.75.75 0 001.5 0v-5.5h5.5a.75.75 0 000-1.5h-5.5v-5.5z'],
                                        ['label' => 'Empresas', 'route' => 'super-admin.companies.index', 'match' => 'super-admin.companies.*', 'icon' => 'M3.5 5.75A2.25 2.25 0 015.75 3.5h8.5a2.25 2.25 0 012.25 2.25v8.5a2.25 2.25 0 01-2.25 2.25h-8.5A2.25 2.25 0 013.5 14.25v-8.5zm2.25-.75a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-8.5a.75.75 0 00-.75-.75h-8.5z'],
                                        ['label' => 'Usuários', 'route' => 'super-admin.users.index', 'match' => 'super-admin.users.*', 'icon' => 'M10 9a3 3 0 100-6 3 3 0 000 6zm-5.75 6.5A3.25 3.25 0 017.5 12.25h5a3.25 3.25 0 013.25 3.25v.25a.75.75 0 01-.75.75H5a.75.75 0 01-.75-.75v-.25z'],
                                    ];
                                } else {
                                    $sidebarLinks = [
                                        ['label' => 'Painel', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'M10.75 3.75a.75.75 0 00-1.5 0v5.5h-5.5a.75.75 0 000 1.5h5.5v5.5a.75.75 0 001.5 0v-5.5h5.5a.75.75 0 000-1.5h-5.5v-5.5z'],
                                        ['label' => 'PDV', 'route' => 'pdv.index', 'match' => 'pdv.*', 'icon' => 'M3.75 5.25A2.25 2.25 0 016 3h8a2.25 2.25 0 012.25 2.25v9.5A2.25 2.25 0 0114 17H6a2.25 2.25 0 01-2.25-2.25v-9.5zM6 4.5a.75.75 0 00-.75.75v2.25h9.5V5.25A.75.75 0 0014 4.5H6zm-.75 4.5v5.75c0 .414.336.75.75.75h8a.75.75 0 00.75-.75V9h-9.5zm2 1.25a.75.75 0 000 1.5h2a.75.75 0 000-1.5h-2z'],
                                        ['label' => 'Agendamentos', 'route' => 'appointments.index', 'match' => 'appointments.*', 'icon' => 'M5.75 3a.75.75 0 000 1.5h8.5a.75.75 0 000-1.5h-8.5zM4 6.75A1.75 1.75 0 015.75 5h8.5A1.75 1.75 0 0116 6.75v7.5A1.75 1.75 0 0114.25 16h-8.5A1.75 1.75 0 014 14.25v-7.5z'],
                                        ['label' => 'Caixa', 'route' => 'finance.cash', 'match' => 'finance.cash*', 'icon' => 'M2.75 5A2.25 2.25 0 015 2.75h10A2.25 2.25 0 0117.25 5v10A2.25 2.25 0 0115 17.25H5A2.25 2.25 0 012.75 15V5zm2 2a.75.75 0 000 1.5h10.5a.75.75 0 000-1.5H4.75zm0 3a.75.75 0 000 1.5h5.5a.75.75 0 000-1.5h-5.5z'],
                                        ['label' => 'Clientes', 'route' => 'clients.index', 'match' => 'clients.*', 'icon' => 'M10 9a3 3 0 100-6 3 3 0 000 6zm-5.75 6.5A3.25 3.25 0 017.5 12.25h5a3.25 3.25 0 013.25 3.25v.25a.75.75 0 01-.75.75H5a.75.75 0 01-.75-.75v-.25z'],
                                        ['label' => 'Serviços', 'route' => 'services.index', 'match' => 'services.*', 'icon' => 'M4.5 5A2.5 2.5 0 017 2.5h6A2.5 2.5 0 0115.5 5v10A2.5 2.5 0 0113 17.5H7A2.5 2.5 0 014.5 15V5zm3 .75a.75.75 0 000 1.5h5a.75.75 0 000-1.5h-5zm0 3a.75.75 0 000 1.5h5a.75.75 0 000-1.5h-5z'],
                                        ['label' => 'Produtos', 'route' => 'products.index', 'match' => 'products.*|product-sales.*', 'icon' => 'M5 4.75A1.75 1.75 0 016.75 3h6.5A1.75 1.75 0 0115 4.75v1.336c0 .398.158.779.439 1.061l.31.31A2.75 2.75 0 0116.5 9.4v4.85A2.75 2.75 0 0113.75 17h-7.5A2.75 2.75 0 013.5 14.25V9.4c0-.73.29-1.429.806-1.945l.31-.31c.281-.282.439-.663.439-1.061V4.75zm1.75-.25a.25.25 0 00-.25.25v1.336A2.75 2.75 0 015.694 8.03l-.31.31A1.25 1.25 0 005 9.4v4.85c0 .69.56 1.25 1.25 1.25h7.5c.69 0 1.25-.56 1.25-1.25V9.4c0-.331-.132-.649-.366-.883l-.31-.31a2.75 2.75 0 01-.806-1.944V4.75a.25.25 0 00-.25-.25h-6.5z'],
                                        ['label' => 'Relatórios', 'route' => 'finance.index', 'match' => 'finance.index|finance.production|finance.commissions*|finance.report|finance.service-report|finance.performance|production.*', 'icon' => 'M3.5 5.75A2.25 2.25 0 015.75 3.5h8.5a2.25 2.25 0 012.25 2.25v8.5a2.25 2.25 0 01-2.25 2.25h-8.5A2.25 2.25 0 013.5 14.25v-8.5zm2.25-.75a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-8.5a.75.75 0 00-.75-.75h-8.5zm1.5 2.25a.75.75 0 000 1.5h5.5a.75.75 0 000-1.5h-5.5zm0 3a.75.75 0 000 1.5h5.5a.75.75 0 000-1.5h-5.5z'],
                                    ];

                                    if (auth()->user()->isAdmin()) {
                                        array_splice($sidebarLinks, 7, 0, [[
                                            'label' => 'Assinaturas',
                                            'route' => 'membership-plans.index',
                                            'match' => 'membership-plans.*|customer-memberships.*',
                                            'icon' => 'M4.5 5.25A2.25 2.25 0 016.75 3h6.5a2.25 2.25 0 012.25 2.25v9.5A2.25 2.25 0 0113.25 17h-6.5A2.25 2.25 0 014.5 14.75V5.25zm2.25-.75a.75.75 0 00-.75.75v9.5c0 .414.336.75.75.75h6.5a.75.75 0 00.75-.75v-9.5a.75.75 0 00-.75-.75h-6.5z',
                                        ]]);
                                        array_splice($sidebarLinks, 8, 0, [[
                                            'label' => 'Equipe',
                                            'route' => 'team.index',
                                            'match' => 'team.*',
                                            'icon' => 'M10 3.75a3.25 3.25 0 110 6.5 3.25 3.25 0 010-6.5zm-5.75 11a2.25 2.25 0 012.25-2.25h7a2.25 2.25 0 012.25 2.25v.5a.75.75 0 01-.75.75H5a.75.75 0 01-.75-.75v-.5z',
                                        ]]);
                                        $sidebarLinks[] = [
                                            'label' => 'Empresa',
                                            'route' => 'company.edit',
                                            'match' => 'company.*',
                                            'icon' => 'M3.5 15.25V7.94a1.5 1.5 0 01.64-1.23l5-3.57a1.5 1.5 0 011.72 0l5 3.57a1.5 1.5 0 01.64 1.23v7.31A1.75 1.75 0 0114.75 17H5.25A1.75 1.75 0 013.5 15.25zm4-4a.75.75 0 000 1.5h5a.75.75 0 000-1.5h-5z',
                                        ];
                                    }

                                    if (auth()->user()->hasFinancialPrivileges()) {
                                        $sidebarLinks[] = [
                                            'label' => 'Avaliações',
                                            'route' => 'reviews.index',
                                            'match' => 'reviews.*',
                                            'icon' => 'M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z',
                                        ];
                                    }
                                }
                            @endphp

                            @foreach ($sidebarLinks as $link)
                                @php
                                    $active = collect(explode('|', $link['match']))->contains(fn ($pattern) => request()->routeIs($pattern));
                                @endphp
                                <a
                                    href="{{ route($link['route']) }}"
                                    class="{{ $active ? 'nav-item-active border shadow-[inset_0_0_0_1px_color-mix(in_srgb,var(--brand-primary)_32%,transparent)]' : 'border border-transparent text-[var(--text-muted)] hover:border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] hover:bg-[color-mix(in_srgb,var(--text-main)_5%,transparent)] hover:text-[var(--text-main)]' }} flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition duration-150"
                                >
                                    <span class="{{ $active ? 'bg-[color-mix(in_srgb,var(--brand-primary)_14%,transparent)] text-[var(--brand-primary)]' : 'bg-[color-mix(in_srgb,var(--text-main)_6%,transparent)] text-[var(--text-muted)]' }} flex h-10 w-10 items-center justify-center rounded-xl transition duration-150">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="{{ $link['icon'] }}" />
                                        </svg>
                                    </span>
                                    <span>{{ $link['label'] }}</span>
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </aside>

                <div class="min-w-0 flex-1">
                    @if (request()->attributes->get('support_mode_active'))
                        @php($supportMode = request()->attributes->get('support_mode'))
                        <div class="mb-6 rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_28%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_10%,var(--card-bg))] px-5 py-4 shadow-[0_14px_32px_color-mix(in_srgb,var(--text-main)_6%,transparent)]">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--brand-primary)]">Modo suporte ativo</p>
                                    <p class="mt-1 text-sm font-semibold sf-text">
                                        Você está atuando como {{ $supportMode['support_user_name'] ?? 'usuário da empresa' }} em {{ $supportMode['company_name'] ?? 'empresa do cliente' }}.
                                    </p>
                                    <p class="mt-1 text-sm sf-text-muted">
                                        Acessó iniciado por {{ $supportMode['original_user_name'] ?? 'super admin' }} para resolver demandas do cliente sem sair do sistema.
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('super-admin.support.stop') }}">
                                    @csrf
                                    <button type="submit" class="sf-button-secondary whitespace-nowrap">
                                        Encerrar modo suporte
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    @isset($header)
                        <header class="sf-card mb-6 px-5 py-5 sm:px-6">
                            {{ $header }}
                        </header>
                    @endisset

                    <main>
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
