<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assinaturas - {{ $company->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--app-shell-bg)] text-[var(--text-main)]">
    <main class="mx-auto max-w-6xl px-4 py-10">
        <header class="mb-8">
            <p class="sf-page-eyebrow">Assinaturas</p>
            <h1 class="sf-page-title mt-2">{{ $company->name }}</h1>
            <p class="sf-page-subtitle mt-3">Escolha um plano e confirme o aceite dos termos para iniciar a contratacao.</p>
        </header>

        @if (session('status') === 'membership-requested')
            <div class="mb-6 rounded-xl border border-emerald-300/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                Solicitacao registrada. A empresa entrara em contato para confirmar o pagamento e ativacao.
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-300/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-5 lg:grid-cols-3">
            @forelse ($plans as $plan)
                <form method="POST" action="{{ route('public-memberships.store', $company) }}" class="sf-card flex flex-col p-5">
                    @csrf
                    <input type="hidden" name="membership_plan_id" value="{{ $plan->id }}">
                    <div class="flex-1">
                        <h2 class="text-xl font-bold">{{ $plan->name }}</h2>
                        <p class="mt-2 text-sm sf-muted">{{ $plan->description }}</p>
                        <p class="mt-5 text-3xl font-black brand-text">R$ {{ number_format((float) $plan->price, 2, ',', '.') }}</p>
                        <p class="mt-1 text-xs uppercase tracking-[0.18em] sf-muted">{{ $plan->billing_cycle_label }}</p>
                        <div class="mt-5 space-y-2 text-sm sf-text-muted">
                            @foreach ($plan->services as $service)
                                <p>{{ $service->name }} @if ($service->pivot->included) incluso @elseif($service->pivot->discount_percent) {{ number_format((float) $service->pivot->discount_percent, 0) }}% off @endif</p>
                            @endforeach
                        </div>
                        @if ($plan->terms_text)
                            <div class="mt-5 max-h-36 overflow-y-auto rounded-lg border border-white/10 bg-[var(--input-bg)] p-3 text-xs sf-muted">
                                {{ $plan->terms_text }}
                            </div>
                        @endif
                    </div>

                    <div class="mt-5 space-y-3">
                        <input name="name" value="{{ old('name') }}" class="sf-input w-full" placeholder="Nome completo" required>
                        <input name="phone" value="{{ old('phone') }}" class="sf-input w-full" placeholder="WhatsApp" required>
                        <input name="email" value="{{ old('email') }}" class="sf-input w-full" placeholder="E-mail">
                        <label class="flex items-start gap-2 text-xs sf-muted">
                            <input type="checkbox" name="accepted_terms" value="1" class="mt-1 rounded border-white/20" required>
                            <span>Li e aceito os termos de consentimento/contrato deste plano.</span>
                        </label>
                        <button class="sf-button-primary w-full" type="submit">Contratar</button>
                    </div>
                </form>
            @empty
                <div class="sf-card p-6 lg:col-span-3">Nenhum plano disponivel no momento.</div>
            @endforelse
        </div>
    </main>
</body>
</html>
