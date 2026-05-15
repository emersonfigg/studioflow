<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Comanda</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--text-main)]">
                    Atendimento de {{ $appointment->client->name }}
                </h2>
                <p class="mt-2 text-sm sf-text-muted">
                    {{ $appointment->start_time->format('d/m/Y H:i') }} · {{ $appointment->user->name }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('appointments.show', $appointment) }}" class="sf-button-ghost">Voltar</a>
                @if ($order->isOpen())
                    <a href="#fechamento" class="sf-button-primary">Fechar comanda</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="space-y-6">
            @if (isset($clientRecommendations) && $clientRecommendations->isNotEmpty())
                @include('partials.client-opportunities', [
                    'recommendations' => $clientRecommendations,
                    'title' => 'Oportunidades para este cliente',
                    'subtitle' => 'Use estas sugestões antes de iniciar o atendimento.',
                ])
            @endif

            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">Itens da comanda</h3>
                    <p class="mt-1 text-sm sf-text-muted">Serviços adicionais não alteram a agenda automaticamente.</p>
                </div>

                <div class="divide-y divide-white/10">
                    @forelse ($order->items as $item)
                        <div class="grid gap-4 px-5 py-4 md:grid-cols-[minmax(0,1fr)_120px_120px_auto] md:items-center">
                            <div>
                                <p class="text-sm font-semibold text-[var(--text-main)]">{{ $item->description }}</p>
                                <p class="mt-1 text-xs sf-text-muted">
                                    {{ $item->type === 'service' ? 'Serviço' : 'Produto' }}
                                    @if ($item->professional)
                                        · {{ $item->professional->name }}
                                    @endif
                                    @if ($item->type === 'product' && $item->seller)
                                        · Vendedor: {{ $item->seller->name }}
                                    @endif
                                </p>
                            </div>
                            <p class="text-sm sf-text-muted">Qtd. {{ $item->quantity }}</p>
                            <p class="text-sm font-semibold brand-text">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</p>
                            <div>
                                @if ($order->isOpen())
                                    <form method="POST" action="{{ route('service-orders.items.destroy', [$order, $item]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="sf-button-ghost px-3 py-2 text-xs">Remover</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-sm sf-text-muted">Nenhum item na comanda.</div>
                    @endforelse
                </div>
            </section>

            @if ($order->isOpen())
                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="sf-card p-5">
                        <h3 class="text-base font-semibold text-[var(--text-main)]">Adicionar serviço</h3>
                        <form method="POST" action="{{ route('service-orders.services.store', $order) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="service_id" value="Serviço" />
                                <select id="service_id" name="service_id" class="sf-select mt-2 block w-full" required>
                                    <option value="">Selecione</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}">{{ $service->name }} · R$ {{ number_format((float) $service->price, 2, ',', '.') }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
                            </div>
                            <div>
                                <x-input-label for="professional_id" value="Profissional" />
                                <select id="professional_id" name="professional_id" class="sf-select mt-2 block w-full">
                                    <option value="">Usar profissional principal</option>
                                    @foreach ($professionals as $professional)
                                        <option value="{{ $professional->id }}">{{ $professional->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-primary-button>Adicionar serviço</x-primary-button>
                        </form>
                    </section>

                    <section class="sf-card p-5">
                        <h3 class="text-base font-semibold text-[var(--text-main)]">Adicionar produto</h3>
                        <form method="POST" action="{{ route('service-orders.products.store', $order) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="product_id" value="Produto" />
                                <select id="product_id" name="product_id" class="sf-select mt-2 block w-full" required>
                                    <option value="">Selecione</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" data-commission="{{ $product->hasCommission() ? '1' : '0' }}">
                                            {{ $product->name }} · R$ {{ number_format((float) $product->price, 2, ',', '.') }} · Estoque {{ $product->stock_quantity }}{{ $product->hasCommission() ? ' · Comissão' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('product_id')" />
                            </div>
                            <div>
                                <x-input-label for="quantity" value="Quantidade" />
                                <x-text-input id="quantity" name="quantity" type="number" min="1" value="1" class="mt-2 block w-full" required />
                            </div>
                            <div>
                                <x-input-label for="seller_id" value="Vendedor responsável" />
                                <select id="seller_id" name="seller_id" class="sf-select mt-2 block w-full">
                                    <option value="">— Profissional do atendimento —</option>
                                    @foreach ($professionals as $professional)
                                        <option value="{{ $professional->id }}" @selected(old('seller_id', $order->professional_id) == $professional->id)>{{ $professional->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs sf-text-muted">Obrigatório quando o produto tem comissão configurada.</p>
                                <x-input-error class="mt-2" :messages="$errors->get('seller_id')" />
                            </div>
                            <x-primary-button>Adicionar produto</x-primary-button>
                        </form>
                    </section>
                </div>
            @endif
        </section>

        <aside class="space-y-6">
            <section class="sf-card p-5">
                <h3 class="text-base font-semibold text-[var(--text-main)]">Resumo</h3>
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="sf-text-muted">Serviços</dt>
                        <dd class="font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $order->subtotal_services, 2, ',', '.') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="sf-text-muted">Produtos</dt>
                        <dd class="font-semibold text-[var(--text-main)]">R$ {{ number_format((float) $order->subtotal_products, 2, ',', '.') }}</dd>
                    </div>
                    <div class="border-t border-white/10 pt-3">
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-[var(--text-main)]">Total</dt>
                            <dd class="text-xl font-semibold brand-text">R$ {{ number_format((float) $order->total, 2, ',', '.') }}</dd>
                        </div>
                    </div>
                    <div class="pt-2">
                        <dt class="sf-text-muted">Status</dt>
                        <dd class="mt-1 font-semibold text-[var(--text-main)]">{{ $order->status === 'paid' ? 'Paga' : 'Aberta' }}</dd>
                    </div>
                </dl>
            </section>

            @if ($order->isOpen())
                <section id="fechamento" class="sf-card p-5">
                    <h3 class="text-base font-semibold text-[var(--text-main)]">Fechamento</h3>
                    <p class="mt-2 text-sm sf-text-muted">O estoque de produtos será baixado somente ao fechar a comanda.</p>
                    <form method="POST" action="{{ route('service-orders.close', $order) }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="payment_method" value="Forma de pagamento" />
                            <select id="payment_method" name="payment_method" class="sf-select mt-2 block w-full" required>
                                @foreach ($paymentMethods as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
                        </div>
                        <div>
                            <x-input-label for="notes" value="Observações" />
                            <textarea id="notes" name="notes" rows="3" class="sf-input mt-2 block w-full"></textarea>
                        </div>
                        <x-primary-button>Confirmar pagamento</x-primary-button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
