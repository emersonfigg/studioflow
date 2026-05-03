@php
    $serviceOptions = $services->map(fn ($service) => [
        'id' => $service->id,
        'name' => $service->name,
        'duration' => (int) $service->duration_minutes,
        'price' => (float) $service->price,
        'label' => $service->name.' · '.$service->duration_minutes.' min · R$ '.number_format((float) $service->price, 2, ',', '.'),
    ])->values();
    $productOptions = ($products ?? collect())->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name,
        'stock' => (int) $product->stock_quantity,
        'price' => (float) $product->price,
        'label' => $product->name.' · R$ '.number_format((float) $product->price, 2, ',', '.').' · Estoque '.$product->stock_quantity,
    ])->values();
    $initialServiceIds = collect(old('service_ids', $appointment?->bookedServices()->pluck('id')->all() ?? [$appointment?->service_id]))
        ->filter()
        ->map(fn ($serviceId) => (int) $serviceId)
        ->values();
@endphp

<div
    x-data="{
        creatingClient: false,
        clientErrors: {},
        clientMessage: '',
        clientHistory: null,
        loadingHistory: false,
        selectedServiceId: '',
        selectedProductId: '',
        selectedProductQuantity: 1,
        selectedServiceIds: @js($initialServiceIds),
        selectedProducts: @js(collect(old('product_items', []))->filter(fn ($item) => ! empty($item['product_id']))->values()),
        services: @js($serviceOptions),
        products: @js($productOptions),
        clientForm: {
            name: '',
            phone: '',
            birthday: '',
            notes: '',
        },
        money(value) {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
        },
        serviceById(id) {
            return this.services.find((service) => String(service.id) === String(id));
        },
        productById(id) {
            return this.products.find((product) => String(product.id) === String(id));
        },
        get selectedServices() {
            return this.selectedServiceIds.map((id) => this.serviceById(id)).filter(Boolean);
        },
        get normalizedProducts() {
            return this.selectedProducts
                .map((item) => ({
                    product_id: Number(item.product_id),
                    quantity: Math.max(1, Number(item.quantity || 1)),
                    product: this.productById(item.product_id),
                }))
                .filter((item) => item.product);
        },
        get serviceSubtotal() {
            return this.selectedServices.reduce((total, service) => total + Number(service.price || 0), 0);
        },
        get productSubtotal() {
            return this.normalizedProducts.reduce((total, item) => total + (Number(item.product.price || 0) * item.quantity), 0);
        },
        get totalDuration() {
            return this.selectedServices.reduce((total, service) => total + Number(service.duration || 0), 0);
        },
        get totalAmount() {
            return this.serviceSubtotal + this.productSubtotal;
        },
        addService() {
            if (! this.selectedServiceId || this.selectedServiceIds.includes(Number(this.selectedServiceId))) {
                return;
            }

            this.selectedServiceIds.push(Number(this.selectedServiceId));
            this.selectedServiceId = '';
        },
        removeService(id) {
            this.selectedServiceIds = this.selectedServiceIds.filter((serviceId) => Number(serviceId) !== Number(id));
        },
        addProduct() {
            const product = this.productById(this.selectedProductId);

            if (! product) {
                return;
            }

            const quantity = Math.max(1, Number(this.selectedProductQuantity || 1));
            const existing = this.selectedProducts.find((item) => Number(item.product_id) === Number(product.id));

            if (existing) {
                existing.quantity = Math.min(product.stock, Number(existing.quantity || 0) + quantity);
            } else {
                this.selectedProducts.push({ product_id: Number(product.id), quantity: Math.min(product.stock, quantity) });
            }

            this.selectedProductId = '';
            this.selectedProductQuantity = 1;
        },
        removeProduct(id) {
            this.selectedProducts = this.selectedProducts.filter((item) => Number(item.product_id) !== Number(id));
        },
        repeatLastAppointment() {
            if (! this.clientHistory || ! this.clientHistory.repeat_service_ids) {
                return;
            }

            this.selectedServiceIds = this.clientHistory.repeat_service_ids
                .map((id) => Number(id))
                .filter((id) => this.serviceById(id));
        },
        async loadClientHistory(clientId) {
            this.clientHistory = null;

            if (! clientId) {
                return;
            }

            this.loadingHistory = true;

            try {
                const response = await fetch(@js(route('appointments.client-history', ['client' => '__CLIENT__'])).replace('__CLIENT__', clientId), {
                    headers: { 'Accept': 'application/json' },
                });

                if (response.ok) {
                    this.clientHistory = await response.json();
                }
            } finally {
                this.loadingHistory = false;
            }
        },
        openClientModal() {
            this.clientErrors = {};
            this.clientMessage = '';
            this.$dispatch('open-modal', 'appointment-client-modal');
            this.$nextTick(() => this.$refs.inlineClientName?.focus());
        },
        closeClientModal() {
            this.$dispatch('close-modal', 'appointment-client-modal');
        },
        resetClientForm() {
            this.clientForm = {
                name: '',
                phone: '',
                birthday: '',
                notes: '',
            };
            this.clientErrors = {};
        },
        syncClientOption(client) {
            const select = this.$refs.clientSelect;

            if (! select) {
                return;
            }

            let option = [...select.options].find((item) => item.value === String(client.id));

            if (! option) {
                option = new Option(client.name, client.id, true, true);
                select.add(option);
            } else {
                option.text = client.name;
                option.selected = true;
            }

            select.value = String(client.id);
            select.dispatchEvent(new Event('change', { bubbles: true }));
        },
        async saveClient() {
            if (this.creatingClient) {
                return;
            }

            this.creatingClient = true;
            this.clientErrors = {};
            this.clientMessage = '';

            try {
                const response = await fetch(@js(route('clients.inline.store')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': @js(csrf_token()),
                    },
                    body: JSON.stringify(this.clientForm),
                });

                const payload = await response.json();

                if (! response.ok) {
                    if (response.status === 422 && payload.errors) {
                        this.clientErrors = payload.errors;
                        return;
                    }

                    this.clientErrors = {
                        general: [payload.message || 'Não foi possível salvar o cliente agora.'],
                    };

                    return;
                }

                this.syncClientOption(payload.client);
                this.clientMessage = payload.message;
                this.closeClientModal();
                this.resetClientForm();
            } catch (error) {
                this.clientErrors = {
                    general: ['Não foi possível salvar o cliente agora.'],
                };
            } finally {
                this.creatingClient = false;
            }
        },
        init() {
            if (this.$refs.clientSelect?.value) {
                this.loadClientHistory(this.$refs.clientSelect.value);
            }
        },
    }"
    class="space-y-6"
>
    <form method="POST" action="{{ $action }}" class="space-y-6">
        @csrf

        @if ($method !== 'POST')
            @method($method)
        @endif

        <input type="hidden" name="service_id" x-bind:value="selectedServiceIds[0] || ''">

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <section class="space-y-6">
                <section class="sf-card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="flex-1">
                            <x-input-label for="client_id" value="Cliente" />
                            <select id="client_id" x-ref="clientSelect" x-on:change="loadClientHistory($event.target.value)" name="client_id" class="sf-select mt-2 block w-full" required>
                                <option value="">Selecione um cliente</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((int) old('client_id', $appointment?->client_id) === $client->id)>
                                        {{ $client->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('client_id')" />
                            <p x-show="clientMessage" x-text="clientMessage" class="mt-2 text-sm font-medium text-[#d4af37]"></p>
                        </div>

                        <button
                            type="button"
                            x-on:click="openClientModal()"
                            class="inline-flex items-center justify-center rounded-xl border border-[#d4af37]/30 bg-[#d4af37]/10 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-[#f6e7b3] transition duration-150 hover:border-[#d4af37]/50 hover:bg-[#d4af37]/16"
                        >
                            + Novo cliente
                        </button>
                    </div>
                </section>

                <section class="sf-card p-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Serviços</p>
                        <h3 class="mt-2 text-lg font-semibold text-white">Serviços do atendimento</h3>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                        <select x-model="selectedServiceId" class="sf-select block w-full">
                            <option value="">Adicionar serviço</option>
                            <template x-for="service in services" :key="service.id">
                                <option x-bind:value="service.id" x-text="service.label"></option>
                            </template>
                        </select>
                        <button type="button" x-on:click="addService()" class="sf-button-primary">Adicionar serviço</button>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('service_ids')" />
                    <x-input-error class="mt-2" :messages="$errors->get('service_id')" />

                    <div class="mt-4 space-y-3">
                        <template x-for="(service, index) in selectedServices" :key="service.id">
                            <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-[#132746] px-4 py-3">
                                <div>
                                    <input type="hidden" x-bind:name="`service_ids[${index}]`" x-bind:value="service.id">
                                    <p class="text-sm font-semibold text-white" x-text="service.name"></p>
                                    <p class="mt-1 text-xs text-[#c7d2e3]">
                                        <span x-text="`${service.duration} min`"></span>
                                        <span> · </span>
                                        <span x-text="money(service.price)"></span>
                                    </p>
                                </div>
                                <button type="button" x-on:click="removeService(service.id)" class="sf-button-ghost px-3 py-2 text-xs">Remover</button>
                            </div>
                        </template>

                        <div x-show="selectedServices.length === 0" class="rounded-2xl border border-dashed border-white/15 px-4 py-5 text-sm text-[#c7d2e3]">
                            Adicione pelo menos um serviço para calcular duração, valor e disponibilidade.
                        </div>
                    </div>
                </section>

                <section class="sf-card p-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Produtos</p>
                        <h3 class="mt-2 text-lg font-semibold text-white">Extras opcionais da comanda</h3>
                        <p class="mt-1 text-sm text-[#c7d2e3]">Produtos entram na comanda, mas não alteram a duração do agendamento.</p>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_110px_auto]">
                        <select x-model="selectedProductId" class="sf-select block w-full">
                            <option value="">Adicionar produto</option>
                            <template x-for="product in products" :key="product.id">
                                <option x-bind:value="product.id" x-text="product.label"></option>
                            </template>
                        </select>
                        <input type="number" min="1" x-model="selectedProductQuantity" class="sf-input block w-full" aria-label="Quantidade">
                        <button type="button" x-on:click="addProduct()" class="sf-button-primary">Adicionar produto</button>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('product_items')" />

                    <div class="mt-4 space-y-3">
                        <template x-for="(item, index) in normalizedProducts" :key="item.product.id">
                            <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-[#132746] px-4 py-3">
                                <div>
                                    <input type="hidden" x-bind:name="`product_items[${index}][product_id]`" x-bind:value="item.product.id">
                                    <input type="hidden" x-bind:name="`product_items[${index}][quantity]`" x-bind:value="item.quantity">
                                    <p class="text-sm font-semibold text-white" x-text="item.product.name"></p>
                                    <p class="mt-1 text-xs text-[#c7d2e3]">
                                        <span x-text="`Qtd. ${item.quantity}`"></span>
                                        <span> · </span>
                                        <span x-text="money(item.product.price * item.quantity)"></span>
                                    </p>
                                </div>
                                <button type="button" x-on:click="removeProduct(item.product.id)" class="sf-button-ghost px-3 py-2 text-xs">Remover</button>
                            </div>
                        </template>

                        <div x-show="normalizedProducts.length === 0" class="rounded-2xl border border-dashed border-white/15 px-4 py-5 text-sm text-[#c7d2e3]">
                            Nenhum produto adicionado na comanda inicial.
                        </div>
                    </div>
                </section>

                <section class="sf-card p-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <x-input-label for="user_id" value="Profissional" />
                            <select id="user_id" name="user_id" class="sf-select mt-2 block w-full" required>
                                <option value="">Selecione um profissional</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected((int) old('user_id', $appointment?->user_id ?? auth()->id()) === $user->id)>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('user_id')" />
                        </div>

                        <div>
                            <x-input-label for="start_time" value="Data e hora" />
                            <x-text-input id="start_time" name="start_time" type="datetime-local" class="mt-2 block w-full" :value="old('start_time', optional($appointment?->start_time)->format('Y-m-d\TH:i'))" required />
                            <p class="mt-2 text-sm text-[#c7d2e3]">A disponibilidade será validada pela duração total dos serviços.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                        </div>
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" class="sf-select mt-2 block w-full" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status', $appointment?->status ?? 'scheduled') === $status)>
                                        {{ match ($status) {
                                            'scheduled' => 'Agendado',
                                            'confirmed' => 'Confirmado',
                                            'in_progress' => 'Em atendimento',
                                            'completed' => 'Concluído',
                                            'cancelled' => 'Cancelado',
                                            default => $status,
                                        } }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('status')" />
                        </div>

                        <div>
                            <x-input-label for="source" value="Origem" />
                            <select id="source" name="source" class="sf-select mt-2 block w-full" required>
                                @foreach ($sources as $source)
                                    <option value="{{ $source }}" @selected(old('source', $appointment?->source ?? 'internal') === $source)>
                                        {{ match ($source) {
                                            'internal' => 'Interno',
                                            'public_booking' => 'Agendamento público',
                                            'whatsapp' => 'WhatsApp',
                                            default => $source,
                                        } }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('source')" />
                        </div>
                    </div>

                    <div class="mt-5">
                        <x-input-label for="notes" value="Observações" />
                        <textarea id="notes" name="notes" rows="4" class="sf-input mt-2 block w-full">{{ old('notes', $appointment?->notes) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>
                </section>
            </section>

            <aside class="space-y-6">
                <section class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Resumo da comanda</p>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-[#c7d2e3]">Duração</dt>
                            <dd class="font-semibold text-white" x-text="`${totalDuration} min`"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-[#c7d2e3]">Serviços</dt>
                            <dd class="font-semibold text-white" x-text="money(serviceSubtotal)"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-[#c7d2e3]">Produtos</dt>
                            <dd class="font-semibold text-white" x-text="money(productSubtotal)"></dd>
                        </div>
                        <div class="border-t border-white/10 pt-3">
                            <div class="flex justify-between gap-4">
                                <dt class="font-semibold text-white">Total geral</dt>
                                <dd class="text-xl font-semibold text-[#d4af37]" x-text="money(totalAmount)"></dd>
                            </div>
                        </div>
                    </dl>
                </section>

                <section class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Histórico do cliente</p>
                    <div x-show="loadingHistory" class="mt-4 text-sm text-[#c7d2e3]">Carregando histórico...</div>
                    <div x-show="clientHistory && clientHistory.has_history" class="mt-4 space-y-4 text-sm">
                        <div>
                            <p class="text-[#c7d2e3]">Última visita</p>
                            <p class="font-semibold text-white" x-text="clientHistory?.last_visit || 'Sem visita concluída'"></p>
                        </div>
                        <div>
                            <p class="text-[#c7d2e3]">Últimos serviços</p>
                            <p class="font-semibold text-white" x-text="clientHistory?.last_services?.join(', ') || 'Nenhum serviço registrado'"></p>
                        </div>
                        <div>
                            <p class="text-[#c7d2e3]">Últimos produtos</p>
                            <p class="font-semibold text-white" x-text="clientHistory?.last_products?.join(', ') || 'Nenhum produto registrado'"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-3 py-3">
                                <p class="text-xs text-[#c7d2e3]">Total gasto</p>
                                <p class="mt-1 font-semibold text-white" x-text="money(clientHistory?.total_spent)"></p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-[#132746] px-3 py-3">
                                <p class="text-xs text-[#c7d2e3]">Ticket médio</p>
                                <p class="mt-1 font-semibold text-white" x-text="money(clientHistory?.average_ticket)"></p>
                            </div>
                        </div>
                        <div x-show="clientHistory?.notes">
                            <p class="text-[#c7d2e3]">Observações</p>
                            <p class="font-semibold text-white" x-text="clientHistory?.notes"></p>
                        </div>
                        <button type="button" x-on:click="repeatLastAppointment()" class="sf-button-ghost w-full">Repetir último atendimento</button>
                    </div>
                    <div x-show="clientHistory && ! clientHistory.has_history" class="mt-4 rounded-2xl border border-dashed border-white/15 px-4 py-5 text-sm text-[#c7d2e3]">
                        Este cliente ainda não possui histórico de atendimento.
                    </div>
                    <div x-show="! clientHistory && ! loadingHistory" class="mt-4 text-sm text-[#c7d2e3]">
                        Selecione um cliente para ver histórico, consumo e sugestões.
                    </div>
                </section>
            </aside>
        </div>

        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('appointments.index') }}" class="text-sm font-medium text-[#c7d2e3] transition hover:text-white">Cancelar</a>
            <x-primary-button>Salvar agendamento</x-primary-button>
        </div>
    </form>

    <x-modal name="appointment-client-modal" maxWidth="lg" focusable>
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#d4af37]">Clientes</p>
                    <h3 class="mt-2 text-2xl font-semibold text-white">Novo cliente</h3>
                    <p class="mt-2 text-sm text-[#c7d2e3]">Cadastre o cliente sem sair do novo agendamento.</p>
                </div>

                <button
                    type="button"
                    x-on:click="closeClientModal()"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-[#132746] text-[#c7d2e3] transition hover:border-[#d4af37]/30 hover:text-white"
                    aria-label="Fechar modal"
                >
                    <span class="text-lg leading-none">&times;</span>
                </button>
            </div>

            <form x-on:submit.prevent="saveClient()" class="mt-6 space-y-5">
                <div x-show="clientErrors.general" class="rounded-2xl border border-rose-300/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">
                    <span x-text="clientErrors.general ? clientErrors.general[0] : ''"></span>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="inline_client_name" value="Nome" />
                        <x-text-input id="inline_client_name" x-ref="inlineClientName" x-model="clientForm.name" type="text" class="mt-1 block w-full" required />
                        <p x-show="clientErrors.name" class="mt-2 text-sm text-rose-200" x-text="clientErrors.name ? clientErrors.name[0] : ''"></p>
                    </div>

                    <div>
                        <x-input-label for="inline_client_phone" value="Telefone" />
                        <x-text-input id="inline_client_phone" x-model="clientForm.phone" type="text" class="mt-1 block w-full" required />
                        <p x-show="clientErrors.phone" class="mt-2 text-sm text-rose-200" x-text="clientErrors.phone ? clientErrors.phone[0] : ''"></p>
                    </div>

                    <div>
                        <x-input-label for="inline_client_birthday" value="Aniversário" />
                        <x-text-input id="inline_client_birthday" x-model="clientForm.birthday" type="date" class="mt-1 block w-full" />
                        <p x-show="clientErrors.birthday" class="mt-2 text-sm text-rose-200" x-text="clientErrors.birthday ? clientErrors.birthday[0] : ''"></p>
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="inline_client_notes" value="Observações" />
                        <textarea id="inline_client_notes" x-model="clientForm.notes" rows="4" class="sf-input mt-1 block w-full"></textarea>
                        <p x-show="clientErrors.notes" class="mt-2 text-sm text-rose-200" x-text="clientErrors.notes ? clientErrors.notes[0] : ''"></p>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/8 bg-[#132746] px-4 py-4 text-sm text-[#c7d2e3]">
                    Se o telefone já existir nesta empresa, o StudioFlow reaproveita o cliente automaticamente.
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-secondary-button type="button" x-on:click="closeClientModal()">
                        Cancelar
                    </x-secondary-button>

                    <x-primary-button x-bind:disabled="creatingClient">
                        <span x-show="! creatingClient">Salvar cliente</span>
                        <span x-show="creatingClient">Salvando...</span>
                    </x-primary-button>
                </div>
            </form>
        </div>
    </x-modal>
</div>
