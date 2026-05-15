@php
    /** @var \App\Models\ServiceOrder $order */
    $company = $order->company;
    $paymentMethod = $order->payment?->payment_method ?? $order->productSale?->payment_method;
    $paymentLabel = $paymentMethod
        ? \App\Models\Payment::labelForPaymentMethod((string) $paymentMethod)
        : '—';
    $notes = $order->productSale?->notes ?? $order->payment?->notes;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprovante — Comanda #{{ $order->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif; margin: 0; padding: 1.25rem; color: #171717; background: #fff; font-size: 13px; line-height: 1.45; }
        .brand { text-align: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 2px solid #171717; }
        .brand h1 { margin: 0; font-size: 1.35rem; font-weight: 800; letter-spacing: -0.02em; text-transform: uppercase; }
        .brand .sub { margin-top: 0.35rem; font-size: 0.8rem; color: #525252; }
        .meta { margin: 0.75rem 0; font-size: 0.8rem; color: #404040; }
        .meta strong { color: #171717; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; font-size: 12px; }
        th, td { text-align: left; padding: 0.35rem 0.2rem; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        th { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.06em; color: #737373; border-bottom: 2px solid #d4d4d4; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-top: 1rem; max-width: 18rem; margin-left: auto; font-size: 12px; }
        .totals div { display: flex; justify-content: space-between; padding: 0.15rem 0; }
        .grand { font-weight: 800; font-size: 1rem; border-top: 2px solid #171717; padding-top: 0.5rem; margin-top: 0.35rem; }
        .footer-note { margin-top: 1.25rem; text-align: center; font-size: 0.8rem; color: #737373; }
        .thankyou { margin-top: 0.5rem; text-align: center; font-weight: 600; color: #171717; font-size: 0.95rem; }
        .actions { margin-top: 1rem; text-align: center; }
        .actions button { padding: 0.5rem 1rem; font-weight: 600; cursor: pointer; border-radius: 0.375rem; border: 1px solid #171717; background: #171717; color: #fff; }
        @media print {
            .actions { display: none; }
            body { padding: 0.35rem; }
        }
    </style>
</head>
<body>
    <div class="brand">
        <h1>{{ $company?->name ?? 'Empresa' }}</h1>
        @if ($company?->phone)
            <p class="sub">Tel. {{ $company->phone }}</p>
        @endif
        @if ($company?->cnpj)
            <p class="sub">CNPJ {{ $company->cnpj }}</p>
        @endif
    </div>

    <p class="meta"><strong>Comanda / venda</strong> #{{ $order->id }}</p>
    <p class="meta"><strong>Data e hora:</strong> {{ $order->closed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>

    @if ($order->client)
        <p class="meta"><strong>Cliente:</strong> {{ $order->client->name }}</p>
    @endif

    @if ($order->professional)
        <p class="meta"><strong>Profissional:</strong> {{ $order->professional->name }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Descrição</th>
                <th class="num">Qtd</th>
                <th class="num">Unit.</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                    <td class="num">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal serviços</span><span>R$ {{ number_format((float) $order->subtotal_services, 2, ',', '.') }}</span></div>
        <div><span>Subtotal produtos</span><span>R$ {{ number_format((float) $order->subtotal_products, 2, ',', '.') }}</span></div>
        <div class="grand"><span>Total</span><span>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</span></div>
    </div>

    <p class="meta" style="margin-top:1rem;"><strong>Forma de pagamento:</strong> {{ $paymentLabel }}</p>

    @if ($notes)
        <p class="meta"><strong>Observações:</strong> {{ $notes }}</p>
    @endif

    <p class="thankyou">Obrigado pela preferência.</p>
    <p class="footer-note">Documento sem valor fiscal.</p>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>
