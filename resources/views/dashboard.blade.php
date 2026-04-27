<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-gray-500">
                {{ auth()->user()->company?->name ?? __('No company assigned') }}
            </p>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Dashboard') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-4">
                <section class="bg-white border border-gray-200 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <p class="text-sm font-medium text-gray-500">Agendamentos Hoje</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $appointmentsToday }}</p>
                        <p class="mt-2 text-sm text-gray-500">Compromissos ativos do dia.</p>
                    </div>
                </section>

                <section class="bg-white border border-gray-200 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <p class="text-sm font-medium text-gray-500">Clientes</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $clientsCount }}</p>
                        <p class="mt-2 text-sm text-gray-500">Base atual da sua empresa.</p>
                    </div>
                </section>

                <section class="bg-white border border-gray-200 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <p class="text-sm font-medium text-gray-500">Serviços</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $servicesCount }}</p>
                        <p class="mt-2 text-sm text-gray-500">Serviços cadastrados no StudioFlow.</p>
                    </div>
                </section>

                <section class="bg-white border border-gray-200 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <p class="text-sm font-medium text-gray-500">Receita do Mês</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">R$ {{ number_format($monthlyRevenue, 2, ',', '.') }}</p>
                        <p class="mt-2 text-sm text-gray-500">Estimativa baseada em atendimentos concluídos.</p>
                    </div>
                </section>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-[320px,1fr]">
                <section class="bg-white border border-gray-200 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Ações rápidas</h3>
                            <p class="mt-1 text-sm text-gray-500">Cadastre e organize sua operação.</p>
                        </div>

                        <div class="mt-6 grid gap-3">
                            <a href="{{ route('clients.create') }}" class="inline-flex items-center justify-center px-4 py-3 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition">
                                Novo Cliente
                            </a>
                            <a href="{{ route('services.create') }}" class="inline-flex items-center justify-center px-4 py-3 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                                Novo Serviço
                            </a>
                            <a href="{{ route('appointments.create') }}" class="inline-flex items-center justify-center px-4 py-3 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                                Novo Agendamento
                            </a>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-gray-200 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Próximos agendamentos</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __("You're logged in!") }}</p>
                        </div>
                        <a href="{{ route('appointments.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                            Ver agenda
                        </a>
                    </div>

                    @if ($upcomingAppointments->isEmpty())
                        <div class="p-6 text-sm text-gray-500">
                            Nenhum agendamento futuro encontrado.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Horário</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Cliente</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Serviço</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Profissional</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach ($upcomingAppointments as $appointment)
                                        <tr>
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                                {{ $appointment->start_time->format('d/m H:i') }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600">{{ $appointment->client->name }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-600">{{ $appointment->service->name }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-600">{{ $appointment->user->name }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-600">{{ __(ucfirst($appointment->status)) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

        </div>
    </div>
</x-app-layout>
