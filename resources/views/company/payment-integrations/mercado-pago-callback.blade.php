<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[var(--app-shell-bg)] text-[var(--text-main)]">
        <div class="mx-auto flex min-h-screen max-w-xl items-center justify-center px-6 py-10">
            <div class="sf-card w-full p-6 sm:p-8">
                <p class="sf-page-eyebrow">Mercado Pago</p>
                <h1 class="sf-page-title mt-2 text-3xl">{{ $title }}</h1>
                <p class="sf-page-subtitle mt-3">{{ $message }}</p>

                <div class="mt-6 rounded-2xl border border-emerald-400/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                    O StudioFlow vai atualizar a tela principal automaticamente sempre que esta janela tiver sido aberta como popup.
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ $redirectUrl }}" class="sf-button-primary text-sm">Voltar ao StudioFlow</a>
                    <button type="button" class="sf-button-secondary text-sm" onclick="window.close()">Fechar janela</button>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const redirectUrl = @js($redirectUrl);

                try {
                    if (window.opener && !window.opener.closed) {
                        window.opener.location = redirectUrl;
                        window.setTimeout(() => window.close(), 900);
                    }
                } catch (error) {
                    window.setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 1200);
                }
            })();
        </script>
    </body>
</html>
