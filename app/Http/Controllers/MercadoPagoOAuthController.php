<?php

namespace App\Http\Controllers;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Models\CompanyPaymentIntegration;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MercadoPagoOAuthController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $oauthConfig = $this->validatedOauthConfig();
        if ($oauthConfig === null) {
            Log::warning('oauth_error', [
                'provider' => 'mercado_pago',
                'stage' => 'connect',
                'reason' => 'invalid_oauth_configuration',
                'company_id' => (int) $request->user()->company_id,
                'user_id' => (int) $request->user()->id,
            ]);

            return redirect()
                ->route('company.payment-integrations.index')
                ->withErrors(['oauth' => 'A conexao automatica do Mercado Pago nao esta disponivel agora. Entre em contato com o suporte para habilitar essa integracao.']);
        }

        $state = Str::random(64);

        session([
            'mercado_pago_oauth_state' => [
                'state' => $state,
                'company_id' => (int) $request->user()->company_id,
                'issued_at' => now()->timestamp,
            ],
        ]);

        Log::info('oauth_start', [
            'provider' => 'mercado_pago',
            'company_id' => (int) $request->user()->company_id,
            'user_id' => (int) $request->user()->id,
        ]);

        $query = http_build_query([
            'client_id' => $oauthConfig['client_id'],
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => $oauthConfig['redirect_uri'],
        ]);

        return redirect()->away($oauthConfig['auth_base_url'].'/authorization?'.$query);
    }

    public function callback(Request $request): RedirectResponse|View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        Log::info('oauth_callback', [
            'provider' => 'mercado_pago',
            'company_id' => (int) $request->user()->company_id,
            'user_id' => (int) $request->user()->id,
            'has_code' => $request->filled('code'),
            'has_error' => $request->filled('error'),
        ]);

        $oauthState = session('mercado_pago_oauth_state');
        session()->forget('mercado_pago_oauth_state');

        if (
            ! is_array($oauthState)
            || ! hash_equals((string) ($oauthState['state'] ?? ''), (string) $request->query('state', ''))
            || (int) ($oauthState['company_id'] ?? 0) !== (int) $request->user()->company_id
            || now()->timestamp - (int) ($oauthState['issued_at'] ?? 0) > 900
        ) {
            Log::warning('oauth_error', [
                'provider' => 'mercado_pago',
                'stage' => 'callback',
                'reason' => 'invalid_state',
                'company_id' => (int) $request->user()->company_id,
                'user_id' => (int) $request->user()->id,
            ]);

            return redirect()
                ->route('company.payment-integrations.index')
                ->withErrors(['oauth' => 'Falha de validacao da autorizacao do Mercado Pago. Tente conectar novamente.']);
        }

        if ($request->filled('error')) {
            $message = (string) $request->query('error_description', $request->query('error'));

            Log::warning('oauth_error', [
                'provider' => 'mercado_pago',
                'stage' => 'callback',
                'reason' => 'provider_returned_error',
                'company_id' => (int) $request->user()->company_id,
                'user_id' => (int) $request->user()->id,
                'provider_error' => (string) $request->query('error'),
            ]);

            return redirect()
                ->route('company.payment-integrations.index')
                ->withErrors(['oauth' => 'A conexao com o Mercado Pago nao foi concluida: '.$message]);
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            Log::warning('oauth_error', [
                'provider' => 'mercado_pago',
                'stage' => 'callback',
                'reason' => 'missing_code',
                'company_id' => (int) $request->user()->company_id,
                'user_id' => (int) $request->user()->id,
            ]);

            return redirect()
                ->route('company.payment-integrations.index')
                ->withErrors(['oauth' => 'A autorizacao expirou. Tente conectar novamente.']);
        }

        $oauthConfig = $this->validatedOauthConfig();
        if ($oauthConfig === null) {
            Log::warning('oauth_error', [
                'provider' => 'mercado_pago',
                'stage' => 'callback',
                'reason' => 'invalid_oauth_configuration',
                'company_id' => (int) $request->user()->company_id,
                'user_id' => (int) $request->user()->id,
            ]);

            return redirect()
                ->route('company.payment-integrations.index')
                ->withErrors(['oauth' => 'A conexao automatica do Mercado Pago nao esta disponivel agora. Entre em contato com o suporte para habilitar essa integracao.']);
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->post($oauthConfig['api_base_url'].'/oauth/token', [
                    'grant_type' => 'authorization_code',
                    'client_id' => $oauthConfig['client_id'],
                    'client_secret' => $oauthConfig['client_secret'],
                    'code' => $code,
                    'redirect_uri' => $oauthConfig['redirect_uri'],
                ])
                ->throw();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $message = is_array($body)
                ? (string) ($body['message'] ?? $body['error_description'] ?? $body['error'] ?? 'Nao foi possivel concluir a autorizacao.')
                : 'Nao foi possivel concluir a autorizacao.';

            if (str_contains(Str::lower($message), 'expired')) {
                $message = 'A autorizacao expirou. Tente conectar novamente.';
            }

            Log::warning('oauth_error', [
                'provider' => 'mercado_pago',
                'stage' => 'token_exchange',
                'reason' => 'request_exception',
                'company_id' => (int) $request->user()->company_id,
                'user_id' => (int) $request->user()->id,
                'provider_message' => $message,
            ]);

            return redirect()
                ->route('company.payment-integrations.index')
                ->withErrors(['oauth' => $message]);
        }

        $payload = $response->json();
        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));

        if ($accessToken === '') {
            Log::warning('oauth_error', [
                'provider' => 'mercado_pago',
                'stage' => 'token_exchange',
                'reason' => 'empty_access_token',
                'company_id' => (int) $request->user()->company_id,
                'user_id' => (int) $request->user()->id,
            ]);

            return redirect()
                ->route('company.payment-integrations.index')
                ->withErrors(['oauth' => 'O Mercado Pago nao retornou um access token valido.']);
        }

        $integration = CompanyPaymentIntegration::query()
            ->where('company_id', (int) $request->user()->company_id)
            ->where('provider', PaymentProvider::MercadoPago)
            ->latest('id')
            ->first() ?? new CompanyPaymentIntegration([
                'company_id' => (int) $request->user()->company_id,
                'provider' => PaymentProvider::MercadoPago,
            ]);

        $integration->fill([
            'name' => $integration->name ?: 'Mercado Pago',
            'environment' => $integration->environment ?? PaymentIntegrationEnvironment::Production,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken !== '' ? $refreshToken : $integration->refresh_token,
            'public_key' => $payload['public_key'] ?? $integration->public_key,
            'account_identifier' => (string) ($payload['user_id'] ?? $payload['collector_id'] ?? $integration->account_identifier ?? ''),
            'expires_at' => isset($payload['expires_in']) ? now()->addSeconds((int) $payload['expires_in']) : null,
            'connected_at' => now(),
            'status' => 'connected',
            'active' => true,
            'metadata' => array_filter(array_merge($integration->metadata ?? [], [
                'oauth' => Arr::except($payload, ['access_token', 'refresh_token']),
                'last_oauth_exchange_at' => now()->toIso8601String(),
            ])),
        ]);
        $integration->save();

        session()->flash('status', 'mercado-pago-connected');

        return view('company.payment-integrations.mercado-pago-callback', [
            'redirectUrl' => route('company.payment-integrations.index'),
            'title' => 'Mercado Pago conectado',
            'message' => 'A conta foi conectada com sucesso. Voce ja pode fechar esta janela.',
            'status' => 'success',
        ]);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $integration = CompanyPaymentIntegration::query()
            ->where('company_id', (int) $request->user()->company_id)
            ->where('provider', PaymentProvider::MercadoPago)
            ->latest('id')
            ->first();

        if ($integration) {
            $integration->update([
                'active' => false,
                'status' => 'disconnected',
            ]);
        }

        return redirect()
            ->route('company.payment-integrations.index')
            ->with('status', 'mercado-pago-disconnected');
    }

    /**
     * @return array{client_id:string,client_secret:string,redirect_uri:string,api_base_url:string,auth_base_url:string}|null
     */
    private function validatedOauthConfig(): ?array
    {
        $clientId = trim((string) config('services.mercado_pago.client_id'));
        $clientSecret = trim((string) config('services.mercado_pago.client_secret'));
        $redirectUri = trim((string) config('services.mercado_pago.oauth_redirect_uri'));
        $apiBaseUrl = rtrim((string) config('services.mercado_pago.api_base_url'), '/');
        $authBaseUrl = rtrim((string) config('services.mercado_pago.auth_base_url'), '/');

        if (
            $this->looksLikePlaceholder($clientId)
            || $this->looksLikePlaceholder($clientSecret)
            || $this->looksLikePlaceholder($redirectUri)
            || ! filter_var($redirectUri, FILTER_VALIDATE_URL)
        ) {
            return null;
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'api_base_url' => $apiBaseUrl,
            'auth_base_url' => $authBaseUrl,
        ];
    }

    private function looksLikePlaceholder(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        $upperValue = Str::upper($value);

        return Str::contains($upperValue, [
            'SEU_CLIENT_ID',
            'SEU_SECRET',
            'SEU_CLIENT_SECRET',
            'YOUR_CLIENT_ID',
            'YOUR_CLIENT_SECRET',
            '${APP_URL}',
        ]);
    }
}
