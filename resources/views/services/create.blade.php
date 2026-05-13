<x-app-layout>
    @php($initialLibraryImage = collect($libraryImages)->firstWhere('path', old('library_image')))

    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">SERVIÇOS</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Novo serviço
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Cadastre preço, duração, disponibilidade e imagem</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('services.index') }}" class="sf-button-ghost">
                    Voltar
                </a>
                <button type="submit" form="service-create-form" class="sf-button-primary">
                    Salvar serviço
                </button>
            </div>
        </div>
    </x-slot>

    <div
        x-data="{
            name: @js(old('name', '')),
            description: @js(old('description', '')),
            descriptionLimit: 500,
            duration: @js((string) old('duration_minutes', '60')),
            price: @js((string) old('price', '0,00')),
            active: @js((bool) old('active', true)),
            imageName: '',
            uploadPreview: '',
            selectedLibraryImage: @js(old('library_image', '')),
            selectedLibraryPreview: @js($initialLibraryImage['url'] ?? ''),
            handleUploadChange(event) {
                const file = event.target.files[0];

                if (!file) {
                    this.imageName = '';
                    this.uploadPreview = '';
                    return;
                }

                this.imageName = file.name;
                this.selectedLibraryImage = '';
                this.selectedLibraryPreview = '';

                const reader = new FileReader();
                reader.onload = (loadEvent) => {
                    this.uploadPreview = loadEvent.target?.result ?? '';
                };
                reader.readAsDataURL(file);
            },
            chooseLibraryImage(image) {
                this.selectedLibraryImage = image.path;
                this.selectedLibraryPreview = image.url;
                this.imageName = '';
                this.uploadPreview = '';

                if (this.$refs.imageInput) {
                    this.$refs.imageInput.value = '';
                }
            }
        }"
        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
    >
        <section class="sf-card p-5 sm:p-6">
            <form id="service-create-form" method="POST" action="{{ route('services.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <input type="hidden" name="library_image" :value="selectedLibraryImage">

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="lg:col-span-2">
                        <label for="name" class="text-sm font-medium text-white">Nome</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            x-model="name"
                            class="sf-input mt-2 block w-full"
                            required
                            autofocus
                        >
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div class="lg:col-span-2">
                        <div class="flex items-center justify-between gap-3">
                            <label for="description" class="text-sm font-medium text-white">Descrição</label>
                            <span class="text-xs text-[#c7d2e3]">
                                <span x-text="description.length">0</span>/<span x-text="descriptionLimit">500</span>
                            </span>
                        </div>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            maxlength="500"
                            placeholder="Ex: Modelagem completa da barba com acabamento na navalha e aplicação de óleo hidratante."
                            x-model="description"
                            class="sf-input mt-2 block w-full resize-y leading-relaxed"
                        >{{ old('description') }}</textarea>
                        <p class="mt-2 text-xs text-[#c7d2e3]">Opcional. Aparece no agendamento online para o cliente entender o que está contratando.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('description')" />
                    </div>

                    <div>
                        <label for="duration_minutes" class="text-sm font-medium text-white">Duração (min)</label>
                        <input
                            id="duration_minutes"
                            name="duration_minutes"
                            type="number"
                            min="1"
                            value="{{ old('duration_minutes', 60) }}"
                            x-model="duration"
                            class="sf-input mt-2 block w-full"
                            required
                        >
                        <x-input-error class="mt-2" :messages="$errors->get('duration_minutes')" />
                    </div>

                    <div>
                        <label for="price" class="text-sm font-medium text-white">Preço</label>
                        <input
                            id="price"
                            name="price"
                            type="text"
                            inputmode="decimal"
                            placeholder="R$ 0,00"
                            value="{{ old('price', '0,00') }}"
                            x-model="price"
                            class="sf-input mt-2 block w-full"
                            required
                        >
                        <x-input-error class="mt-2" :messages="$errors->get('price')" />
                    </div>

                    <div class="lg:col-span-2">
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Retorno inteligente</p>
                                    <p class="mt-1 text-sm text-[#c7d2e3]">Use o prazo sugerido para que o sistema lembre o cliente de repetir esse atendimento.</p>
                                </div>
                            </div>
                            <div class="mt-4 grid gap-3 md:grid-cols-[200px_minmax(0,1fr)]">
                                <div>
                                    <label for="recommended_return_days" class="text-sm font-medium text-white">Prazo sugerido para retorno (dias)</label>
                                    <input
                                        id="recommended_return_days"
                                        name="recommended_return_days"
                                        type="number"
                                        min="1"
                                        max="730"
                                        step="1"
                                        placeholder="Ex: 30"
                                        value="{{ old('recommended_return_days', '') }}"
                                        class="sf-input mt-2 block w-full"
                                    >
                                    <x-input-error class="mt-2" :messages="$errors->get('recommended_return_days')" />
                                </div>
                                <div class="flex items-center">
                                    <p class="text-xs text-[#c7d2e3]">Após esse prazo, o sistema poderá sugerir este serviço novamente ao cliente. Deixe em branco para não gerar recomendação.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="space-y-5 rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <div>
                                <p class="text-sm font-medium text-white">Biblioteca StudioFlow</p>
                                <p class="mt-2 text-sm text-[#c7d2e3]">
                                    Escolha uma imagem pronta da nossa base visual ou envie uma imagem do seu computador ou celular.
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($libraryImages as $libraryImage)
                                    <button
                                        type="button"
                                        @click='chooseLibraryImage(@js($libraryImage))'
                                        :class="selectedLibraryImage === @js($libraryImage['path']) ? 'border-[#d4af37] bg-[#1b335b] ring-2 ring-[#d4af37]/30' : 'border-white/10 bg-[#1b335b]/60 hover:border-[#d4af37]/60 hover:bg-[#1b335b]'"
                                        class="overflow-hidden rounded-2xl border text-left transition"
                                    >
                                        <div class="aspect-[4/3] overflow-hidden bg-[#10213b]">
                                            <img src="{{ $libraryImage['url'] }}" alt="{{ $libraryImage['label'] }}" class="h-full w-full object-cover">
                                        </div>
                                        <div class="flex items-center justify-between gap-3 px-3 py-3">
                                            <p class="text-sm font-semibold text-white">{{ $libraryImage['label'] }}</p>
                                            <span
                                                x-show="selectedLibraryImage === @js($libraryImage['path'])"
                                                x-cloak
                                                class="rounded-full bg-[#d4af37] px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#132746]"
                                            >
                                                Escolhida
                                            </span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>

                            <div class="rounded-2xl border border-dashed border-white/10 bg-[#1b335b]/70 px-4 py-4">
                                <label for="image" class="text-sm font-medium text-white">Ou envie a sua imagem</label>
                                <input
                                    id="image"
                                    x-ref="imageInput"
                                    name="image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    class="sf-input mt-2 block w-full px-3 py-3"
                                    @change="handleUploadChange($event)"
                                >
                                <p class="mt-2 text-xs text-[#c7d2e3]">Use JPG, PNG ou WEBP para destacar o serviço no catalogo e no agendamento online.</p>
                            </div>

                            <x-input-error class="mt-2" :messages="$errors->get('image')" />
                            <x-input-error class="mt-2" :messages="$errors->get('library_image')" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                    <div class="flex items-start gap-3">
                        <input name="active" type="hidden" value="0">
                        <input
                            id="active"
                            name="active"
                            type="checkbox"
                            value="1"
                            x-model="active"
                            class="mt-1 h-4 w-4 rounded border-white/20 bg-[#1b335b] text-[#d4af37] focus:ring-[#d4af37]"
                            @checked(old('active', true))
                        >
                        <div>
                            <label for="active" class="text-sm font-medium text-white">Status ativo</label>
                            <p class="mt-1 text-sm text-[#c7d2e3]">Serviços ativos aparecem na agenda interna e podem ser usados no agendamento online.</p>
                        </div>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('active')" />
                </div>
            </form>
        </section>

        <aside class="space-y-6">
            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Pré-visualização</p>
                    <h3 class="mt-2 text-xl font-semibold text-white">Como o serviço vai aparecer</h3>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Nome</p>
                        <p class="mt-2 text-lg font-semibold text-white" x-text="name || 'Novo serviço completo'"></p>
                    </div>

                    <div
                        x-show="description.trim().length > 0"
                        x-cloak
                        class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Descrição</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-[#c7d2e3]" x-text="description"></p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Imagem</p>
                        <div class="mt-3 flex h-32 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-white/10 bg-[#1b335b]">
                            <template x-if="uploadPreview">
                                <img :src="uploadPreview" alt="Preview da imagem enviada" class="h-full w-full object-cover">
                            </template>
                            <template x-if="!uploadPreview && selectedLibraryPreview">
                                <img :src="selectedLibraryPreview" alt="Preview da imagem escolhida" class="h-full w-full object-cover">
                            </template>
                            <template x-if="!uploadPreview && !selectedLibraryPreview">
                                <div class="flex flex-col items-center gap-3 text-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#d4af37]/12 text-[#d4af37]">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2 1.586-1.586a2 2 0 012.828 0L20 14m-9-5h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-medium text-[#c7d2e3]">Sem imagem enviada</p>
                                </div>
                            </template>
                        </div>
                        <div class="mt-3 rounded-2xl border border-white/10 bg-[#1b335b]/60 px-3 py-3 text-xs text-[#c7d2e3]">
                            <template x-if="imageName">
                                <p><span class="font-semibold text-white" x-text="imageName"></span> pronta para envio.</p>
                            </template>
                            <template x-if="!imageName && selectedLibraryImage">
                                <p>Imagem da biblioteca pronta para ser usada no serviço.</p>
                            </template>
                            <template x-if="!imageName && !selectedLibraryImage">
                                <p>Escolha uma imagem pronta ou envie uma do seu dispositivo.</p>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Preço</p>
                            <p class="mt-2 text-lg font-semibold text-white" x-text="new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(`${price || '0'}`.replace(/[R$\s.]/g, '').replace(',', '.')) || 0)"></p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Duração</p>
                            <p class="mt-2 text-lg font-semibold text-white" x-text="(duration || '0') + ' min'"></p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Disponibilidade</p>
                        <p class="mt-2 text-sm font-medium text-white">Aparecerá no agendamento online</p>
                        <p class="mt-2 text-sm" :class="active ? 'text-emerald-200' : 'text-[#c7d2e3]'" x-text="active ? 'Ativo e pronto para a agenda.' : 'Inativo até nova ativação.'"></p>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</x-app-layout>
