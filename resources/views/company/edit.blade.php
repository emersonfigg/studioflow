<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">{{ $isOnboarding ? 'ONBOARDING' : 'EMPRESA' }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    {{ $isOnboarding ? 'Vamos configurar sua empresa' : 'Dados da empresa' }}
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">{{ $isOnboarding ? 'Preencha os dados principais para deixar seu StudioFlow com a cara da sua marca.' : 'Personalize nome, logo e informacoes da sua operacao.' }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @unless($isOnboarding)
                    <a href="{{ route('dashboard') }}" class="sf-button-ghost">
                        Voltar
                    </a>
                @endunless
                <button type="submit" form="company-edit-form" class="sf-button-primary">
                    {{ $isOnboarding ? 'Concluir configuracao' : 'Salvar empresa' }}
                </button>
            </div>
        </div>
    </x-slot>

    <div
        x-data="{
            name: @js(old('name', $company->name)),
            phone: @js(old('phone', $company->phone)),
            cnpj: @js(old('cnpj', $company->cnpj)),
            address: @js(old('address', $company->address)),
            instagram: @js(old('instagram', $company->instagram)),
            description: @js(old('description', $company->description)),
            logoName: '',
        }"
        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
    >
        <section class="sf-card p-5 sm:p-6">
            @if (session('status') === 'company-updated')
                <div class="mb-5 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                    Dados da empresa atualizados com sucesso.
                </div>
            @elseif (session('status') === 'company-onboarded')
                <div class="mb-5 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                    Sua empresa foi configurada com sucesso.
                </div>
            @endif

            <form id="company-edit-form" method="POST" action="{{ route('company.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                    <div class="flex items-center gap-4">
                        @if ($company->logo_url)
                            <img src="{{ $company->logo_url }}" alt="Logo de {{ $company->name }}" class="h-20 w-20 rounded-2xl object-cover ring-1 ring-white/10">
                        @else
                            <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-[#d4af37]/12 text-2xl font-semibold text-[#d4af37]">
                                {{ strtoupper(substr($company->name, 0, 1)) }}
                            </div>
                        @endif

                        <div>
                            <p class="text-sm font-medium text-white">Logo da empresa</p>
                            <p class="mt-1 text-sm text-[#c7d2e3]">Envie JPG, JPEG, PNG ou WEBP com ate 2MB.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="logo" class="text-sm font-medium text-white">Upload da logo</label>
                        <input
                            id="logo"
                            name="logo"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="sf-input mt-2 block w-full px-3 py-3"
                            @change="logoName = $event.target.files[0] ? $event.target.files[0].name : ''"
                        >
                        <p class="mt-2 text-xs text-[#c7d2e3]" x-show="logoName" x-text="'Arquivo selecionado: ' + logoName"></p>
                        <x-input-error class="mt-2" :messages="$errors->get('logo')" />
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="lg:col-span-2">
                        <label for="name" class="text-sm font-medium text-white">Nome da empresa</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $company->name) }}" x-model="name" class="sf-input mt-2 block w-full" required>
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <label for="phone" class="text-sm font-medium text-white">Telefone</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $company->phone) }}" x-model="phone" class="sf-input mt-2 block w-full">
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>

                    <div>
                        <label for="cnpj" class="text-sm font-medium text-white">CNPJ</label>
                        <input id="cnpj" name="cnpj" type="text" value="{{ old('cnpj', $company->cnpj) }}" x-model="cnpj" class="sf-input mt-2 block w-full">
                        <x-input-error class="mt-2" :messages="$errors->get('cnpj')" />
                    </div>

                    <div class="lg:col-span-2">
                        <label for="instagram" class="text-sm font-medium text-white">Instagram</label>
                        <input id="instagram" name="instagram" type="text" value="{{ old('instagram', $company->instagram) }}" x-model="instagram" class="sf-input mt-2 block w-full">
                        <x-input-error class="mt-2" :messages="$errors->get('instagram')" />
                    </div>

                    <div class="lg:col-span-2">
                        <label for="address" class="text-sm font-medium text-white">Endereco</label>
                        <textarea id="address" name="address" rows="3" x-model="address" class="sf-input mt-2 block w-full">{{ old('address', $company->address) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('address')" />
                    </div>

                    <div class="lg:col-span-2">
                        <label for="description" class="text-sm font-medium text-white">Descricao curta</label>
                        <textarea id="description" name="description" rows="3" x-model="description" class="sf-input mt-2 block w-full">{{ old('description', $company->description) }}</textarea>
                        <p class="mt-2 text-xs text-[#c7d2e3]">Essa frase pode aparecer na navegacao e na pagina publica de agendamento.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('description')" />
                    </div>
                </div>
            </form>
        </section>

        <aside class="space-y-6">
            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Preview</p>
                    <h3 class="mt-2 text-xl font-semibold text-white">Como sua marca aparece</h3>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Empresa</p>
                        <p class="mt-2 text-lg font-semibold text-white" x-text="name || 'Sua empresa premium'"></p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Contato</p>
                        <p class="mt-2 text-sm font-medium text-white" x-text="phone || 'Telefone nao informado'"></p>
                        <p class="mt-2 text-sm text-[#c7d2e3]" x-text="address || 'Endereco ainda nao preenchido'"></p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Documento</p>
                        <p class="mt-2 text-sm font-medium text-white" x-text="cnpj || 'CNPJ nao informado'"></p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Marca</p>
                        <p class="mt-2 text-sm font-medium text-white" x-text="instagram || 'Instagram nao informado'"></p>
                        <p class="mt-2 text-sm text-[#c7d2e3]" x-text="description || 'Descricao curta ainda nao preenchida.'"></p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Link publico</p>
                        <p class="mt-2 text-sm font-medium text-white break-all">{{ route('public-bookings.create', $company) }}</p>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</x-app-layout>
