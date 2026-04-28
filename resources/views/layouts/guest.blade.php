<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>StudioFlow</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#1b335b] font-sans antialiased text-white">
        <div class="min-h-screen bg-[#1b335b] bg-[radial-gradient(circle_at_top,_rgba(212,175,55,0.14),_transparent_28%),linear-gradient(180deg,_#223d69_0%,_#1b335b_38%,_#132746_100%)]">
            <div class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-4 py-8 sm:px-6 lg:px-8">
                <div class="grid items-center gap-8 lg:grid-cols-[minmax(0,1fr)_440px]">
                    <section class="hidden lg:block">
                        <div class="max-w-xl">
                            <div class="inline-flex items-center gap-3 rounded-full border border-[#d4af37]/20 bg-[#132746] px-4 py-2 text-sm text-[#c7d2e3] shadow-[0_18px_38px_rgba(7,17,38,0.18)]">
                                <x-application-logo class="h-8 w-8 text-[#d4af37]" />
                                <span class="font-semibold">StudioFlow</span>
                            </div>

                            <h1 class="mt-6 text-4xl font-semibold tracking-tight text-white">
                                Agenda inteligente para barbearias, saloes e estetica
                            </h1>

                            <p class="mt-4 max-w-lg text-base leading-7 text-[#c7d2e3]">
                                Organize atendimentos, acompanhe clientes e compartilhe seu link publico de agendamento em uma experiencia elegante e profissional.
                            </p>

                            <div class="mt-8 grid max-w-lg gap-4 sm:grid-cols-2">
                                <div class="rounded-[24px] border border-white/8 bg-[#223d69] px-5 py-4 shadow-[0_18px_40px_rgba(7,17,38,0.18)]">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Operacao</p>
                                    <p class="mt-2 text-lg font-semibold text-white">Agenda, equipe e clientes no mesmo fluxo</p>
                                </div>

                                <div class="rounded-[24px] border border-white/8 bg-[#223d69] px-5 py-4 shadow-[0_18px_40px_rgba(7,17,38,0.18)]">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Conversao</p>
                                    <p class="mt-2 text-lg font-semibold text-white">Autoagendamento e financeiro sem trocar de sistema</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="w-full overflow-hidden rounded-[28px] border border-white/10 bg-[#223d69] shadow-[0_30px_70px_rgba(7,17,38,0.32)]">
                        <div class="border-b border-white/10 px-6 py-6 sm:px-8">
                            <a href="/" class="inline-flex items-center gap-3">
                                <x-application-logo class="h-11 w-11 text-[#d4af37]" />
                                <div>
                                    <p class="text-lg font-semibold text-white">StudioFlow</p>
                                    <p class="text-sm text-[#c7d2e3]">Agenda inteligente para barbearias, saloes e estetica</p>
                                </div>
                            </a>
                        </div>

                        <div class="px-6 py-6 sm:px-8 sm:py-8">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
