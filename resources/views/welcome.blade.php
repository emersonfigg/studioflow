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
    <body class="min-h-screen bg-[#1b335b] text-white antialiased">
        <div class="relative isolaté min-h-screen overflow-hidden bg-[#1b335b]">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(212,175,55,0.18),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(19,39,70,0.7),_transparent_38%)]"></div>

            <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
                <header class="flex items-center justify-between gap-4 py-2">
                    <a href="{{ url('/') }}" class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-[#d4af37] shadow-[0_16px_30px_rgba(8,18,38,0.28)] ring-1 ring-white/10">
                            <x-application-logo class="h-7 w-7" />
                        </div>
                        <div>
                            <p class="text-base font-semibold text-white">StudioFlow</p>
                            <p class="text-xs text-[#c7d2e3]">Agenda inteligente para barbearias, saloes e estetica</p>
                        </div>
                    </a>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('login') }}" class="sf-button-secondary">
                            Entrar
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="sf-button-primary">
                                Criar conta
                            </a>
                        @endif
                    </div>
                </header>

                <main class="flex flex-1 items-center py-10 sm:py-14 lg:py-20">
                    <div class="grid w-full gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,460px)] lg:items-center">
                        <section class="max-w-3xl">
                            <div class="inline-flex items-center rounded-full border border-[#d4af37]/25 bg-[#d4af37]/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-[#f6e7b3]">
                                StudioFlow SaaS
                            </div>

                            <h1 class="mt-6 text-4xl font-semibold leading-tight text-white sm:text-5xl lg:text-6xl">
                                Agenda inteligente para barbearias, saloes e estetica
                            </h1>

                            <p class="mt-5 max-w-2xl text-base leading-7 text-[#c7d2e3] sm:text-lg">
                                Organize horários, clientes, equipe, comissões e autoagendamento em um só lugar.
                            </p>

                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                <a href="{{ route('login') }}" class="sf-button-primary text-center">
                                    Entrar no sistema
                                </a>

                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="sf-button-secondary text-center">
                                        Criar conta
                                    </a>
                                @endif
                            </div>

                            <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ([
                                    ['title' => 'Agenda online', 'description' => 'Organize atendimentos, status e disponibilidade em tempo real.'],
                                    ['title' => 'Controle de equipe', 'description' => 'Gerencie profissionais, escalas, papéis e produção da operação.'],
                                    ['title' => 'Comissões automaticas', 'description' => 'Registre pagamentos e acompanhe comissões sem planilhas paralelas.'],
                                    ['title' => 'Link público de agendamento', 'description' => 'Receba novos agendamentos com uma jornada simples e pensada para celular.'],
                                ] as $benefit)
                                    <article class="rounded-[24px] border border-white/8 bg-[#223d69] p-5 shadow-[0_24px_40px_rgba(9,20,45,0.2)] transition duration-200 hover:-translate-y-0.5 hover:border-[#d4af37]/24 hover:shadow-[0_30px_50px_rgba(9,20,45,0.26)]">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-[#d4af37] ring-1 ring-[#d4af37]/15">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 2.75a.75.75 0 01.75.75v5.75h5.75a.75.75 0 010 1.5h-5.75v5.75a.75.75 0 01-1.5 0v-5.75H3.5a.75.75 0 010-1.5h5.75V3.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <h2 class="mt-4 text-lg font-semibold text-white">{{ $benefit['title'] }}</h2>
                                        <p class="mt-2 text-sm leading-6 text-[#c7d2e3]">{{ $benefit['description'] }}</p>
                                    </article>
                                @endforeach
                            </div>
                        </section>

                        <aside class="rounded-[28px] border border-white/10 bg-[#132746] p-6 shadow-[0_36px_60px_rgba(9,20,45,0.34)] sm:p-7">
                            <div class="rounded-[24px] border border-white/8 bg-[#223d69] p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d4af37]">Acessó rápido</p>
                                <h2 class="mt-4 text-2xl font-semibold text-white">Entre e comande sua operação</h2>
                                <p class="mt-3 text-sm leading-6 text-[#c7d2e3]">
                                    Use o StudioFlow para centralizar agenda, time, clientes, financeiro e o link público de agendamento.
                                </p>

                                <div class="mt-6 space-y-3">
                                    <a href="{{ route('login') }}" class="sf-button-primary flex w-full items-center justify-center">
                                        Entrar
                                    </a>

                                    @if (Route::has('register'))
                                        <a href="{{ route('register') }}" class="sf-button-secondary flex w-full items-center justify-center">
                                            Criar conta
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                <div class="rounded-[24px] border border-white/8 bg-[#223d69] px-5 py-4">
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">Tudo em um fluxo</p>
                                    <p class="mt-2 text-lg font-semibold text-white">Do agendamento ao pagamento</p>
                                </div>
                                <div class="rounded-[24px] border border-white/8 bg-[#223d69] px-5 py-4">
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#c7d2e3]">Experiência completa</p>
                                    <p class="mt-2 text-lg font-semibold text-white">Mobile-first para equipe e clientes</p>
                                </div>
                            </div>
                        </aside>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
