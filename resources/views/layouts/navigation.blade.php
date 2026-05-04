<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-white/10 bg-[#1b335b]/95 backdrop-blur-xl">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
        <div class="flex h-20 items-center justify-between gap-6">
            <div class="flex min-w-0 items-center gap-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    @if (! auth()->user()->isSuperAdmin() && auth()->user()->company?->logo_url)
                        <img src="{{ auth()->user()->company->logo_url }}" alt="Logo de {{ auth()->user()->company->name }}" class="h-11 w-11 rounded-2xl object-cover ring-1 ring-white/10 shadow-[0_10px_24px_rgba(9,20,45,0.22)]">
                    @else
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#d4af37]/20 bg-[#132746] text-[#d4af37] shadow-[0_10px_24px_rgba(9,20,45,0.22)]">
                            <x-application-logo class="h-6 w-6" />
                        </div>
                    @endif
                    <div>
                        <p class="text-sm font-semibold tracking-tight text-white">{{ auth()->user()->isSuperAdmin() ? 'StudioFlow' : (auth()->user()->company?->name ?? 'StudioFlow') }}</p>
                        <p class="hidden text-xs leading-5 text-[#c7d2e3] sm:block">{{ auth()->user()->isSuperAdmin() ? 'Agenda inteligente para barbearias, salões e estética' : (auth()->user()->company?->description ?: 'Agenda inteligente para barbearias, salões e estética') }}</p>
                    </div>
                </a>

                <div class="hidden xl:flex xl:items-center xl:gap-2">
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-[#c7d2e3]">
                        {{ auth()->user()->isSuperAdmin() ? 'Painel Global' : (auth()->user()->company?->name ?? 'Sem empresa') }}
                    </span>
                </div>
            </div>

            <div class="hidden items-center gap-3 sm:flex">
                <x-dropdown align="right" width="w-56" contentClasses="rounded-2xl border border-white/10 bg-[#223d69] p-2 shadow-[0_18px_40px_rgba(9,20,45,0.35)]">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-[#223d69] px-3 py-2.5 text-sm font-medium text-white shadow-[0_10px_24px_rgba(9,20,45,0.18)] transition duration-150 ease-in-out hover:border-[#d4af37]/30 hover:bg-[#294775] focus:outline-none">
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-sm font-semibold text-[#d4af37]">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <div class="text-left leading-tight">
                                <div class="max-w-36 truncate text-sm text-white">{{ Auth::user()->name }}</div>
                                <div class="max-w-36 truncate text-xs text-[#c7d2e3]">
                                    @if (request()->attributes->get('support_mode_active'))
                                        Modo suporte
                                    @else
                                        {{ Auth::user()->company?->name }}
                                    @endif
                                </div>
                            </div>

                            <div class="ms-1 text-[#c7d2e3]">
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

                            <button type="submit" class="block w-full rounded-xl px-4 py-2.5 text-start text-sm leading-5 text-[#c7d2e3] transition duration-150 ease-in-out hover:bg-white/5 hover:text-white focus:outline-none focus:bg-white/5">
                                Sair
                            </button>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-xl border border-white/10 bg-[#223d69] p-2.5 text-[#c7d2e3] transition duration-150 ease-in-out hover:border-[#d4af37]/30 hover:text-white focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-white/10 sm:hidden">
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
                <x-responsive-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                    Clientes
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">
                    Serviços
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.*') || request()->routeIs('product-sales.*')">
                    Produtos
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('product-sales.index')" :active="request()->routeIs('product-sales.*')">
                    Vendas
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('pdv.index')" :active="request()->routeIs('pdv.*')">
                    PDV
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('appointments.index')" :active="request()->routeIs('appointments.*')">
                    Agendamentos
                </x-responsive-nav-link>
                @if (auth()->user()->isAdmin())
                    <x-responsive-nav-link :href="route('company.edit')" :active="request()->routeIs('company.*')">
                        Empresa
                    </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('schedule.edit')" :active="request()->routeIs('schedule.*') || request()->routeIs('team.availability.*')">
                    Minha agenda
                </x-responsive-nav-link>
                @if (auth()->user()->isAdmin())
                    <x-responsive-nav-link :href="route('team.index')" :active="request()->routeIs('team.*')">
                        Equipe
                    </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('finance.cash')" :active="request()->routeIs('finance.cash*')">
                    Caixa
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('finance.index')" :active="request()->routeIs('finance.index') || request()->routeIs('finance.production') || request()->routeIs('finance.commissions*') || request()->routeIs('finance.report') || request()->routeIs('finance.performance') || request()->routeIs('production.*')">
                    Financeiro
                </x-responsive-nav-link>
            @endif
        </div>

        <div class="border-t border-white/10 px-4 py-4">
            <div class="px-4">
                <div class="text-base font-medium text-white">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-[#c7d2e3]">
                    @if (request()->attributes->get('support_mode_active'))
                        Modo suporte
                    @else
                        {{ Auth::user()->company?->name }}
                    @endif
                </div>
                <div class="text-sm font-medium text-[#c7d2e3]">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-2">
                <x-responsive-nav-link :href="route('profile.edit')">
                    Perfil
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit" class="block w-full rounded-xl border border-transparent px-4 py-3 text-start text-base font-medium text-[#c7d2e3] transition duration-150 ease-in-out hover:border-white/10 hover:bg-white/5 hover:text-white focus:outline-none">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
