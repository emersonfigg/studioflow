@php
    $serviceOptions = $services->map(fn ($service) => [
        'id' => $service->id,
        'name' => $service->name,
        'duration' => (int) $service->duration_minutes,
        'price' => (float) $service->price,
        'label' => $service->name.' · '.$service->duration_minutes.' min · R$ '.number_format((float) $service->price, 2, ',', '.'),
        'needle' => strtolower($service->name.' '.$service->duration_minutes),
    ])->values();
    $productOptions = ($products ?? collect())->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name,
        'stock' => (float) $product->stock_quantity,
        'track_stock' => (bool) $product->track_stock,
        'price' => (float) $product->price,
        'label' => $product->name.' · R$ '.number_format((float) $product->price, 2, ',', '.').' · Estoque '.$product->stock_quantity,
        'needle' => strtolower($product->name),
    ])->values();
    $clientPickerRows = $clients->map(fn ($c) => [
        'id' => $c->id,
        'label' => ($c->client_code ?? '-').' · '.$c->name.' · '.$c->phone.($c->cpf ? ' · CPF '.$c->cpf : ''),
        'needle' => strtolower(trim(($c->client_code ?? '').' '.$c->name.' '.$c->phone.' '.($c->cpf ?? ''))),
    ])->values();
    $professionalPickerRows = $users->map(fn ($u) => [
        'id' => $u->id,
        'label' => $u->name,
        'needle' => strtolower($u->name),
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
        smartSlots: [],
        smartSlotsLoading: false,
        smartSlotsError: '',
        smartSlotsUrl: @js($smartSlotsUrl ?? route('appointments.smart-slots')),
        ignoreAppointmentId: @js($appointment->id ?? null),
        selectedServiceId: '',
        selectedProductId: '',
        selectedProductQuantity: 1,
        selectedServiceIds: @js($initialServiceIds),
        selectedProducts: @js(collect(old('product_items', []))->filter(fn ($item) => ! empty($item['product_id']))->values()),
        services: @js($serviceOptions),
        products: @js($productOptions),
        clientRows: @js($clientPickerRows),
        professionalRows: @js($professionalPickerRows),
        clientPickerQuery: '',
        professionalPickerQuery: '',
        servicePickerQuery: '',
        productPickerQuery: '',
        pickedClientLabel: '',
        pickedProfessionalLabel: '',
        clientForm: {
            name: '',
            phone: '',
            birthday: '',
            notes: '',
        },
        money(value) {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
        },
        async fetchSmartSlots() {
            const startInput = document.getElementById('start_time');
            const startVal = startInput?.value || '';
            const userId = this.$refs.professionalSelect?.value || '';

            if (! startVal || ! userId || this.selectedServiceIds.length === 0) {
                this.smartSlots = [];
                this.smartSlotsError = '';

                return;
            }

            const date = startVal.slice(0, 10);
            const params = new URLSearchParams();
            params.set('user_id', userId);
            params.set('date', date);
            this.selectedServiceIds.forEach((id) => params.append('service_ids[]', String(id)));
            if (this.ignoreAppointmentId) {
                params.set('ignore_appointment_id', String(this.ignoreAppointmentId));
            }

            this.smartSlotsLoading = true;
            this.smartSlotsError = '';

            try {
                const res = await fetch(`${this.smartSlotsUrl}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (! res.ok) {
                    throw new Error('bad status');
                }
                const body = await res.json();
                this.smartSlots = Array.isArray(body.slots) ? body.slots : [];
            } catch (e) {
                this.smartSlots = [];
                this.smartSlotsError = 'Não foi possível carregar sugestões de horário.';
            } finally {
                this.smartSlotsLoading = false;
            }
        },
        applySmartSlot(time) {
            const startInput = document.getElementById('start_time');
            if (! startInput) {
                return;
            }
            const day = (startInput.value || '').slice(0, 10);
            if (! day) {
                return;
            }
            startInput.value = `${day}T${time}`;
            startInput.dispatchEvent(new Event('input', { bubbles: true }));
            startInput.dispatchEvent(new Event('change', { bubbles: true }));
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
        get filteredClientRows() {
            const q = this.clientPickerQuery.trim().toLowerCase();
            const rows = this.clientRows;

            if (! q) {
                return rows.slice(0, 50);
            }

            return rows.filter((r) => r.needle.includes(q)).slice(0, 60);
        },
        get filteredProfessionalRows() {
            const q = this.professionalPickerQuery.trim().toLowerCase();
            const rows = this.professionalRows;

            if (! q) {
                return rows.slice(0, 40);
            }

            return rows.filter((r) => r.needle.includes(q)).slice(0, 50);
        },
        get filteredServicesPicker() {
            const q = this.servicePickerQuery.trim().toLowerCase();
            const rows = this.services;

            if (! q) {
                return rows.slice(0, 40);
            }

            return rows.filter((s) => (s.needle && s.needle.includes(q)) || String(s.name || '').toLowerCase().includes(q) || String(s.label || '').toLowerCase().includes(q)).slice(0, 50);
        },
        get filteredProductsPicker() {
            const q = this.productPickerQuery.trim().toLowerCase();
            const rows = this.products;

            if (! q) {
                return rows.slice(0, 40);
            }

            return rows.filter((p) => (p.needle && p.needle.includes(q)) || String(p.name || '').toLowerCase().includes(q) || String(p.label || '').toLowerCase().includes(q)).slice(0, 50);
        },
        pickClient(row) {
            const select = this.$refs.clientSelect;

            if (! select) {
                return;
            }

            select.value = String(row.id);
            select.dispatchEvent(new Event('change', { bubbles: true }));
            this.pickedClientLabel = row.label;
            this.clientPickerQuery = '';
        },
        pickProfessional(row) {
            const select = this.$refs.professionalSelect;

            if (! select) {
                return;
            }

            select.value = String(row.id);
            select.dispatchEvent(new Event('change', { bubbles: true }));
            this.pickedProfessionalLabel = row.label;
            this.professionalPickerQuery = '';
            this.$nextTick(() => this.fetchSmartSlots());
        },
        addService() {
            if (! this.selectedServiceId || this.selectedServiceIds.includes(Number(this.selectedServiceId))) {
                return;
            }

            this.selectedServiceIds.push(Number(this.selectedServiceId));
            this.selectedServiceId = '';
            this.servicePickerQuery = '';
            this.$nextTick(() => this.fetchSmartSlots());
        },
        removeService(id) {
            this.selectedServiceIds = this.selectedServiceIds.filter((serviceId) => Number(serviceId) !== Number(id));
            this.$nextTick(() => this.fetchSmartSlots());
        },
        addProduct() {
            const product = this.productById(this.selectedProductId);

            if (! product) {
                return;
            }

            const quantity = Math.max(1, Number(this.selectedProductQuantity || 1));
            const existing = this.selectedProducts.find((item) => Number(item.product_id) === Number(product.id));

            if (existing) {
                const maxQty = existing.product.track_stock ? Number(existing.product.stock || 0) : 999999;
                existing.quantity = Math.min(maxQty, Number(existing.quantity || 0) + quantity);
            } else {
                const maxQty = product.track_stock ? Number(product.stock || 0) : 999999;
                this.selectedProducts.push({ product_id: Number(product.id), quantity: Math.min(maxQty, quantity) });
            }

            this.selectedProductId = '';
            this.selectedProductQuantity = 1;
            this.productPickerQuery = '';
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
            const label = `${client.client_code ?? '-'} · ${client.name}${client.phone ? ' · ' + client.phone : ''}`;
            const needle = [client.client_code, client.name, client.phone].filter(Boolean).join(' ').toLowerCase();

            if (! this.clientRows.some((r) => Number(r.id) === Number(client.id))) {
                this.clientRows.push({ id: client.id, label, needle });
            }

            const select = this.$refs.clientSelect;

            if (! select) {
                return;
            }

            let option = [...select.options].find((item) => item.value === String(client.id));

            if (! option) {
                option = new Option(label, client.id, true, true);
                select.add(option);
            } else {
                option.text = label;
                option.selected = true;
            }

            select.value = String(client.id);
            this.pickedClientLabel = label;
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
            const cs = this.$refs.clientSelect;

            if (cs?.value) {
                const opt = [...cs.options].find((o) => o.value === String(cs.value));
                this.pickedClientLabel = opt?.text?.trim() || '';
                this.loadClientHistory(cs.value);
            }

            const ps = this.$refs.professionalSelect;

            if (ps?.value) {
                const opt = [...ps.options].find((o) => o.value === String(ps.value));
                this.pickedProfessionalLabel = opt?.text?.trim() || '';
            }
        },
    }"
    x-init="$watch('selectedServiceIds', () => $nextTick(() => fetchSmartSlots())); $nextTick(() => fetchSmartSlots())"
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
                        <div class="min-w-0 flex-1 space-y-2">
                            <x-input-label for="client-search" value="Cliente" />
                            <input
                                id="client-search"
                                type="search"
                                autocomplete="off"
                                x-model="clientPickerQuery"
                                class="sf-input mt-2 block w-full"
                                placeholder="Digite nome, código ou telefone para buscar…"
                            >
                            <div
                                x-cloak
                                x-show="clientPickerQuery.trim().length > 0 && filteredClientRows.length > 0"
                                class="max-h-52 overflow-y-auto overscroll-contain rounded-xl border border-white/10 bg-[var(--input-bg)] shadow-inner"
                            >
                                <template x-for="row in filteredClientRows" :key="row.id">
                                    <button
                                        type="button"
                                        class="flex w-full flex-col border-b border-white/5 px-4 py-3 text-left text-sm text-[var(--text-main)] transition hover:bg-white/5 last:border-b-0"
                                        x-on:click="pickClient(row)"
                                    >
                                        <span class="font-semibold" x-text="row.label"></span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="clientPickerQuery.trim().length > 0 && clientRows.length > 0 && filteredClientRows.length === 0" x-cloak class="rounded-xl border border-dashed border-white/15 px-4 py-3 text-sm text-rose-100/90">
                                Nenhum cliente encontrado para esta busca.
                            </p>
                            <p x-show="pickedClientLabel" class="text-sm sf-text-muted">
                                <span class="font-semibold brand-text">Selecionado:</span>
                                <span x-text="pickedClientLabel" class="text-[var(--text-main)]"></span>
                            </p>
                            <select
                                id="client_id"
                                x-ref="clientSelect"
                                x-on:change="pickedClientLabel = $event.target.value ? ([...$event.target.options].find((o) => o.value === $event.target.value)?.text?.trim() ?? '') : ''; loadClientHistory($event.target.value)"
                                name="client_id"
                                class="sr-only"
                                tabindex="-1"
                                required
                                aria-hidden="true"
                            >
                                <option value="">Selecione um cliente</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((int) old('client_id', $appointment?->client_id) === $client->id)>
                                        {{ $client->client_code ?? '-' }} · {{ $client->name }} · {{ $client->phone }}{{ $client->cpf ? ' · CPF '.$client->cpf : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('client_id')" />
                            <p x-show="clientMessage" x-text="clientMessage" class="mt-2 text-sm font-medium brand-text"></p>
                        </div>

                        <button
                            type="button"
                            x-on:click="openClientModal()"
                            class="inline-flex items-center justify-center rounded-xl border border-[color-mix(in_srgb,var(--brand-primary)_30%,transparent)] bg-[var(--brand-primary)]/10 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-[var(--text-main)] transition duration-150 hover:border-[color-mix(in_srgb,var(--brand-primary)_50%,transparent)] hover:bg-[var(--brand-primary)]/16"
                        >
                            + Novo cliente
                        </button>
                    </div>
                </section>

                <section class="sf-card p-5 transition @error('service_ids') ring-2 ring-rose-400/60 ring-offset-2 ring-offset-[var(--app-shell-bg)] @enderror">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Serviços</p>
                        <h3 class="mt-2 text-lg font-semibold text-[var(--text-main)]">Serviços do atendimento</h3>
                    </div>

                    <div class="mt-5 space-y-3">
                        <label for="service-picker-search" class="text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Buscar serviço</label>
                        <input
                            id="service-picker-search"
                            type="search"
                            autocomplete="off"
                            x-model="servicePickerQuery"
                            class="sf-input block w-full"
                            placeholder="Digite para filtrar serviços…"
                        >
                        <div
                            x-cloak
                            x-show="filteredServicesPicker.length > 0"
                            class="max-h-52 overflow-y-auto overscroll-contain rounded-xl border border-white/10 bg-[var(--input-bg)]"
                        >
                            <template x-for="service in filteredServicesPicker" :key="service.id">
                                <button
                                    type="button"
                                    class="flex w-full flex-col border-b border-white/5 px-4 py-3 text-left transition last:border-b-0"
                                    :class="String(selectedServiceId) === String(service.id) ? 'bg-[var(--brand-primary)]/15 ring-1 ring-inset ring-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)]' : 'hover:bg-white/5'"
                                    x-on:click="selectedServiceId = String(service.id)"
                                >
                                    <span class="text-sm font-semibold text-[var(--text-main)]" x-text="service.name"></span>
                                    <span class="mt-1 text-xs sf-text-muted" x-text="service.label"></span>
                                </button>
                            </template>
                        </div>
                        <p x-show="servicePickerQuery.trim().length > 0 && services.length > 0 && filteredServicesPicker.length === 0" x-cloak class="rounded-xl border border-dashed border-white/15 px-4 py-3 text-sm sf-text-muted">
                            Nenhum serviço encontrado para este filtro.
                        </p>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="min-w-0 flex-1 text-sm sf-text-muted" x-show="selectedServiceId">
                                <span class="font-semibold brand-text">Pré-selecionado:</span>
                                <span x-text="serviceById(selectedServiceId)?.label || ''"></span>
                            </p>
                            <button type="button" x-on:click="addService()" class="sf-button-primary shrink-0">Adicionar serviço</button>
                        </div>
                    </div>

                    @error('service_ids')
                        <p class="mt-3 rounded-xl border border-rose-400/35 bg-rose-950/35 px-4 py-3 text-sm text-rose-100">{{ $message }}</p>
                    @enderror
                    <x-input-error class="mt-2" :messages="$errors->get('service_ids.*')" />
                    <x-input-error class="mt-2" :messages="$errors->get('service_id')" />

                    <div class="mt-4 space-y-3">
                        <template x-for="sid in selectedServiceIds" :key="String(sid)">
                            <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-3">
                                <input type="hidden" name="service_ids[]" x-bind:value="sid">
                                <div class="min-w-0 flex-1">
                                    <div x-show="serviceById(sid)">
                                        <p class="text-sm font-semibold text-[var(--text-main)]" x-text="serviceById(sid) ? serviceById(sid).name : ''"></p>
                                        <p class="mt-1 text-xs sf-text-muted">
                                            <span x-text="serviceById(sid) ? `${serviceById(sid).duration} min` : ''"></span>
                                            <span> · </span>
                                            <span x-text="serviceById(sid) ? money(serviceById(sid).price) : ''"></span>
                                        </p>
                                    </div>
                                    <p x-show="! serviceById(sid)" class="text-sm text-amber-200">Serviço indisponível na lista (#<span x-text="sid"></span>). Remova e adicione novamente.</p>
                                </div>
                                <button type="button" x-on:click="removeService(sid)" class="sf-button-ghost shrink-0 px-3 py-2 text-xs">Remover</button>
                            </div>
                        </template>

                        <div x-show="selectedServiceIds.length === 0" class="rounded-2xl border border-dashed border-white/15 px-4 py-5 text-sm sf-text-muted">
                            Adicione pelo menos um serviço para calcular duração, valor e disponibilidade.
                        </div>
                    </div>
                </section>

                <section class="sf-card p-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Produtos</p>
                        <h3 class="mt-2 text-lg font-semibold text-[var(--text-main)]">Extras opcionais da comanda</h3>
                        <p class="mt-1 text-sm sf-text-muted">Produtos entram na comanda, mas não alteram a duração do agendamento.</p>
                    </div>

                    <div class="mt-5 space-y-3">
                        <label for="product-picker-search" class="text-xs font-semibold uppercase tracking-[0.18em] sf-text-muted">Buscar produto</label>
                        <input
                            id="product-picker-search"
                            type="search"
                            autocomplete="off"
                            x-model="productPickerQuery"
                            class="sf-input block w-full"
                            placeholder="Digite para filtrar produtos…"
                        >
                        <div
                            x-cloak
                            x-show="filteredProductsPicker.length > 0"
                            class="max-h-52 overflow-y-auto overscroll-contain rounded-xl border border-white/10 bg-[var(--input-bg)]"
                        >
                            <template x-for="product in filteredProductsPicker" :key="product.id">
                                <button
                                    type="button"
                                    class="flex w-full flex-col border-b border-white/5 px-4 py-3 text-left transition last:border-b-0"
                                    :class="String(selectedProductId) === String(product.id) ? 'bg-[var(--brand-primary)]/15 ring-1 ring-inset ring-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)]' : 'hover:bg-white/5'"
                                    x-on:click="selectedProductId = String(product.id)"
                                >
                                    <span class="text-sm font-semibold text-[var(--text-main)]" x-text="product.name"></span>
                                    <span class="mt-1 text-xs sf-text-muted" x-text="product.label"></span>
                                </button>
                            </template>
                        </div>
                        <p x-show="productPickerQuery.trim().length > 0 && products.length > 0 && filteredProductsPicker.length === 0" x-cloak class="rounded-xl border border-dashed border-white/15 px-4 py-3 text-sm sf-text-muted">
                            Nenhum produto encontrado para este filtro.
                        </p>
                    </div>

                    <div class="mt-5 space-y-3">
                        <p class="text-sm sf-text-muted" x-show="selectedProductId">
                            <span class="font-semibold brand-text">Pré-selecionado:</span>
                            <span x-text="productById(selectedProductId)?.label || ''"></span>
                        </p>
                        <div class="grid gap-3 sm:grid-cols-[110px_minmax(0,1fr)]">
                            <input type="number" min="1" x-model="selectedProductQuantity" class="sf-input block w-full" aria-label="Quantidade">
                            <button type="button" x-on:click="addProduct()" class="sf-button-primary">Adicionar produto</button>
                        </div>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('product_items')" />

                    <div class="mt-4 space-y-3">
                        <template x-for="(item, index) in normalizedProducts" :key="item.product.id">
                            <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-[var(--input-bg)] px-4 py-3">
                                <div>
                                    <input type="hidden" x-bind:name="`product_items[${index}][product_id]`" x-bind:value="item.product.id">
                                    <input type="hidden" x-bind:name="`product_items[${index}][quantity]`" x-bind:value="item.quantity">
                                    <p class="text-sm font-semibold text-[var(--text-main)]" x-text="item.product.name"></p>
                                    <p class="mt-1 text-xs sf-text-muted">
                                        <span x-text="`Qtd. ${item.quantity}`"></span>
                                        <span> · </span>
                                        <span x-text="money(item.product.price * item.quantity)"></span>
                                    </p>
                                </div>
                                <button type="button" x-on:click="removeProduct(item.product.id)" class="sf-button-ghost px-3 py-2 text-xs">Remover</button>
                            </div>
                        </template>

                        <div x-show="normalizedProducts.length === 0" class="rounded-2xl border border-dashed border-white/15 px-4 py-5 text-sm sf-text-muted">
                            Nenhum produto adicionado na comanda inicial.
                        </div>
                    </div>
                </section>

                <section class="sf-card p-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <x-input-label for="professional-search" value="Profissional" />
                            <input
                                id="professional-search"
                                type="search"
                                autocomplete="off"
                                x-model="professionalPickerQuery"
                                class="sf-input mt-2 block w-full"
                                placeholder="Digite o nome do profissional…"
                            >
                            <div
                                x-cloak
                                x-show="professionalPickerQuery.trim().length > 0 && filteredProfessionalRows.length > 0"
                                class="mt-2 max-h-52 overflow-y-auto overscroll-contain rounded-xl border border-white/10 bg-[var(--input-bg)] shadow-inner"
                            >
                                <template x-for="row in filteredProfessionalRows" :key="row.id">
                                    <button
                                        type="button"
                                        class="flex w-full border-b border-white/5 px-4 py-3 text-left text-sm font-medium text-[var(--text-main)] transition hover:bg-white/5 last:border-b-0"
                                        x-on:click="pickProfessional(row)"
                                        x-text="row.label"
                                    ></button>
                                </template>
                            </div>
                            <p x-show="professionalPickerQuery.trim().length > 0 && professionalRows.length > 0 && filteredProfessionalRows.length === 0" x-cloak class="mt-2 rounded-xl border border-dashed border-white/15 px-4 py-3 text-sm sf-text-muted">
                                Nenhum profissional encontrado para esta busca.
                            </p>
                            <p x-show="pickedProfessionalLabel" class="mt-2 text-sm sf-text-muted">
                                <span class="font-semibold brand-text">Selecionado:</span>
                                <span x-text="pickedProfessionalLabel" class="text-[var(--text-main)]"></span>
                            </p>
                            <select
                                id="user_id"
                                x-ref="professionalSelect"
                                x-on:change="pickedProfessionalLabel = $event.target.value ? ([...$event.target.options].find((o) => o.value === $event.target.value)?.text?.trim() ?? '') : ''; fetchSmartSlots()"
                                name="user_id"
                                class="sr-only"
                                tabindex="-1"
                                required
                                aria-hidden="true"
                            >
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
                            <x-text-input id="start_time" name="start_time" type="datetime-local" class="mt-2 block w-full" :value="old('start_time', optional($appointment?->start_time)->format('Y-m-d\TH:i'))" required x-on:change="fetchSmartSlots()" />
                            <div class="mt-3 space-y-2" x-show="smartSlots.length > 0 || smartSlotsLoading || smartSlotsError" x-cloak>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Sugestões de horário</p>
                                <p class="text-xs sf-text-muted" x-show="! smartSlotsLoading && smartSlots.length === 0 && ! smartSlotsError">Escolha profissional, data e serviços para ver encaixes recomendados.</p>
                                <p class="text-xs text-amber-200" x-show="smartSlotsError" x-text="smartSlotsError"></p>
                                <p class="text-xs sf-text-muted" x-show="smartSlotsLoading">Carregando sugestões…</p>
                                <div class="flex flex-wrap gap-2" x-show="! smartSlotsLoading && smartSlots.length > 0">
                                    <template x-for="slot in smartSlots.slice(0, 12)" :key="slot.time">
                                        <button
                                            type="button"
                                            class="rounded-full border px-3 py-1.5 text-left text-xs font-semibold transition"
                                            :class="slot.label === 'Melhor encaixe' ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-100' : (slot.warnings && slot.warnings.length ? 'border-amber-400/40 bg-amber-500/10 text-amber-50' : 'border-white/10 bg-[var(--input-bg)] text-[var(--text-main)]')"
                                            @click="applySmartSlot(slot.time)"
                                        >
                                            <span x-text="slot.time"></span>
                                            <template x-if="slot.label">
                                                <span class="ml-1 rounded bg-emerald-500/20 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-emerald-100" x-text="slot.label"></span>
                                            </template>
                                            <template x-if="slot.warnings && slot.warnings.length">
                                                <span class="ml-1 block text-[10px] font-normal text-amber-100" x-text="slot.warnings[0]"></span>
                                            </template>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <p class="mt-2 text-sm sf-text-muted">A disponibilidade será validada pela duração total dos serviços.</p>
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
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Resumo da comanda</p>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="sf-text-muted">Duração</dt>
                            <dd class="font-semibold text-[var(--text-main)]" x-text="`${totalDuration} min`"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="sf-text-muted">Serviços</dt>
                            <dd class="font-semibold text-[var(--text-main)]" x-text="money(serviceSubtotal)"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="sf-text-muted">Produtos</dt>
                            <dd class="font-semibold text-[var(--text-main)]" x-text="money(productSubtotal)"></dd>
                        </div>
                        <div class="border-t border-white/10 pt-3">
                            <div class="flex justify-between gap-4">
                                <dt class="font-semibold text-[var(--text-main)]">Total geral</dt>
                                <dd class="text-xl font-semibold brand-text" x-text="money(totalAmount)"></dd>
                            </div>
                        </div>
                    </dl>
                </section>

                <section class="sf-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] brand-text">Histórico do cliente</p>
                    <div x-show="loadingHistory" class="mt-4 text-sm sf-text-muted">Carregando histórico...</div>
                    <div x-show="clientHistory && clientHistory.has_history" class="mt-4 space-y-4 text-sm">
                        <div>
                            <p class="sf-text-muted">Última visita</p>
                            <p class="font-semibold text-[var(--text-main)]" x-text="clientHistory?.last_visit || 'Sem visita concluída'"></p>
                        </div>
                        <div>
                            <p class="sf-text-muted">Últimos serviços</p>
                            <p class="font-semibold text-[var(--text-main)]" x-text="clientHistory?.last_services?.join(', ') || 'Nenhum serviço registrado'"></p>
                        </div>
                        <div>
                            <p class="sf-text-muted">Últimos produtos</p>
                            <p class="font-semibold text-[var(--text-main)]" x-text="clientHistory?.last_products?.join(', ') || 'Nenhum produto registrado'"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-3 py-3">
                                <p class="text-xs sf-text-muted">Total gasto</p>
                                <p class="mt-1 font-semibold text-[var(--text-main)]" x-text="money(clientHistory?.total_spent)"></p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-[var(--input-bg)] px-3 py-3">
                                <p class="text-xs sf-text-muted">Ticket médio</p>
                                <p class="mt-1 font-semibold text-[var(--text-main)]" x-text="money(clientHistory?.average_ticket)"></p>
                            </div>
                        </div>
                        <div x-show="clientHistory?.notes">
                            <p class="sf-text-muted">Observações</p>
                            <p class="font-semibold text-[var(--text-main)]" x-text="clientHistory?.notes"></p>
                        </div>
                        <button type="button" x-on:click="repeatLastAppointment()" class="sf-button-ghost w-full">Repetir último atendimento</button>
                    </div>
                    <div x-show="clientHistory && ! clientHistory.has_history" class="mt-4 rounded-2xl border border-dashed border-white/15 px-4 py-5 text-sm sf-text-muted">
                        Este cliente ainda não possui histórico de atendimento.
                    </div>
                    <div x-show="! clientHistory && ! loadingHistory" class="mt-4 text-sm sf-text-muted">
                        Selecione um cliente para ver histórico, consumo e sugestões.
                    </div>
                </section>
            </aside>
        </div>

        @if (auth()->user()->hasFinancialPrivileges())
            <div class="mt-6 rounded-xl border border-amber-400/20 bg-amber-500/5 px-4 py-3">
                <label class="flex items-start gap-2 text-sm sf-text-muted">
                    <input type="hidden" name="force_blocked_client" value="0">
                    <input type="checkbox" name="force_blocked_client" value="1" class="mt-1 rounded border-white/20 bg-[var(--card-bg)] brand-text" @checked(old('force_blocked_client'))>
                    <span>Permitir agendamento para cliente bloqueado (confirmacao de gestor/financeiro).</span>
                </label>
            </div>
        @endif

        <div class="mt-6 flex items-center justify-between gap-4">
            <a href="{{ route('appointments.index') }}" class="text-sm font-medium sf-text-muted transition hover:text-[var(--text-main)]">Cancelar</a>
            <x-primary-button>Salvar agendamento</x-primary-button>
        </div>
    </form>

    <x-modal name="appointment-client-modal" maxWidth="lg" focusable>
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] brand-text">Clientes</p>
                    <h3 class="mt-2 text-2xl font-semibold text-[var(--text-main)]">Novo cliente</h3>
                    <p class="mt-2 text-sm sf-text-muted">Cadastre o cliente sem sair do novo agendamento.</p>
                </div>

                <button
                    type="button"
                    x-on:click="closeClientModal()"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-[var(--input-bg)] sf-text-muted transition hover:border-[color-mix(in_srgb,var(--brand-primary)_30%,transparent)] hover:text-[var(--text-main)]"
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

                <div class="rounded-2xl border border-white/8 bg-[var(--input-bg)] px-4 py-4 text-sm sf-text-muted">
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
