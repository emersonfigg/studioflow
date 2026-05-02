<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>StudioFlow</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#1b335b] font-sans text-white antialiased">
        <div class="min-h-screen bg-[#1b335b]">
            <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-5 sm:px-6 lg:px-8">
                <header class="flex flex-col gap-4 border-b border-white/10 pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-[#d4af37] ring-1 ring-white/10">
                            <x-application-logo class="h-7 w-7" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-white">StudioFlow</p>
                            <p class="text-xs leading-5 text-[#c7d2e3]">Agenda inteligente para barbearias, salões e estética</p>
                        </div>
                    </a>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('login') }}" class="sf-button-secondary w-full sm:w-auto">
                            Entrar
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="sf-button-primary w-full sm:w-auto">
                                Criar conta
                            </a>
                        @endif
                    </div>
                </header>

                <main class="grid flex-1 gap-8 py-8 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)] lg:items-center lg:py-12">
                    <section class="min-w-0">
                        <div class="inline-flex rounded-full border border-[#d4af37]/25 bg-[#d4af37]/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-[#f6e7b3]">
                            StudioFlow
                        </div>

                        <h1 class="mt-6 max-w-3xl text-4xl font-semibold leading-tight text-white sm:text-5xl lg:text-[3.4rem]">
                            Agenda, equipe e financeiro em um só lugar.
                        </h1>

                        <p class="mt-5 max-w-2xl text-base leading-7 text-[#c7d2e3] sm:text-lg">
                            Organize atendimentos, clientes, profissionais, comissões e links públicos de agendamento com uma operação clara e rápida.
                        </p>

                        <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('login') }}" class="sf-button-primary w-full sm:w-auto">
                                Entrar no sistema
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="sf-button-secondary w-full sm:w-auto">
                                    Criar conta
                                </a>
                            @endif
                        </div>

                        <div class="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ([
                                ['title' => 'Agenda online', 'description' => 'Horários, status e disponibilidade sempre visíveis.'],
                                ['title' => 'Equipe', 'description' => 'Profissionais, escalas e permissões no mesmo fluxo.'],
                                ['title' => 'Comissões', 'description' => 'Pagamentos e repasses sem planilhas paralelas.'],
                                ['title' => 'Link público', 'description' => 'Agendamento simples para seus clientes.'],
                            ] as $benefit)
                                <article class="rounded-2xl border border-white/10 bg-[#223d69] p-4 shadow-[0_18px_36px_rgba(9,20,45,0.2)]">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#d4af37]/12 text-[#d4af37]">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 2.75a.75.75 0 01.75.75v5.75h5.75a.75.75 0 010 1.5h-5.75v5.75a.75.75 0 01-1.5 0v-5.75H3.5a.75.75 0 010-1.5h5.75V3.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <h2 class="mt-4 text-base font-semibold text-white">{{ $benefit['title'] }}</h2>
                                    <p class="mt-2 text-sm leading-6 text-[#c7d2e3]">{{ $benefit['description'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    <aside class="sf-card p-5 sm:p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#d4af37]">Acesso rápido</p>
                        <h2 class="mt-3 text-2xl font-semibold text-white">Entre e comande sua operação</h2>
                        <p class="mt-3 text-sm leading-6 text-[#c7d2e3]">
                            Use o StudioFlow para acompanhar agenda, time, clientes, financeiro e autoagendamento sem trocar de sistema.
                        </p>

                        <div class="mt-6 grid gap-3">
                            <a href="{{ route('login') }}" class="sf-button-primary w-full">
                                Entrar
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="sf-button-secondary w-full">
                                    Criar conta
                                </a>
                            @endif
                        </div>

                        <div class="mt-6 space-y-3">
                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">Tudo em um fluxo</p>
                                <p class="mt-2 text-base font-semibold text-white">Do agendamento ao pagamento</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">Experiência completa</p>
                                <p class="mt-2 text-base font-semibold text-white">Para equipe e clientes</p>
                            </div>
                        </div>
                    </aside>
                </main>
            </div>
        </div>
    </body>
</html>
