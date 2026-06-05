<nav x-data="{ open: false }" class="sf-admin-topbar sticky top-0 z-40">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
        <div class="flex h-20 items-center justify-between gap-6">
            <div class="flex min-w-0 items-center gap-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    @if (! auth()->user()->isSuperAdmin() && ! empty($tenantBranding['logo_url']))
                        <img src="{{ $tenantBranding['logo_url'] }}" alt="Logo de {{ auth()->user()->company?->name }}" class="h-11 w-11 rounded-2xl object-cover ring-1 ring-white/10 shadow-[0_10px_24px_rgba(0,0,0,0.18)]" loading="lazy" decoding="async">
                    @else
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_22%,transparent)] bg-[var(--input-bg)] text-[var(--brand-primary)] shadow-[0_10px_24px_color-mix(in_srgb,var(--text-main)_8%,transparent)]">
                            <x-application-logo class="h-6 w-6" />
                        </div>
                    @endif
                    <div>
                        <p class="text-sm font-semibold tracking-tight sf-text">{{ auth()->user()->isSuperAdmin() ? 'StudioFlow' : (auth()->user()->company?->name ?? 'StudioFlow') }}</p>
                        <p class="hidden text-xs leading-5 sf-text-muted sm:block">{{ auth()->user()->isSuperAdmin() ? 'Agenda inteligente para negócios de serviços' : (auth()->user()->company?->safeDescription() ?: 'Agenda inteligente para negócios de serviços') }}</p>
                    </div>
                </a>

                <div class="hidden xl:flex xl:items-center xl:gap-2">
                    <span class="rounded-full border border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[color-mix(in_srgb,var(--text-main)_5%,transparent)] px-3 py-1 text-xs font-medium sf-text-muted">
                        {{ auth()->user()->isSuperAdmin() ? 'Painel Global' : (auth()->user()->company?->name ?? 'Sem empresa') }}
                    </span>
                </div>
            </div>

            <div class="hidden items-center gap-3 sm:flex">
                <x-dropdown align="right" width="w-56" contentClasses="rounded-2xl border border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--card-bg)] p-2 shadow-[0_18px_40px_color-mix(in_srgb,var(--text-main)_12%,transparent)]">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-3 rounded-2xl border border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--card-bg)] px-3 py-2.5 text-sm font-medium sf-text shadow-[0_10px_24px_color-mix(in_srgb,var(--text-main)_8%,transparent)] transition duration-150 ease-in-out hover:border-[color-mix(in_srgb,var(--brand-primary)_30%,transparent)] hover:bg-[color-mix(in_srgb,var(--card-bg)_92%,var(--brand-primary))] focus:outline-none">
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--brand-primary)_14%,transparent)] text-sm font-semibold text-[var(--brand-primary)]">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <div class="text-left leading-tight">
                                <div class="max-w-36 truncate text-sm sf-text">{{ Auth::user()->name }}</div>
                                <div class="max-w-36 truncate text-xs sf-text-muted">
                                    @if (request()->attributes->get('support_mode_active'))
                                        Modo suporte
                                    @elseif (Auth::user()->isSuperAdmin())
                                        Painel Global
                                    @else
                                        {{ Auth::user()->company?->name }}
                                    @endif
                                </div>
                            </div>

                            <div class="ms-1 sf-text-muted">
                                <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            Perfil
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button type="submit" class="block w-full rounded-xl px-4 py-2.5 text-start text-sm leading-5 sf-text-muted transition duration-150 ease-in-out hover:bg-[color-mix(in_srgb,var(--text-main)_6%,transparent)] hover:text-[var(--text-main)] focus:outline-none focus:bg-[color-mix(in_srgb,var(--text-main)_6%,transparent)]">
                                Sair
                            </button>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center lg:hidden">
                <button @click="open = ! open" type="button" aria-controls="sf-responsive-nav" :aria-expanded="open ? 'true' : 'false'" class="inline-flex items-center justify-center rounded-xl border border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] bg-[var(--card-bg)] p-2.5 sf-text-muted transition duration-150 ease-in-out hover:border-[color-mix(in_srgb,var(--brand-primary)_28%,transparent)] hover:text-[var(--text-main)] focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div id="sf-responsive-nav" :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] lg:hidden">
        <div class="space-y-2 px-4 py-4">
            @if (auth()->user()->isSuperAdmin())
                <x-responsive-nav-link :href="route('super-admin.dashboard')" :active="request()->routeIs('super-admin.dashboard')">
                    Painel Global
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('super-admin.companies.index')" :active="request()->routeIs('super-admin.companies.*')">
                    Empresas
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('super-admin.users.index')" :active="request()->routeIs('super-admin.users.*')">
                    Usuários
                </x-responsive-nav-link>
            @else
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    Painel
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('daily-dashboard.index')" :active="request()->routeIs('daily-dashboard.*')">
                    Central do Dia
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('pdv.index')" :active="request()->routeIs('pdv.*')">
                    PDV
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('appointments.index')" :active="request()->routeIs('appointments.*')">
                    Agendamentos
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('finance.cash')" :active="request()->routeIs('finance.cash*')">
                    Caixa
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('expenses.index')" :active="request()->routeIs('expenses.*')">
                    Despesas
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                    Clientes
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">
                    Serviços
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.*') || request()->routeIs('product-sales.*')">
                    Produtos
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stock.index')" :active="request()->routeIs('stock.*')">
                    Estoque
                </x-responsive-nav-link>
                @if (auth()->user()->isAdmin())
                    <x-responsive-nav-link :href="route('membership-plans.index')" :active="request()->routeIs('membership-plans.*') || request()->routeIs('customer-memberships.*')">
                        Assinaturas
                    </x-responsive-nav-link>
                @endif
                @if (auth()->user()->isAdmin())
                    <x-responsive-nav-link :href="route('team.index')" :active="request()->routeIs('team.*')">
                        Equipe
                    </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('finance.index')" :active="request()->routeIs('finance.index') || request()->routeIs('finance.production') || request()->routeIs('finance.commissions*') || request()->routeIs('finance.report') || request()->routeIs('finance.service-report') || request()->routeIs('finance.performance') || request()->routeIs('production.*')">
                    Relatórios
                </x-responsive-nav-link>
                @if (auth()->user()->hasFinancialPrivileges())
                    <x-responsive-nav-link :href="route('reviews.index')" :active="request()->routeIs('reviews.*')">
                        Avaliações
                    </x-responsive-nav-link>
                @endif
                @if (auth()->user()->isAdmin())
                    <x-responsive-nav-link :href="route('company.edit')" :active="request()->routeIs('company.*')">
                        Empresa
                    </x-responsive-nav-link>
                @endif
            @endif
        </div>

        <div class="border-t border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] px-4 py-4">
            <div class="px-4">
                <div class="text-base font-medium sf-text">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium sf-text-muted">
                    @if (request()->attributes->get('support_mode_active'))
                        Modo suporte
                    @elseif (Auth::user()->isSuperAdmin())
                        Painel Global
                    @else
                        {{ Auth::user()->company?->name }}
                    @endif
                </div>
                <div class="text-sm font-medium sf-text-muted">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-2">
                <x-responsive-nav-link :href="route('profile.edit')">
                    Perfil
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit" class="block w-full rounded-xl border border-transparent px-4 py-3 text-start text-base font-medium sf-text-muted transition duration-150 ease-in-out hover:border-[color-mix(in_srgb,var(--text-main)_10%,transparent)] hover:bg-[color-mix(in_srgb,var(--text-main)_5%,transparent)] hover:text-[var(--text-main)] focus:outline-none">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
