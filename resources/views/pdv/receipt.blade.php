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
        : '#b8913b';

    $companyName = $company?->safeDisplayText($company->name ?? null) ?? 'Empresa';
    $companyNameParts = preg_split('/\s+/', trim($companyName), 2) ?: [$companyName];
    $companyBrandMain = $companyNameParts[0] ?? $companyName;
    $companyBrandSub = $companyNameParts[1] ?? null;
    $companyPhone = $company?->safeDisplayText($company->phone ?? null);
    $companyCnpj = $company?->safeDisplayText($company->cnpj ?? null);
    $companyAddress = $company?->safeDisplayText($company->address ?? null);
    $companyInstagram = $company?->safeDisplayText($company->instagram ?? null);
    $logoUrl = $company?->logo_url;
    $closedAt = $order->closed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
    $receiptNumber = str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
    $paymentStatus = $order->status === \App\Models\ServiceOrder::STATUS_PAID ? 'Pago' : null;
    $hasDiscount = (float) $order->discount > 0;

    $addressIsUrl = $companyAddress && filter_var($companyAddress, FILTER_VALIDATE_URL);
    $addressUrl = $addressIsUrl
        ? $companyAddress
        : ($companyAddress ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($companyAddress) : null);
    $addressDisplay = $addressIsUrl ? null : $companyAddress;

    $phoneDigits = $companyPhone ? preg_replace('/\D+/', '', $companyPhone) : '';
    if ($phoneDigits !== '' && ! str_starts_with($phoneDigits, '55') && in_array(strlen($phoneDigits), [10, 11], true)) {
        $phoneDigits = '55'.$phoneDigits;
    }
    $whatsAppUrl = $phoneDigits !== '' ? 'https://wa.me/'.$phoneDigits : null;

    $instagramValue = $companyInstagram;
    $instagramUrl = null;
    $instagramDisplay = null;
    if ($instagramValue) {
        if (filter_var($instagramValue, FILTER_VALIDATE_URL)) {
            $instagramUrl = $instagramValue;
            $path = trim((string) parse_url($instagramValue, PHP_URL_PATH), '/');
            $instagramDisplay = $path !== '' ? '@'.explode('/', $path)[0] : 'Instagram';
        } else {
            $handle = ltrim($instagramValue, '@');
            $instagramUrl = 'https://www.instagram.com/'.$handle;
            $instagramDisplay = '@'.$handle;
        }
    }

    $qrTarget = $whatsAppUrl ?: ($instagramUrl ?: $addressUrl);
    $qrCodeUrl = $qrTarget
        ? 'https://quickchart.io/qr?size=148&margin=1&text='.rawurlencode($qrTarget)
        : null;
    $qrLabel = $whatsAppUrl
        ? 'Fale conosco'
        : ($instagramUrl ? 'Acompanhe no Instagram' : ($addressUrl ? 'Ver localizacao' : null));
    $footerDetails = collect([
        $addressDisplay,
        $companyPhone,
        $companyCnpj ? 'CNPJ: '.$companyCnpj : null,
        $instagramDisplay,
    ])->filter()->values();
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
            --receipt-ink: #141414;
            --receipt-muted: #666a70;
            --receipt-line: #e7e2d8;
            --receipt-soft: #f5f5f3;
            --receipt-panel: #f7f7f6;
            --receipt-black: #111;
        }

        * { box-sizing: border-box; }

        html {
            background: #ececec;
        }

        body {
            margin: 0;
            background: #ececec;
            color: var(--receipt-ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            line-height: 1.45;
        }

        .receipt-page {
            min-height: 100vh;
            padding: 28px 14px 36px;
        }

        .receipt-sheet {
            width: min(100%, 940px);
            margin: 0 auto;
            padding: 48px 56px 36px;
            border: 1px solid rgba(20, 20, 20, 0.08);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.15);
        }

        .receipt-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 28px;
            align-items: center;
        }

        .receipt-brand {
            display: flex;
            gap: 20px;
            align-items: center;
            min-width: 0;
        }

        .receipt-logo {
            width: 124px;
            height: 124px;
            object-fit: contain;
            flex: 0 0 auto;
            border: 0;
            border-radius: 50%;
            padding: 0;
            background: #fff;
        }

        .receipt-logo-fallback {
            display: grid;
            place-items: center;
            width: 104px;
            height: 104px;
            flex: 0 0 auto;
            border-radius: 50%;
            background: #081026;
            color: var(--receipt-brand);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 2.7rem;
            font-weight: 900;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .receipt-company-name {
            margin: 0;
            color: var(--receipt-black);
            font-size: clamp(1.8rem, 4vw, 3.1rem);
            font-weight: 900;
            letter-spacing: 0.16em;
            line-height: 0.98;
            text-transform: uppercase;
            overflow-wrap: anywhere;
        }

        .receipt-tagline {
            margin: 12px 0 0;
            color: var(--receipt-muted);
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.55em;
            text-transform: uppercase;
        }

        .receipt-meta {
            min-width: 190px;
            text-align: right;
        }

        .receipt-meta-title {
            margin: 0;
            color: var(--receipt-black);
            font-size: 2.35rem;
            font-weight: 900;
            letter-spacing: 0.1em;
            line-height: 1;
            text-transform: uppercase;
        }

        .receipt-meta-number {
            margin-top: 12px;
            color: var(--receipt-brand);
            font-size: 1.35rem;
            font-weight: 900;
        }

        .receipt-meta-label {
            margin: 18px 0 0;
            color: var(--receipt-black);
            font-size: 0.92rem;
            font-weight: 800;
        }

        .receipt-meta-date {
            margin-top: 5px;
            color: var(--receipt-muted);
            font-size: 0.92rem;
            font-weight: 700;
        }

        .receipt-accent-line {
            height: 3px;
            margin: 36px 0 34px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--receipt-brand), rgba(184, 145, 59, 0.18));
        }

        .receipt-business-info {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 0.86fr);
            gap: 48px;
            align-items: center;
            margin-bottom: 40px;
            padding: 0 34px;
        }

        .receipt-info-column {
            display: grid;
            gap: 19px;
            align-content: start;
            padding-right: 34px;
            border-right: 2px solid var(--receipt-brand);
        }

        .receipt-info-line {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: 18px;
            align-items: start;
            color: var(--receipt-black);
            font-size: 0.98rem;
        }

        .receipt-info-icon {
            display: inline-grid;
            place-items: center;
            width: 25px;
            height: 25px;
            border: 2px solid var(--receipt-black);
            border-radius: 7px;
            color: var(--receipt-black);
            font-size: 0.78rem;
            font-weight: 950;
            line-height: 1;
        }

        .receipt-info-icon-round {
            border-radius: 50%;
        }

        .receipt-info-label {
            display: inline;
            color: var(--receipt-black);
            font-size: inherit;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: none;
        }

        .receipt-info-text {
            overflow-wrap: anywhere;
        }

        .receipt-info-text a {
            color: var(--receipt-ink);
            font-weight: 800;
            text-decoration: none;
        }

        .receipt-message-panel {
            display: grid;
            place-items: center;
            min-height: 210px;
            text-align: center;
        }

        .receipt-message {
            color: var(--receipt-black);
            font-family: "Brush Script MT", "Segoe Script", cursive;
            font-size: 1.75rem;
            font-weight: 500;
            line-height: 1.55;
        }

        .receipt-message::after {
            display: block;
            width: 110px;
            height: 2px;
            margin: 16px auto 0;
            border-radius: 999px;
            background: var(--receipt-brand);
            content: "";
        }

        .receipt-sale-box {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(260px, 0.9fr);
            gap: 40px;
            position: relative;
            overflow: hidden;
            margin-bottom: 34px;
            padding: 28px 32px;
            border: 1px solid #d9d9d9;
            border-radius: 0;
            background: var(--receipt-panel);
        }

        .receipt-sale-item {
            min-width: 0;
            display: grid;
            grid-template-columns: 170px minmax(0, 1fr);
            gap: 18px;
            padding: 0;
            background: transparent;
        }

        .receipt-sale-label {
            display: block;
            margin-bottom: 4px;
            color: var(--receipt-black);
            font-size: 0.76rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .receipt-sale-value {
            color: var(--receipt-black);
            font-size: 1.04rem;
            font-weight: 500;
            overflow-wrap: anywhere;
        }

        .receipt-sale-column {
            display: grid;
            gap: 24px;
            align-content: start;
        }

        .receipt-status-pill {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 8px;
            background: #ccf2cd;
            color: #147a2e;
            font-weight: 900;
        }

        .receipt-sale-watermark {
            position: absolute;
            right: 58px;
            top: 50%;
            color: rgba(17, 17, 17, 0.055);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 8rem;
            font-weight: 700;
            line-height: 1;
            pointer-events: none;
            transform: translateY(-50%);
        }

        .receipt-items {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 0.94rem;
        }

        .receipt-items thead th {
            padding: 15px 28px;
            background: var(--receipt-black);
            color: #fff;
            font-size: 0.82rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-align: left;
            text-transform: uppercase;
        }

        .receipt-items thead th:first-child {
            border-top-left-radius: 0;
        }

        .receipt-items thead th:last-child {
            border-top-right-radius: 0;
        }

        .receipt-items tbody td {
            padding: 18px 28px;
            border-bottom: 1px solid var(--receipt-line);
            vertical-align: top;
        }

        .receipt-description {
            color: var(--receipt-black);
            font-weight: 800;
        }

        .receipt-num {
            text-align: right;
            white-space: nowrap;
        }

        .receipt-summary {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
            gap: 26px;
            align-items: start;
            margin-top: 26px;
        }

        .receipt-payment {
            padding: 0;
            border-left: 0;
            border-radius: 0;
            background: transparent;
        }

        .receipt-payment p {
            margin: 0;
        }

        .receipt-payment strong {
            color: var(--receipt-black);
        }

        .receipt-payment-muted {
            margin-top: 5px;
            color: var(--receipt-muted);
            font-size: 0.86rem;
        }

        .receipt-totals {
            display: grid;
            gap: 8px;
            padding: 18px 20px;
            border: 0;
            border-radius: 0;
            background: #fff;
        }

        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            color: var(--receipt-muted);
        }

        .receipt-total-row span:last-child {
            color: var(--receipt-black);
            font-weight: 850;
            white-space: nowrap;
        }

        .receipt-total-row-main {
            margin-top: 8px;
            padding-top: 14px;
            border-top: 4px solid var(--receipt-black);
            border-bottom: 2px solid var(--receipt-brand);
            padding-bottom: 12px;
            color: var(--receipt-black);
            font-size: 1.35rem;
            font-weight: 950;
        }

        .receipt-total-row-main span:last-child {
            color: var(--receipt-brand);
            font-size: 1.5rem;
            font-weight: 950;
        }

        .receipt-note {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 8px;
            background: #fff9eb;
            color: #4f4327;
            font-size: 0.92rem;
        }

        .receipt-thankyou {
            margin: 38px 0 0;
            text-align: center;
            color: var(--receipt-black);
            font-size: 1.25rem;
            font-weight: 900;
        }

        .receipt-footer {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 24px;
            align-items: center;
            margin-top: 34px;
            padding: 22px 26px;
            border-top: 0;
            background: var(--receipt-panel);
            color: var(--receipt-muted);
            font-size: 0.82rem;
        }

        .receipt-footer p {
            margin: 0;
        }

        .receipt-footer-legal {
            display: grid;
            gap: 4px;
            justify-items: center;
            margin-top: 14px;
            color: var(--receipt-muted);
            text-align: center;
        }

        .receipt-footer-brand {
            display: grid;
            gap: 5px;
        }

        .receipt-footer-brand strong {
            color: var(--receipt-brand);
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .receipt-footer-details {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 10px;
        }

        .receipt-footer-details span + span::before {
            color: var(--receipt-brand);
            content: "|";
            margin-right: 10px;
        }

        .receipt-footer-qr-wrap {
            display: grid;
            grid-template-columns: auto auto;
            gap: 28px;
            align-items: center;
            padding-left: 38px;
            border-left: 2px solid var(--receipt-brand);
        }

        .receipt-footer-qr-copy {
            max-width: 120px;
            color: var(--receipt-muted);
            font-size: 0.86rem;
            line-height: 1.35;
            text-align: right;
        }

        .receipt-qr {
            display: grid;
            gap: 7px;
            justify-items: center;
            text-align: center;
        }

        .receipt-qr img {
            width: 118px;
            height: 118px;
            border: 0;
            border-radius: 0;
            padding: 0;
            background: #fff;
        }

        .receipt-qr span {
            color: var(--receipt-black);
            font-size: 0.72rem;
            font-weight: 850;
        }

        .receipt-print-button {
            display: flex;
            justify-content: center;
            margin-top: 18px;
        }

        .receipt-print-button button {
            min-height: 44px;
            padding: 0 22px;
            border: 1px solid var(--receipt-black);
            border-radius: 8px;
            background: var(--receipt-black);
            color: #fff;
            cursor: pointer;
            font-weight: 850;
        }

        @media (max-width: 720px) {
            .receipt-page {
                padding: 12px;
            }

            .receipt-sheet {
                padding: 24px 16px;
            }

            .receipt-header,
            .receipt-business-info,
            .receipt-summary,
            .receipt-footer {
                grid-template-columns: 1fr;
            }

            .receipt-brand {
                align-items: flex-start;
            }

            .receipt-logo,
            .receipt-logo-fallback {
                width: 72px;
                height: 72px;
                font-size: 1.7rem;
            }

            .receipt-meta {
                text-align: left;
            }

            .receipt-meta-title {
                font-size: 1.75rem;
            }

            .receipt-business-info {
                gap: 12px;
                padding: 0;
            }

            .receipt-info-column {
                padding-right: 0;
                border-right: 0;
            }

            .receipt-message-panel {
                min-height: 120px;
            }

            .receipt-message {
                font-size: 1.35rem;
            }

            .receipt-sale-box {
                grid-template-columns: 1fr;
                gap: 24px;
                padding: 22px 18px;
            }

            .receipt-sale-item {
                grid-template-columns: 1fr;
                gap: 4px;
            }

            .receipt-sale-watermark {
                display: none;
            }

            .receipt-items {
                font-size: 0.82rem;
            }

            .receipt-items thead th,
            .receipt-items tbody td {
                padding: 10px 6px;
            }

            .receipt-items th:nth-child(3),
            .receipt-items td:nth-child(3) {
                display: none;
            }

            .receipt-totals {
                padding: 16px;
            }

            .receipt-total-row-main {
                font-size: 1.15rem;
            }

            .receipt-total-row-main span:last-child {
                font-size: 1.25rem;
            }

            .receipt-footer {
                text-align: center;
            }

            .receipt-footer-qr-wrap {
                grid-template-columns: 1fr;
                justify-items: center;
                gap: 12px;
                padding-left: 0;
                border-left: 0;
            }

            .receipt-footer-qr-copy {
                text-align: center;
            }

            .receipt-qr {
                justify-self: center;
            }
        }

        @page {
            margin: 10mm;
        }

        @media print {
            :root {
                --receipt-brand: {{ $brandColor }};
                --receipt-ink: #000;
                --receipt-muted: #333;
                --receipt-line: #d8d8d8;
                --receipt-soft: #f4f4f4;
                --receipt-black: #000;
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

            .receipt-page {
                min-height: auto;
                padding: 0;
                background: #fff !important;
            }

            .receipt-sheet {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .receipt-print-button,
            nav,
            aside,
            .navbar,
            .sidebar,
            .topbar,
            .app-header,
            .app-footer {
                display: none !important;
            }

            .receipt-header,
            .receipt-business-info,
            .receipt-sale-box,
            .receipt-summary,
            .receipt-totals,
            .receipt-footer,
            table,
            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .receipt-items thead th {
                background: #000 !important;
                color: #fff !important;
            }
        }
    </style>
</head>
<body>
    <main class="receipt-page">
        <article class="receipt-sheet">
            <header class="receipt-header">
                <section class="receipt-brand" aria-label="Empresa">
                    @if ($logoUrl)
                        <img class="receipt-logo" src="{{ $logoUrl }}" alt="Logo {{ $companyName }}">
                    @else
                        <div class="receipt-logo-fallback" aria-hidden="true">{{ mb_substr($companyName, 0, 1) }}</div>
                    @endif

                    <div>
                        <h1 class="receipt-company-name">{{ $companyBrandMain }}</h1>
                        @if ($companyBrandSub)
                            <p class="receipt-tagline">{{ $companyBrandSub }}</p>
                        @endif
                    </div>
                </section>

                <section class="receipt-meta" aria-label="Dados do recibo">
                    <p class="receipt-meta-title">Recibo</p>
                    <p class="receipt-meta-number">N&ordm; {{ $receiptNumber }}</p>
                    <p class="receipt-meta-label">Data e hora</p>
                    <p class="receipt-meta-date">{{ $closedAt ?? 'Data nao informada' }}</p>
                </section>
            </header>

            <div class="receipt-accent-line"></div>

            @if ($companyPhone || $addressDisplay || $addressUrl || $instagramDisplay || $companyCnpj)
                <section class="receipt-business-info" aria-label="Dados comerciais">
                    <div class="receipt-info-column">
                        @if ($addressDisplay || $addressUrl)
                            <div class="receipt-info-line">
                                <span class="receipt-info-icon receipt-info-icon-round">P</span>
                                <span class="receipt-info-text">
                                    @if ($addressDisplay)
                                        {{ $addressDisplay }}
                                    @elseif ($addressUrl)
                                        <a href="{{ $addressUrl }}" target="_blank" rel="noopener">Ver localizacao</a>
                                    @endif
                                </span>
                            </div>
                        @endif

                        @if ($companyPhone)
                            <div class="receipt-info-line">
                                <span class="receipt-info-icon receipt-info-icon-round">T</span>
                                <span class="receipt-info-text">
                                    {{ $companyPhone }}
                                </span>
                            </div>
                        @endif

                        @if ($companyCnpj)
                            <div class="receipt-info-line">
                                <span class="receipt-info-icon">ID</span>
                                <span class="receipt-info-text">
                                    <span class="receipt-info-label">CNPJ</span> {{ $companyCnpj }}
                                </span>
                            </div>
                        @endif

                        @if ($instagramDisplay)
                            <div class="receipt-info-line">
                                <span class="receipt-info-icon receipt-info-icon-round">IG</span>
                                <span class="receipt-info-text">
                                    <span class="receipt-info-label">Instagram:</span>
                                    <a href="{{ $instagramUrl }}" target="_blank" rel="noopener">{{ $instagramDisplay }}</a>
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="receipt-message-panel" aria-label="Mensagem institucional">
                        <div class="receipt-message">
                            Mais que um corte,<br>
                            &eacute; sobre autoestima.
                        </div>
                    </div>
                </section>
            @endif

            <section class="receipt-sale-box" aria-label="Dados da venda">
                <div class="receipt-sale-column">
                    <div class="receipt-sale-item">
                        <span class="receipt-sale-label">Comanda / venda</span>
                        <span class="receipt-sale-value">#{{ $order->id }}</span>
                    </div>

                    @if ($order->client)
                        <div class="receipt-sale-item">
                            <span class="receipt-sale-label">Cliente</span>
                            <span class="receipt-sale-value">{{ $order->client->name }}</span>
                        </div>
                    @endif

                    @if ($order->professional)
                        <div class="receipt-sale-item">
                            <span class="receipt-sale-label">Profissional</span>
                            <span class="receipt-sale-value">{{ $order->professional->name }}</span>
                        </div>
                    @endif
                </div>

                <div class="receipt-sale-column">
                    <div class="receipt-sale-item">
                        <span class="receipt-sale-label">Forma de pagamento</span>
                        <span class="receipt-sale-value">{{ $paymentLabel }}</span>
                    </div>

                    <div class="receipt-sale-item">
                        <span class="receipt-sale-label">Status</span>
                        <span class="receipt-sale-value">
                            @if ($paymentStatus)
                                <span class="receipt-status-pill">{{ $paymentStatus }}</span>
                            @else
                                Nao informado
                            @endif
                        </span>
                    </div>
                </div>

                <div class="receipt-sale-watermark" aria-hidden="true">{{ mb_substr($companyName, 0, 1) }}</div>
            </section>

            <table class="receipt-items">
                <thead>
                    <tr>
                        <th>Descri&ccedil;&atilde;o</th>
                        <th class="receipt-num">Qtd</th>
                        <th class="receipt-num">Unit.</th>
                        <th class="receipt-num">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="receipt-description">{{ $item->description }}</td>
                            <td class="receipt-num">{{ $item->quantity }}</td>
                            <td class="receipt-num">R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                            <td class="receipt-num">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <section class="receipt-summary">
                <div>
                    <div class="receipt-payment">
                        <p><strong>Forma de pagamento:</strong> {{ $paymentLabel }}</p>
                    </div>

                    @if ($notes)
                        <div class="receipt-note">
                            <strong>Observa&ccedil;&otilde;es:</strong> {{ $notes }}
                        </div>
                    @endif
                </div>

                <div class="receipt-totals">
                    <div class="receipt-total-row">
                        <span>Subtotal servi&ccedil;os</span>
                        <span>R$ {{ number_format((float) $order->subtotal_services, 2, ',', '.') }}</span>
                    </div>
                    <div class="receipt-total-row">
                        <span>Subtotal produtos</span>
                        <span>R$ {{ number_format((float) $order->subtotal_products, 2, ',', '.') }}</span>
                    </div>
                    @if ($hasDiscount)
                        <div class="receipt-total-row">
                            <span>Desconto</span>
                            <span>- R$ {{ number_format((float) $order->discount, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="receipt-total-row receipt-total-row-main">
                        <span>Total</span>
                        <span>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </section>

            <p class="receipt-thankyou">Obrigado pela prefer&ecirc;ncia!</p>

            <div class="receipt-footer-legal">
                <p>Documento sem valor fiscal.</p>
                <p>Este recibo n&atilde;o substitui nota fiscal.</p>
            </div>

            <footer class="receipt-footer">
                <div class="receipt-footer-brand">
                    <strong>{{ $companyName }}</strong>
                    @if ($footerDetails->isNotEmpty())
                        <div class="receipt-footer-details">
                            @foreach ($footerDetails as $detail)
                                <span>{{ $detail }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($qrCodeUrl && $qrLabel)
                    <div class="receipt-footer-qr-wrap">
                        <div class="receipt-footer-qr-copy">Escaneie para falar conosco</div>
                        <div class="receipt-qr">
                            <img src="{{ $qrCodeUrl }}" alt="{{ $qrLabel }}">
                        </div>
                    </div>
                @endif
            </footer>
        </article>

        <div class="receipt-print-button">
            <button type="button" onclick="window.print()">Imprimir</button>
        </div>
    </main>
</body>
</html>
