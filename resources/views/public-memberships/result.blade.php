<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Contratação — {{ $company->name }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-[var(--app-shell-bg)] text-[var(--text-main)]"><main class="mx-auto max-w-xl p-6"><section class="sf-card p-6">
    <h1 class="text-xl font-bold">Acompanhe sua contratação</h1>
    <p class="mt-4">O retorno do checkout não confirma o pagamento nem libera benefícios. A confirmação financeira será processada com segurança.</p>
    @if ($intention->status === 'creation_unknown' || $intention->status === 'creating')
        <p class="mt-4">Estamos verificando o envio ao Asaas. Não repita o pagamento. Entre em contato com o estabelecimento se a confirmação demorar.</p>
    @elseif (in_array($intention->status, ['expired', 'canceled']) || $intention->expires_at?->isPast())
        <p class="mt-4">Esta jornada foi encerrada ou seu prazo terminou. Isso não cancela uma cobrança já autorizada. O estabelecimento deverá conferir a situação antes de iniciar outra contratação.</p>
    @else
        <p class="mt-4">Sua solicitação foi registrada. Aguarde a confirmação do estabelecimento sobre a disponibilidade dos benefícios.</p>
    @endif
    <a class="sf-button-primary mt-6 inline-block" href="{{ route('public-bookings.create', $company) }}">Voltar ao agendamento</a>
</section></main></body></html>
