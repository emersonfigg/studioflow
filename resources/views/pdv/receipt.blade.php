@php
    /** @var \App\Models\ServiceOrder $order */
    $company = $order->company;
    $paymentMethod = $order->payment?->payment_method ?? $order->productSale?->payment_method;
    $paymentLabel = $paymentMethod
        ? \App\Models\Payment::labelForPaymentMethod((string) $paymentMethod)
        : 'Nao informado';
    $notes = $order->productSale?->notes ?? $order->payment?->notes;
    $brandColor = $company?->primary_color;
    $brandColor = is_string($brandColor) && preg_match('/^#[0-9a-fA-F]{6}$/', $brandColor) === 1
        ? $brandColor
        : '#171717';
    $companyName = $company?->safeDisplayText($company->name ?? null) ?? 'Empresa';
    $companyPhone = $company?->safeDisplayText($company->phone ?? null);
    $companyCnpj = $company?->safeDisplayText($company->cnpj ?? null);
    $companyAddress = $company?->safeDisplayText($company->address ?? null);
    $companyInstagram = $company?->safeDisplayText($company->instagram ?? null);
    $logoUrl = $company?->logo_url;
    $closedAt = $order->closed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
    $paymentStatus = $order->status === \App\Models\ServiceOrder::STATUS_PAID ? 'Pago' : null;
    $hasDiscount = (float) $order->discount > 0;
    $contactLine = collect([$companyPhone, $companyInstagram])->filter()->implode(' | ');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo - Comanda #{{ $order->id }}</title>
    <style>
        :root {
            --receipt-brand: {{ $brandColor }};
            --receipt-ink: #171717;
            --receipt-muted: #5f6368;
            --receipt-line: #e5e7eb;
            --receipt-soft: #f6f7f9;
        }

        * { box-sizing: border-box; }

        html { background: #f3f4f6; }

        body {
            margin: 0;
            padding: 24px 14px;
            color: var(--receipt-ink);
            background: #f3f4f6;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 13px;
            line-height: 1.45;
        }

        .receipt-shell {
            width: min(100%, 760px);
            margin: 0 auto;
        }

        .receipt-card {
            overflow: hidden;
            border: 1px solid rgba(23, 23, 23, 0.08);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
        }

        .receipt-topbar {
            height: 6px;
            background: var(--receipt-brand);
        }

        .company-header {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 18px;
            align-items: center;
            padding: 28px 30px 22px;
            border-bottom: 1px solid var(--receipt-line);
        }

        .company-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            border: 1px solid var(--receipt-line);
            border-radius: 8px;
            padding: 8px;
            background: #fff;
        }

        .company-title {
            margin: 0;
            color: var(--receipt-ink);
            font-size: 1.65rem;
            font-weight: 850;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .company-details {
            display: grid;
            gap: 3px;
            margin-top: 7px;
            color: var(--receipt-muted);
            font-size: 0.83rem;
        }

        .company-details p,
        .receipt-meta p,
        .footer p {
            margin: 0;
        }

        .receipt-body {
            padding: 24px 30px 28px;
        }

        .receipt-heading {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--receipt-line);
        }

        .receipt-heading h2 {
            margin: 0;
            color: var(--receipt-brand);
            font-size: 1.05rem;
            font-weight: 850;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .receipt-number {
            margin-top: 3px;
            font-size: 1.45rem;
            font-weight: 850;
        }

        .receipt-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 18px;
            min-width: min(100%, 360px);
        }

        .meta-item {
            padding: 10px 12px;
            border: 1px solid var(--receipt-line);
            border-radius: 8px;
            background: var(--receipt-soft);
        }

        .meta-label {
            display: block;
            margin-bottom: 2px;
            color: var(--receipt-muted);
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .meta-value {
            color: var(--receipt-ink);
            font-size: 0.9rem;
            font-weight: 700;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 0.86rem;
        }

        .items-table th,
        .items-table td {
            padding: 11px 8px;
            border-bottom: 1px solid var(--receipt-line);
            vertical-align: top;
        }

        .items-table th {
            color: var(--receipt-muted);
            font-size: 0.68rem;
            font-weight: 850;
            letter-spacing: 0.08em;
            text-align: left;
            text-transform: uppercase;
        }

        .description-cell {
            color: var(--receipt-ink);
            font-weight: 700;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .totals {
            width: min(100%, 340px);
            margin: 18px 0 0 auto;
            padding: 14px 16px;
            border: 1px solid var(--receipt-line);
            border-radius: 8px;
            background: #fff;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 5px 0;
            color: var(--receipt-muted);
        }

        .total-row span:last-child {
            color: var(--receipt-ink);
            font-weight: 750;
            white-space: nowrap;
        }

        .grand {
            margin-top: 8px;
            padding-top: 13px;
            border-top: 2px solid var(--receipt-ink);
            color: var(--receipt-ink);
            font-size: 1.1rem;
            font-weight: 900;
        }

        .payment-note {
            margin-top: 18px;
            padding: 13px 14px;
            border-left: 4px solid var(--receipt-brand);
            border-radius: 8px;
            background: var(--receipt-soft);
            color: var(--receipt-muted);
        }

        .payment-note strong {
            color: var(--receipt-ink);
        }

        .footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
        }

        .thankyou {
            color: var(--receipt-ink);
            font-size: 1rem;
            font-weight: 850;
        }

        .footer-note {
            margin-top: 5px;
            color: var(--receipt-muted);
            font-size: 0.78rem;
        }

        .footer-contact {
            margin-top: 10px;
            color: var(--receipt-muted);
            font-size: 0.75rem;
        }

        .actions {
            margin-top: 16px;
            text-align: center;
        }

        .actions button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 18px;
            border: 1px solid var(--receipt-brand);
            border-radius: 8px;
            background: var(--receipt-brand);
            color: #fff;
            cursor: pointer;
            font-weight: 800;
        }

        @media (max-width: 640px) {
            body { padding: 12px; }

            .company-header {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 22px 18px 18px;
                text-align: center;
            }

            .company-logo {
                margin: 0 auto;
            }

            .company-title {
                font-size: 1.35rem;
            }

            .receipt-body {
                padding: 20px 16px 22px;
            }

            .receipt-heading {
                display: block;
            }

            .receipt-meta {
                grid-template-columns: 1fr;
                margin-top: 16px;
            }

            .items-table {
                font-size: 0.78rem;
            }

            .items-table th,
            .items-table td {
                padding: 9px 5px;
            }

            .items-table th:nth-child(3),
            .items-table td:nth-child(3) {
                display: none;
            }

            .totals {
                width: 100%;
            }
        }

        @page {
            margin: 10mm;
        }

        @media print {
            :root {
                --receipt-brand: #171717;
                --receipt-ink: #000;
                --receipt-muted: #333;
                --receipt-line: #d9d9d9;
                --receipt-soft: #fff;
            }

            html,
            body {
                width: 100%;
                margin: 0;
                padding: 0;
                background: #fff !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .actions,
            nav,
            aside,
            .navbar,
            .sidebar,
            .topbar,
            .app-header,
            .app-footer {
                display: none !important;
            }

            .receipt-shell {
                width: 100%;
                max-width: 760px;
                margin: 0 auto;
            }

            .receipt-card {
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .receipt-topbar {
                display: none;
            }

            .company-header,
            .receipt-heading,
            .totals,
            .payment-note,
            .footer,
            table,
            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .company-header {
                padding: 0 0 14px;
            }

            .receipt-body {
                padding: 16px 0 0;
            }
        }
    </style>
</head>
<body>
    <main class="receipt-shell">
        <article class="receipt-card">
            <div class="receipt-topbar"></div>

            <section class="company-header" aria-label="Dados da empresa">
                @if ($logoUrl)
                    <img class="company-logo" src="{{ $logoUrl }}" alt="Logo {{ $companyName }}">
                @endif

                <div>
                    <h1 class="company-title">{{ $companyName }}</h1>
                    <div class="company-details">
                        @if ($companyPhone)
                            <p>Telefone: {{ $companyPhone }}</p>
                        @endif
                        @if ($companyCnpj)
                            <p>CNPJ: {{ $companyCnpj }}</p>
                        @endif
                        @if ($companyAddress)
                            <p>{{ $companyAddress }}</p>
                        @endif
                        @if ($companyInstagram)
                            <p>Instagram: {{ $companyInstagram }}</p>
                        @endif
                    </div>
                </div>
            </section>

            <section class="receipt-body">
                <div class="receipt-heading">
                    <div>
                        <h2>Recibo</h2>
                        <div class="receipt-number">#{{ $order->id }}</div>
                    </div>

                    <div class="receipt-meta" aria-label="Dados do recibo">
                        <p class="meta-item">
                            <span class="meta-label">Data e hora</span>
                            <span class="meta-value">{{ $closedAt ?? 'Nao informado' }}</span>
                        </p>

                        @if ($order->client)
                            <p class="meta-item">
                                <span class="meta-label">Cliente</span>
                                <span class="meta-value">{{ $order->client->name }}</span>
                            </p>
                        @endif

                        @if ($order->professional)
                            <p class="meta-item">
                                <span class="meta-label">Profissional</span>
                                <span class="meta-value">{{ $order->professional->name }}</span>
                            </p>
                        @endif

                        <p class="meta-item">
                            <span class="meta-label">Forma de pagamento</span>
                            <span class="meta-value">{{ $paymentLabel }}</span>
                        </p>

                        @if ($paymentStatus)
                            <p class="meta-item">
                                <span class="meta-label">Status</span>
                                <span class="meta-value">{{ $paymentStatus }}</span>
                            </p>
                        @endif
                    </div>
                </div>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Descri&ccedil;&atilde;o</th>
                            <th class="num">Qtd</th>
                            <th class="num">Unit.</th>
                            <th class="num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="description-cell">{{ $item->description }}</td>
                                <td class="num">{{ $item->quantity }}</td>
                                <td class="num">R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                                <td class="num">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="totals">
                    <div class="total-row">
                        <span>Subtotal servi&ccedil;os</span>
                        <span>R$ {{ number_format((float) $order->subtotal_services, 2, ',', '.') }}</span>
                    </div>
                    <div class="total-row">
                        <span>Subtotal produtos</span>
                        <span>R$ {{ number_format((float) $order->subtotal_products, 2, ',', '.') }}</span>
                    </div>
                    @if ($hasDiscount)
                        <div class="total-row">
                            <span>Desconto</span>
                            <span>- R$ {{ number_format((float) $order->discount, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="total-row grand">
                        <span>Total</span>
                        <span>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</span>
                    </div>
                </div>

                @if ($notes)
                    <div class="payment-note">
                        <strong>Observa&ccedil;&otilde;es:</strong> {{ $notes }}
                    </div>
                @endif

                <footer class="footer">
                    <p class="thankyou">Obrigado pela prefer&ecirc;ncia!</p>
                    <p class="footer-note">Documento sem valor fiscal.</p>
                    <p class="footer-note">Este recibo n&atilde;o substitui nota fiscal.</p>
                    @if ($contactLine)
                        <p class="footer-contact">{{ $contactLine }}</p>
                    @endif
                </footer>
            </section>
        </article>

        <div class="actions">
            <button type="button" onclick="window.print()">Imprimir</button>
        </div>
    </main>
</body>
</html>
