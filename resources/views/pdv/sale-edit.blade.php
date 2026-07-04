@extends('layouts.pdv')

@section('title', 'Editar venda PDV')

@section('content')
    <div class="space-y-4">
        <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] brand-text">Comanda #{{ $order->id }}</p>
                    <h1 class="mt-1 text-xl font-semibold text-[var(--text-main)]">Editar venda finalizada</h1>
                    <p class="mt-1 text-sm sf-text-muted">Corrija dados de cliente, profissional, pagamento e observacoes sem reabrir valores.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('pdv.sales') }}" class="sf-button-secondary">Voltar historico</a>
                    <a href="{{ route('pdv.sales.show', $order) }}" class="sf-button-secondary">Detalhes</a>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4 sm:p-5">
            <form method="POST" action="{{ route('pdv.sales.update', $order) }}" class="grid gap-4 lg:grid-cols-2">
                @csrf
                @method('PATCH')

                <div>
                    <label for="client_id" class="text-xs font-semibold text-[var(--text-main)]">Cliente</label>
                    <select id="client_id" name="client_id" class="sf-select mt-1 block w-full" required>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id', $order->client_id) === (int) $client->id)>
                                {{ $client->name }}{{ $client->phone ? ' - '.$client->phone : '' }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('client_id')" />
                </div>

                <div>
                    <label for="professional_id" class="text-xs font-semibold text-[var(--text-main)]">Profissional</label>
                    <select id="professional_id" name="professional_id" class="sf-select mt-1 block w-full" required>
                        @foreach ($professionals as $professional)
                            <option value="{{ $professional->id }}" @selected((int) old('professional_id', $order->professional_id) === (int) $professional->id)>
                                {{ $professional->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('professional_id')" />
                </div>

                <div>
                    <label for="payment_method" class="text-xs font-semibold text-[var(--text-main)]">Forma de pagamento</label>
                    <select id="payment_method" name="payment_method" class="sf-select mt-1 block w-full" required>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method', $currentMethod) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('payment_method')" />
                </div>

                <div>
                    <label for="correction_reason" class="text-xs font-semibold text-[var(--text-main)]">Motivo da edicao</label>
                    <textarea id="correction_reason" name="correction_reason" rows="3" class="sf-input mt-1 block w-full" required>{{ old('correction_reason') }}</textarea>
                    <x-input-error class="mt-1" :messages="$errors->get('correction_reason')" />
                </div>

                <div class="lg:col-span-2">
                    <label for="notes" class="text-xs font-semibold text-[var(--text-main)]">Observacoes</label>
                    <textarea id="notes" name="notes" rows="4" class="sf-input mt-1 block w-full">{{ old('notes', $currentNotes) }}</textarea>
                    <x-input-error class="mt-1" :messages="$errors->get('notes')" />
                </div>

                <div class="rounded-2xl border border-amber-300/25 bg-amber-500/10 p-4 text-sm text-amber-50 lg:col-span-2">
                    Itens bloqueados nesta versao: alterar produtos, servicos ou assinaturas de uma venda concluida exige reprocessar caixa, estoque, comissoes e creditos de assinatura. Use cancelamento/estorno para correcoes operacionais de itens.
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-2">
                    <a href="{{ route('pdv.sales.show', $order) }}" class="sf-button-secondary justify-center">Cancelar</a>
                    <button type="submit" class="sf-button-primary">Salvar edicao</button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-white/10 bg-[var(--input-bg)] p-4 sm:p-5">
            <h2 class="text-sm font-semibold uppercase tracking-[0.16em] brand-text">Itens atuais</h2>
            <div class="mt-3 space-y-2">
                @foreach ($order->items as $item)
                    <div class="rounded-xl border border-white/10 bg-[var(--app-shell-bg)] px-3 py-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-semibold text-[var(--text-main)]">{{ $item->description }}</p>
                            <p class="brand-text">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</p>
                        </div>
                        <p class="mt-1 text-xs sf-text-muted">Qtd {{ $item->quantity }} - Unit R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
