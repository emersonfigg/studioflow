<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Assinaturas — {{ $company->name }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-[var(--app-shell-bg)] text-[var(--text-main)]"><main class="mx-auto max-w-6xl px-4 py-8">
<header><h1 class="sf-page-title">{{ $company->name }}</h1><p class="mt-3 sf-muted">Contratação segura com cartão no checkout Asaas.</p></header>
@if ($errors->any())<p role="alert" class="sf-card my-4 p-4">{{ $errors->first() }}</p>@endif
@if ($plans->isEmpty())<p class="sf-card my-6 p-6">Nenhum plano disponível para contratação no momento.</p>@endif
@if ($selected)
<section class="sf-card mx-auto my-6 max-w-xl space-y-4 p-6">
<h2 class="text-xl font-bold">{{ $selected->name }}</h2>
<p>R$ {{ number_format((float) $selected->price, 2, ',', '.') }} · {{ $selected->billing_cycle_label }} · Renovação automática no cartão</p>
<p class="whitespace-pre-line">{{ $selected->terms_text }}</p>
<p class="text-sm sf-muted">As renovações seguem meses de calendário; o dia é ajustado quando o mês tem menos dias. O valor acima será cobrado a cada ciclo até o cancelamento da renovação.</p>
<p class="text-sm sf-muted">A confirmação do pagamento é necessária. A liberação dos benefícios será confirmada pelo estabelecimento.</p>
@if (! $identity)
<form method="POST" action="{{ route('public-memberships.identify', $company) }}" class="space-y-3">@csrf
<input type="hidden" name="membership_plan_id" value="{{ $selected->id }}">
<label class="block">Seu e-mail<input class="sf-input mt-2 w-full" type="email" name="email" autocomplete="email" maxlength="255" required></label>
<button class="sf-button-primary" type="submit">Receber código de confirmação</button>
</form>
@if (session()->has('membership_challenge_'.$company->id))
<p>Enviamos um código para o e-mail informado. Confira também a caixa de spam.</p>
<form method="POST" action="{{ route('public-memberships.verify', $company) }}" class="space-y-3">@csrf
<input type="hidden" name="membership_plan_id" value="{{ $selected->id }}">
<label class="block">Código de confirmação<input name="code" class="sf-input mt-2 w-full" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
<button class="sf-button-primary" type="submit">Confirmar e-mail</button></form>
@endif
@else
<form method="POST" action="{{ route('public-memberships.store', $company) }}" class="space-y-4">@csrf
<input type="hidden" name="membership_plan_id" value="{{ $selected->id }}"><input type="hidden" name="terms_version" value="{{ $snapshot['terms_version'] }}">
<label class="block">Nome completo<input class="sf-input mt-2 w-full" name="name" autocomplete="name" maxlength="255" value="{{ old('name') }}" required></label>
<label class="block">Telefone para contato<input class="sf-input mt-2 w-full" name="phone" type="tel" autocomplete="tel" maxlength="30" value="{{ old('phone') }}" required></label>
<label class="flex items-start gap-2"><input type="checkbox" name="accepted_terms" value="1" required><span>Li e aceito as condições, o preço e a renovação automática {{ mb_strtolower($selected->billing_cycle_label) }} no cartão.</span></label>
<button class="sf-button-primary w-full" type="submit">Continuar no checkout Asaas</button>
</form>
@endif
</section>
@endif
@include('public-memberships.club')
<a class="sf-button-secondary mt-4 inline-block" href="{{ route('public-bookings.create', $company) }}">Continuar com agendamento avulso</a>
</main></body></html>
