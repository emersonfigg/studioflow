<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#1b335b] font-sans antialiased text-white">
        <div class="min-h-screen bg-[#1b335b]">
            @include('layouts.navigation')

            <div class="mx-auto flex max-w-[1600px] gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <aside class="hidden lg:flex lg:w-72 lg:flex-col">
                    <div class="sf-card sticky top-24 p-5">
                        <div class="flex items-center gap-3">
                            @if (! auth()->user()->isSuperAdmin() && auth()->user()->company?->logo_url)
                                <img src="{{ auth()->user()->company->logo_url }}" alt="Logo de {{ auth()->user()->company->name }}" class="h-12 w-12 rounded-2xl object-cover ring-1 ring-white/10">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-[#d4af37]">
                                    <x-application-logo class="h-8 w-8" />
                                </div>
                            @endif
                            <div>
                                <p class="text-base font-semibold text-white">{{ auth()->user()->isSuperAdmin() ? 'StudioFlow' : (auth()->user()->company?->name ?? 'StudioFlow') }}</p>
                                <p class="text-xs leading-5 text-[#c7d2e3]">{{ auth()->user()->isSuperAdmin() ? 'Agenda inteligente para barbearias, salões e estética' : (auth()->user()->company?->description ?: 'Agenda inteligente para barbearias, salões e estética') }}</p>
                            </div>
                        </div>

                        <div class="mt-6 rounded-2xl border border-white/8 bg-[#132746] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#d4af37]">{{ auth()->user()->isSuperAdmin() ? 'Escopo' : 'Empresa' }}</p>
                            <p class="mt-2 text-sm font-semibold text-white">{{ auth()->user()->isSuperAdmin() ? 'Painel Global' : (auth()->user()->company?->name ?? 'Sem empresa vinculada') }}</p>
                            <p class="mt-1 text-sm text-[#c7d2e3]">{{ auth()->user()->name }}</p>
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
                                        ['label' => 'Clientes', 'route' => 'clients.index', 'match' => 'clients.*', 'icon' => 'M10 9a3 3 0 100-6 3 3 0 000 6zm-5.75 6.5A3.25 3.25 0 017.5 12.25h5a3.25 3.25 0 013.25 3.25v.25a.75.75 0 01-.75.75H5a.75.75 0 01-.75-.75v-.25z'],
                                        ['label' => 'Serviços', 'route' => 'services.index', 'match' => 'services.*', 'icon' => 'M4.5 5A2.5 2.5 0 017 2.5h6A2.5 2.5 0 0115.5 5v10A2.5 2.5 0 0113 17.5H7A2.5 2.5 0 014.5 15V5zm3 .75a.75.75 0 000 1.5h5a.75.75 0 000-1.5h-5zm0 3a.75.75 0 000 1.5h5a.75.75 0 000-1.5h-5z'],
                                        ['label' => 'Produtos', 'route' => 'products.index', 'match' => 'products.*|product-sales.*', 'icon' => 'M5 4.75A1.75 1.75 0 016.75 3h6.5A1.75 1.75 0 0115 4.75v1.336c0 .398.158.779.439 1.061l.31.31A2.75 2.75 0 0116.5 9.4v4.85A2.75 2.75 0 0113.75 17h-7.5A2.75 2.75 0 013.5 14.25V9.4c0-.73.29-1.429.806-1.945l.31-.31c.281-.282.439-.663.439-1.061V4.75zm1.75-.25a.25.25 0 00-.25.25v1.336A2.75 2.75 0 015.694 8.03l-.31.31A1.25 1.25 0 005 9.4v4.85c0 .69.56 1.25 1.25 1.25h7.5c.69 0 1.25-.56 1.25-1.25V9.4c0-.331-.132-.649-.366-.883l-.31-.31a2.75 2.75 0 01-.806-1.944V4.75a.25.25 0 00-.25-.25h-6.5z'],
                                        ['label' => 'Vendas', 'route' => 'product-sales.index', 'match' => 'product-sales.*', 'icon' => 'M4.5 5.5A2.5 2.5 0 017 3h6a2.5 2.5 0 012.5 2.5v1.379a2.75 2.75 0 011.161 2.227v4.144A2.75 2.75 0 0113.911 16H6.09a2.75 2.75 0 01-2.75-2.75V9.106c0-.904.437-1.753 1.16-2.227V5.5zm2.5-1a1 1 0 00-1 1v.629c.3-.055.61-.083.93-.083h6.14c.32 0 .63.028.93.083V5.5a1 1 0 00-1-1H7zm-.91 3.046a1.25 1.25 0 00-1.25 1.25v4.454c0 .69.56 1.25 1.25 1.25h7.82c.69 0 1.25-.56 1.25-1.25V8.796c0-.69-.56-1.25-1.25-1.25H6.09zm2.16 1.704a.75.75 0 000 1.5h3.5a.75.75 0 000-1.5h-3.5z'],
                                        ['label' => 'Agendamentos', 'route' => 'appointments.index', 'match' => 'appointments.*', 'icon' => 'M5.75 3a.75.75 0 000 1.5h8.5a.75.75 0 000-1.5h-8.5zM4 6.75A1.75 1.75 0 015.75 5h8.5A1.75 1.75 0 0116 6.75v7.5A1.75 1.75 0 0114.25 16h-8.5A1.75 1.75 0 014 14.25v-7.5z'],
                                        ['label' => 'Minha agenda', 'route' => 'schedule.edit', 'match' => 'schedule.*', 'icon' => 'M6.75 2.5a.75.75 0 000 1.5h6.5a2.25 2.25 0 012.25 2.25v7.5a2.25 2.25 0 01-2.25 2.25h-6.5a.75.75 0 000 1.5h6.5A3.75 3.75 0 0017 13.75v-7.5A3.75 3.75 0 0013.25 2.5h-6.5zm-1 4A.75.75 0 015 7.25v5.5a.75.75 0 001.5 0v-5.5a.75.75 0 01.75-.75h2.5a.75.75 0 000-1.5h-2.5A2.25 2.25 0 005.75 6.5z'],
                                        ['label' => 'Financeiro', 'route' => 'finance.index', 'match' => 'finance.*', 'icon' => 'M3.5 5.75A2.25 2.25 0 015.75 3.5h8.5a2.25 2.25 0 012.25 2.25v8.5a2.25 2.25 0 01-2.25 2.25h-8.5A2.25 2.25 0 013.5 14.25v-8.5zm2.25-.75a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-8.5a.75.75 0 00-.75-.75h-8.5zm1.5 2.25a.75.75 0 000 1.5h5.5a.75.75 0 000-1.5h-5.5zm0 3a.75.75 0 000 1.5h5.5a.75.75 0 000-1.5h-5.5z'],
                                    ];

                                    if (auth()->user()->isAdmin()) {
                                        array_splice($sidebarLinks, 4, 0, [[
                                            'label' => 'Equipe',
                                            'route' => 'team.index',
                                            'match' => 'team.*',
                                            'icon' => 'M10 3.75a3.25 3.25 0 110 6.5 3.25 3.25 0 010-6.5zm-5.75 11a2.25 2.25 0 012.25-2.25h7a2.25 2.25 0 012.25 2.25v.5a.75.75 0 01-.75.75H5a.75.75 0 01-.75-.75v-.5z',
                                        ]]);
                                        array_splice($sidebarLinks, 1, 0, [[
                                            'label' => 'Empresa',
                                            'route' => 'company.edit',
                                            'match' => 'company.*',
                                            'icon' => 'M3.5 15.25V7.94a1.5 1.5 0 01.64-1.23l5-3.57a1.5 1.5 0 011.72 0l5 3.57a1.5 1.5 0 01.64 1.23v7.31A1.75 1.75 0 0114.75 17H5.25A1.75 1.75 0 013.5 15.25zm4-4a.75.75 0 000 1.5h5a.75.75 0 000-1.5h-5z',
                                        ]]);
                                    }
                                }
                            @endphp

                            @foreach ($sidebarLinks as $link)
                                @php
                                    $active = collect(explode('|', $link['match']))->contains(fn ($pattern) => request()->routeIs($pattern));
                                @endphp
                                <a
                                    href="{{ route($link['route']) }}"
                                    class="{{ $active ? 'border-[#d4af37]/25 bg-[#d4af37]/12 text-white' : 'border-transparent text-[#c7d2e3] hover:border-white/10 hover:bg-white/5 hover:text-white' }} flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-medium transition duration-150"
                                >
                                    <span class="{{ $active ? 'bg-[#d4af37]/16 text-[#d4af37]' : 'bg-white/5 text-[#c7d2e3]' }} flex h-10 w-10 items-center justify-center rounded-xl transition duration-150">
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
                        <div class="mb-6 rounded-2xl border border-[#d4af37]/25 bg-[#d4af37]/10 px-5 py-4 shadow-[0_14px_32px_rgba(9,20,45,0.18)]">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Modo suporte ativo</p>
                                    <p class="mt-1 text-sm font-semibold text-white">
                                        Você está atuando como {{ $supportMode['support_user_name'] ?? 'usuário da empresa' }} em {{ $supportMode['company_name'] ?? 'empresa do cliente' }}.
                                    </p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">
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
