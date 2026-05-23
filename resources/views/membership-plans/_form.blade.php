@php
    $plan = $plan ?? null;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-input-label for="name" value="Nome do plano" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $plan?->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>
    <div class="sm:col-span-2">
        <x-input-label for="description" value="Descrição" />
        <textarea id="description" name="description" rows="2" class="sf-input mt-1 block w-full">{{ old('description', $plan?->description) }}</textarea>
    </div>
    <div>
        <x-input-label for="price" value="Preço (referência)" />
        <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price', $plan?->price)" required />
        <x-input-error class="mt-2" :messages="$errors->get('price')" />
    </div>
    <div>
        <x-input-label for="billing_cycle" value="Ciclo de cobrança" />
        <select id="billing_cycle" name="billing_cycle" class="sf-select mt-1 block w-full" required>
            @foreach ($billingCycles as $cycle)
                <option value="{{ $cycle }}" @selected(old('billing_cycle', $plan?->billing_cycle) === $cycle)>{{ \App\Models\MembershipPlan::billingCycleLabel($cycle) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label for="duration_days" value="Duração do ciclo (dias, opcional)" />
        <x-text-input id="duration_days" name="duration_days" type="number" min="1" class="mt-1 block w-full" :value="old('duration_days', $plan?->duration_days)" />
    </div>
    <div>
        <x-input-label for="max_services_per_cycle" value="Máx. serviços por ciclo (total)" />
        <x-text-input id="max_services_per_cycle" name="max_services_per_cycle" type="number" min="1" class="mt-1 block w-full" :value="old('max_services_per_cycle', $plan?->max_services_per_cycle)" />
    </div>
    <div>
        <x-input-label for="max_service_discount_percent" value="Teto desconto serviço (%)" />
        <x-text-input id="max_service_discount_percent" name="max_service_discount_percent" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" :value="old('max_service_discount_percent', $plan?->max_service_discount_percent)" />
    </div>
    <div>
        <x-input-label for="max_product_discount_percent" value="Desconto automático produtos (%)" />
        <x-text-input id="max_product_discount_percent" name="max_product_discount_percent" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" :value="old('max_product_discount_percent', $plan?->max_product_discount_percent)" />
    </div>
    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 text-sm sf-text-muted">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" class="rounded border-white/20" @checked(old('active', $plan?->active ?? true))>
            Ativo
        </label>
        <label class="flex items-center gap-2 text-sm sf-text-muted">
            <input type="hidden" name="auto_renew" value="0">
            <input type="checkbox" name="auto_renew" value="1" class="rounded border-white/20" @checked(old('auto_renew', $plan?->auto_renew))>
            Renovação automática (padrão do plano)
        </label>
    </div>
    <div class="sm:col-span-2">
        <x-input-label for="terms_text" value="Termos (texto)" />
        <textarea id="terms_text" name="terms_text" rows="3" class="sf-input mt-1 block w-full">{{ old('terms_text', $plan?->terms_text) }}</textarea>
    </div>
</div>

<div class="mt-8 border-t border-white/10 pt-6">
    <h3 class="text-sm font-semibold uppercase tracking-[0.18em] brand-text">Serviços vinculados</h3>
    <p class="mt-2 text-xs sf-text-muted">Marque incluso para benefício integral no ciclo; ou informe desconto percentual quando não incluso.</p>
    <div class="mt-4 space-y-3">
        @foreach ($services as $service)
            @php
                $p = $plan?->services->firstWhere('id', $service->id)?->pivot;
                $prefix = "services[{$service->id}]";
            @endphp
            <div class="rounded-xl border border-white/10 bg-[var(--input-bg)] px-4 py-3">
                <input type="hidden" name="{{ $prefix }}[service_id]" value="{{ $service->id }}">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-medium text-[var(--text-main)]">{{ $service->name }}</span>
                    <label class="flex items-center gap-2 text-xs sf-text-muted">
                        <input type="hidden" name="{{ $prefix }}[included]" value="0">
                        <input type="checkbox" name="{{ $prefix }}[included]" value="1" class="rounded border-white/20" @checked(old($prefix.'.included', $p?->included ?? false))>
                        Incluso
                    </label>
                    <label class="text-xs sf-text-muted">Qtd/ciclo
                        <input type="number" name="{{ $prefix }}[quantity_per_cycle]" min="1" class="sf-input ms-1 w-20 py-1 text-xs" value="{{ old($prefix.'.quantity_per_cycle', $p?->quantity_per_cycle) }}">
                    </label>
                    <label class="text-xs sf-text-muted">Desc. %
                        <input type="number" step="0.01" name="{{ $prefix }}[discount_percent]" min="0" max="100" class="sf-input ms-1 w-20 py-1 text-xs" value="{{ old($prefix.'.discount_percent', $p?->discount_percent) }}">
                    </label>
                    <label class="text-xs sf-text-muted">Tempo assinante
                        <input type="number" name="{{ $prefix }}[special_duration_minutes]" min="1" class="sf-input ms-1 w-24 py-1 text-xs" value="{{ old($prefix.'.special_duration_minutes', $p?->special_duration_minutes) }}" placeholder="min">
                    </label>
                </div>
            </div>
        @endforeach
    </div>
</div>
