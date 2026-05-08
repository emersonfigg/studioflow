<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="pdv-shell h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'PDV') - {{ config('app.name', 'StudioFlow') }}</title>
        <style>
            html.pdv-shell,
            html.pdv-shell body {
                height: 100%;
                max-height: 100%;
                overflow: hidden;
                overscroll-behavior: none;
            }
        </style>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="pdv-shell__body bg-[#0f203b] font-sans antialiased text-white">
        <div x-data="{ menuOpen: false }" class="flex h-full max-h-full min-h-0 flex-col overflow-hidden">
            <header class="z-40 shrink-0 border-b border-white/10 bg-[#132746]/95 backdrop-blur">
                <div class="mx-auto flex max-w-[1900px] items-center justify-between gap-3 px-3 py-2 sm:px-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" @click="menuOpen = true" class="inline-flex items-center rounded-xl border border-white/15 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#d4af37] hover:bg-white/5">
                            Menu
                        </button>
                        @if (auth()->user()->company?->logo_url)
                            <img src="{{ auth()->user()->company->logo_url }}" alt="Logo empresa" class="h-9 w-9 rounded-lg object-cover ring-1 ring-white/10">
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ auth()->user()->company?->name ?? 'StudioFlow' }}</p>
                            <p class="truncate text-[11px] text-[#c7d2e3]">Operador: {{ auth()->user()->name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @isset($cashRegister)
                            <span class="hidden rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] sm:inline-flex {{ $cashRegister?->closed_at ? 'border-white/20 bg-white/10 text-[#c7d2e3]' : ($cashRegister ? 'border-[#d4af37]/40 bg-[#d4af37]/15 text-[#d4af37]' : 'border-amber-500/30 bg-amber-500/10 text-amber-200') }}">
                                {{ $cashRegister?->closed_at ? 'Caixa fechado' : ($cashRegister ? 'Caixa aberto' : 'Caixa não iniciado') }}
                            </span>
                        @endisset
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-xl border border-white/15 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-white hover:bg-white/5">Sair do PDV</a>
                    </div>
                </div>
            </header>

            <div x-cloak x-show="menuOpen" class="fixed inset-0 z-50 bg-black/50" @click="menuOpen = false"></div>
            <aside x-cloak x-show="menuOpen" x-transition class="fixed inset-y-0 left-0 z-50 w-[86%] max-w-xs border-r border-white/10 bg-[#132746] p-4 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-[#d4af37]">Menu operacional</p>
                    <button type="button" @click="menuOpen = false" class="rounded-lg border border-white/15 px-2 py-1 text-xs text-[#c7d2e3]">Fechar</button>
                </div>
                <nav class="space-y-2 text-sm">
                    <a href="{{ route('dashboard') }}" class="block rounded-xl border border-white/10 px-4 py-3 hover:bg-white/5">Painel</a>
                    <a href="{{ route('clients.index') }}" class="block rounded-xl border border-white/10 px-4 py-3 hover:bg-white/5">Clientes</a>
                    <a href="{{ route('services.index') }}" class="block rounded-xl border border-white/10 px-4 py-3 hover:bg-white/5">Serviços</a>
                    <a href="{{ route('products.index') }}" class="block rounded-xl border border-white/10 px-4 py-3 hover:bg-white/5">Produtos</a>
                    <a href="{{ route('product-sales.index') }}" class="block rounded-xl border border-white/10 px-4 py-3 hover:bg-white/5">Vendas</a>
                    <a href="{{ route('finance.index') }}" class="block rounded-xl border border-white/10 px-4 py-3 hover:bg-white/5">Financeiro</a>
                    <a href="{{ route('pdv.index') }}" class="block rounded-xl border border-[#d4af37]/30 bg-[#d4af37]/10 px-4 py-3 text-[#d4af37]">PDV</a>
                    <a href="{{ route('pdv.sales') }}" class="block rounded-xl border border-white/10 px-4 py-3 hover:bg-white/5">Histórico de Vendas</a>
                </nav>
            </aside>

            <main class="mx-auto flex min-h-0 max-w-[1900px] flex-1 flex-col overflow-hidden px-2 py-1 sm:px-3">
                @yield('content')
            </main>
        </div>
    </body>
</html>

