<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">
                    {{ auth()->user()->company?->name ?? 'Nenhuma empresa vinculada' }}
                </p>
                <h2 class="text-xl font-semibold leading-tight text-gray-900">
                    Painel
                </h2>
            </div>

            <p class="text-sm text-gray-500">
                Visão executiva da operação de hoje.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 sm:px-6 lg:px-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="border border-gray-200 bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Agendamentos Hoje</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $appointmentsToday }}</p>
                    <p class="mt-2 text-sm text-gray-500">Total do dia, excluindo cancelados.</p>
                </article>

                <article class="border border-gray-200 bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Próximos Atendimentos</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $upcomingAttendances }}</p>
                    <p class="mt-2 text-sm text-gray-500">Compromissos futuros já confirmados na agenda.</p>
                </article>

                <article class="border border-gray-200 bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Clientes</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $clientsCount }}</p>
                    <p class="mt-2 text-sm text-gray-500">Base cadastrada da empresa.</p>
                </article>

                <article class="border border-gray-200 bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Serviços</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $servicesCount }}</p>
                    <p class="mt-2 text-sm text-gray-500">Serviços ativos e operacionais.</p>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
                <aside class="border border-gray-200 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Link público de agendamento</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Compartilhe este acesso com clientes para receber pedidos sem login.
                            </p>
                        </div>
                    </div>

                    @if ($publicBookingUrl)
                        <div class="mt-5 rounded-lg border border-indigo-100 bg-indigo-50 p-4" x-data="{ copied: false }">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Link da empresa</p>
                            <p class="mt-2 break-all text-sm text-indigo-950">{{ $publicBookingUrl }}</p>

                            <button
                                type="button"
                                class="mt-4 inline-flex w-full items-center justify-center rounded-md bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200 transition hover:bg-indigo-100"
                                x-on:click="
                                    navigator.clipboard.writeText('{{ $publicBookingUrl }}');
                                    copied = true;
                                    setTimeout(() => copied = false, 2000);
                                "
                            >
                                <span x-show="! copied">Copiar link</span>
                                <span x-show="copied">Link copiado</span>
                            </button>
                        </div>
                    @else
                        <div class="mt-5 rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-500">
                            Nenhuma empresa vinculada para gerar o link público.
                        </div>
                    @endif

                    <div class="mt-6 grid gap-3">
                        <a href="{{ route('appointments.index') }}" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-gray-800">
                            Ver agenda
                        </a>
                        <a href="{{ route('appointments.create') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                            Novo agendamento
                        </a>
                    </div>
                </aside>

                <section class="overflow-hidden border border-gray-200 bg-white shadow-sm sm:rounded-lg">
                    <div class="flex flex-col gap-2 border-b border-gray-200 px-6 py-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Últimos agendamentos do dia</h3>
                            <p class="mt-1 text-sm text-gray-500">Acompanhe a movimentação mais recente da agenda de hoje.</p>
                        </div>
                        <p class="text-sm text-gray-500">{{ now()->format('d/m/Y') }}</p>
                    </div>

                    @if ($todayAppointments->isEmpty())
                        <div class="px-6 py-10 text-sm text-gray-500">
                            Nenhum agendamento registrado hoje.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Horário</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Serviço</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Profissional</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach ($todayAppointments as $appointment)
                                        <tr>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {{ $appointment->start_time->format('H:i') }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600">{{ $appointment->client->name }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-600">{{ $appointment->service->name }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-600">{{ $appointment->user->name }}</td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $appointment->statusBadgeClasses() }}">
                                                    {{ $appointment->statusLabel() }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </section>
        </div>
    </div>
</x-app-layout>
