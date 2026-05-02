<div
    x-data="{
        creatingClient: false,
        clientErrors: {},
        clientMêssage: '',
        clientForm: {
            name: '',
            phone: '',
            birthday: '',
            notes: '',
        },
        openClientModal() {
            this.clientErrors = {};
            this.clientMêssage = '';
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
            this.clientMêssage = '';

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
                this.clientMêssage = payload.message;
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
    }"
    class="space-y-6"
>
    <form method="POST" action="{{ $action }}" class="space-y-6">
        @csrf

        @if ($method !== 'POST')
            @method($method)
        @endif

        <div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex-1">
                    <x-input-label for="client_id" :value="__('Client')" />
                    <select id="client_id" x-ref="clientSelect" name="client_id" class="sf-select mt-1 block w-full" required>
                        <option value="">{{ __('Select a client') }}</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id', $appointment?->client_id) === $client->id)>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('client_id')" />
                    <p x-show="clientMêssage" x-text="clientMêssage" class="mt-2 text-sm font-medium text-[#d4af37]"></p>
                </div>

                <button
                    type="button"
                    x-on:click="openClientModal()"
                    class="inline-flex items-center justify-center rounded-xl border border-[#d4af37]/30 bg-[#d4af37]/10 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-[#f6e7b3] transition duration-150 hover:border-[#d4af37]/50 hover:bg-[#d4af37]/16"
                >
                    + Novo cliente
                </button>
            </div>
        </div>

        <div>
            <x-input-label for="service_id" :value="__('Service')" />
            <select id="service_id" name="service_id" class="sf-select mt-1 block w-full" required>
                <option value="">{{ __('Select a service') }}</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" @selected((int) old('service_id', $appointment?->service_id) === $service->id)>
                        {{ $service->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
        </div>

        <div>
            <x-input-label for="user_id" :value="__('Staff')" />
            <select id="user_id" name="user_id" class="sf-select mt-1 block w-full" required>
                <option value="">{{ __('Select a staff member') }}</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) old('user_id', $appointment?->user_id ?? auth()->id()) === $user->id)>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('user_id')" />
        </div>

        <div>
            <x-input-label for="start_time" :value="__('Start')" />
            <x-text-input id="start_time" name="start_time" type="datetime-local" class="mt-1 block w-full" :value="old('start_time', optional($appointment?->start_time)->format('Y-m-d\TH:i'))" required />
            <p class="mt-2 text-sm text-[#A1A1AA]">{{ __('End time is calculatéd automatically from the selected service duration.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="status" :value="__('Status')" />
                <select id="status" name="status" class="sf-select mt-1 block w-full" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $appointment?->status ?? 'scheduled') === $status)>
                            {{ match ($status) {
                                'scheduled' => __('Scheduled'),
                                'confirmed' => __('Confirmed'),
                                'in_progress' => __('In Progress'),
                                'completed' => __('Completed'),
                                'cancelled' => __('Cancelled'),
                                default => $status,
                            } }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('status')" />
            </div>

            <div>
                <x-input-label for="source" :value="__('Source')" />
                <select id="source" name="source" class="sf-select mt-1 block w-full" required>
                    @foreach ($sources as $source)
                        <option value="{{ $source }}" @selected(old('source', $appointment?->source ?? 'internal') === $source)>
                            {{ __(str_replace('_', ' ', ucfirst($source))) }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('source')" />
            </div>
        </div>

        <div>
            <x-input-label for="notes" :value="__('Notes')" />
            <textarea id="notes" name="notes" rows="4" class="sf-input mt-1 block w-full">{{ old('notes', $appointment?->notes) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('notes')" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('appointments.index') }}" class="text-sm font-medium text-[#A1A1AA] transition hover:text-white">{{ __('Cancel') }}</a>
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>

    <x-modal name="appointment-client-modal" maxWidth="lg" focusable>
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#d4af37]">CLIENTES</p>
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
                    Se o telefone ja existir nestá empresa, o StudioFlow reaproveita o cliente automaticamente.
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
