<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">SERVICOS</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    Editar servico
                </h2>
                <p class="mt-2 text-sm text-[#c7d2e3]">Atualize imagem, preco, duracao e disponibilidade</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('services.index') }}" class="sf-button-ghost">
                    Voltar
                </a>
                <button type="submit" form="service-edit-form" class="sf-button-primary">
                    Salvar servico
                </button>
            </div>
        </div>
    </x-slot>

    <div
        x-data="{
            name: @js(old('name', $service->name)),
            duration: @js((string) old('duration_minutes', $service->duration_minutes)),
            price: @js((string) old('price', number_format((float) $service->price, 2, '.', ''))),
            active: @js((bool) old('active', $service->active)),
            imageName: '',
            currentImage: @js($service->image_url),
        }"
        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
    >
        <section class="sf-card p-5 sm:p-6">
            <form id="service-edit-form" method="POST" action="{{ route('services.update', $service) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="lg:col-span-2">
                        <label for="name" class="text-sm font-medium text-white">Nome</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $service->name) }}"
                            x-model="name"
                            class="sf-input mt-2 block w-full"
                            required
                            autofocus
                        >
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <label for="duration_minutes" class="text-sm font-medium text-white">Duracao (min)</label>
                        <input
                            id="duration_minutes"
                            name="duration_minutes"
                            type="number"
                            min="1"
                            value="{{ old('duration_minutes', $service->duration_minutes) }}"
                            x-model="duration"
                            class="sf-input mt-2 block w-full"
                            required
                        >
                        <x-input-error class="mt-2" :messages="$errors->get('duration_minutes')" />
                    </div>

                    <div>
                        <label for="price" class="text-sm font-medium text-white">Preco</label>
                        <input
                            id="price"
                            name="price"
                            type="number"
                            min="0"
                            step="0.01"
                            value="{{ old('price', number_format((float) $service->price, 2, '.', '')) }}"
                            x-model="price"
                            class="sf-input mt-2 block w-full"
                            required
                        >
                        <x-input-error class="mt-2" :messages="$errors->get('price')" />
                    </div>

                    <div class="lg:col-span-2">
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <label for="image" class="text-sm font-medium text-white">Imagem do servico</label>
                            <input
                                id="image"
                                name="image"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                class="sf-input mt-2 block w-full px-3 py-3"
                                @change="imageName = $event.target.files[0] ? $event.target.files[0].name : ''"
                            >
                            <p class="mt-2 text-xs text-[#c7d2e3]">Envie uma nova imagem para substituir a miniatura atual do catalogo.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('image')" />
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
                            @checked(old('active', $service->active))
                        >
                        <div>
                            <label for="active" class="text-sm font-medium text-white">Status ativo</label>
                            <p class="mt-1 text-sm text-[#c7d2e3]">Servicos ativos aparecem na agenda interna e podem ser usados no agendamento online.</p>
                        </div>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('active')" />
                </div>
            </form>
        </section>

        <aside class="space-y-6">
            <section class="sf-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d4af37]">Preview</p>
                    <h3 class="mt-2 text-xl font-semibold text-white">Como o servico vai aparecer</h3>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Imagem</p>
                        <div class="mt-3 flex h-28 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-white/10 bg-[#1b335b]">
                            <template x-if="currentImage && !imageName">
                                <img :src="currentImage" alt="Imagem atual do servico" class="h-full w-full object-cover">
                            </template>
                            <template x-if="imageName">
                                <div class="px-4 text-center">
                                    <p class="text-sm font-semibold text-white" x-text="imageName"></p>
                                    <p class="mt-1 text-xs text-[#c7d2e3]">Nova imagem pronta para substituir a atual.</p>
                                </div>
                            </template>
                            <template x-if="!currentImage && !imageName">
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
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Nome</p>
                        <p class="mt-2 text-lg font-semibold text-white" x-text="name || 'Servico premium'"></p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Preco</p>
                            <p class="mt-2 text-lg font-semibold text-white" x-text="'R$ ' + (price || '0.00').replace('.', ',')"></p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Duracao</p>
                            <p class="mt-2 text-lg font-semibold text-white" x-text="(duration || '0') + ' min'"></p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-[#132746] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#d4af37]">Disponibilidade</p>
                        <p class="mt-2 text-sm font-medium text-white">Aparecera no agendamento online</p>
                        <p class="mt-2 text-sm" :class="active ? 'text-emerald-200' : 'text-[#c7d2e3]'" x-text="active ? 'Ativo e pronto para a agenda.' : 'Inativo ate nova ativacao.'"></p>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</x-app-layout>
