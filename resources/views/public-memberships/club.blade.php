@if ($plans->isNotEmpty())
<section aria-labelledby="membership-club-title" class="sf-card mx-3 mb-12 mt-6 p-5 sm:mx-5 lg:mx-0">
    <p class="sf-page-eyebrow">Cuide de você o ano inteiro</p>
    <h2 id="membership-club-title" class="mt-2 text-xl font-bold">CLUBE DE ASSINATURAS</h2>
    <p class="mt-2 text-sm sf-muted">Escolha um plano com renovação automática no cartão. Você também pode continuar com um agendamento avulso.</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($plans as $plan)
        <article class="sf-card flex min-w-0 flex-col p-4">
            <h3 class="text-lg font-bold">{{ $plan->name }}</h3>
            <p class="mt-2 text-sm sf-muted">{{ $plan->description }}</p>
            <p class="mt-4 text-2xl font-bold brand-text">R$ {{ number_format((float) $plan->price, 2, ',', '.') }}</p>
            <p class="text-sm sf-muted">{{ $plan->billing_cycle_label }} · Renovação automática</p>
            <ul class="my-4 space-y-2 text-sm">
                @foreach ($plan->services as $service)
                <li>{{ $service->name }} — {{ $service->pivot->included ? 'Incluso' : 'Desconto de '.number_format((float) $service->pivot->discount_percent, 0).'%' }};
                    {{ $service->pivot->quantity_per_cycle === null ? 'utilizações ilimitadas' : $service->pivot->quantity_per_cycle.' utilização(ões)' }} por ciclo</li>
                @endforeach
                <li>Limite total por ciclo: {{ $plan->max_services_per_cycle === null ? 'ilimitado' : $plan->max_services_per_cycle }}</li>
                @if ($plan->max_product_discount_percent > 0)<li>Até {{ number_format((float) $plan->max_product_discount_percent, 0) }}% de desconto em produtos</li>@endif
                @if ($plan->max_service_discount_percent > 0)<li>Até {{ number_format((float) $plan->max_service_discount_percent, 0) }}% de desconto em serviços</li>@endif
            </ul>
            <details class="mb-4 text-sm"><summary class="cursor-pointer">Benefícios e condições</summary><p class="mt-2 whitespace-pre-line sf-muted">{{ $plan->terms_text }}</p></details>
            <a class="sf-button-primary mt-auto text-center" href="{{ route('public-memberships.index', [$company, 'plan' => $plan->id]) }}">Assinar agora</a>
        </article>
        @endforeach
    </div>
</section>
@endif
