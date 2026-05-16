<?php

namespace App\Services\Payments\Gateways;

use App\Enums\MembershipPaymentBillingType;
use App\Models\Appointment;
use App\Models\BookingPayment;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipPayment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\ParsedGatewayWebhook;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly CompanyPaymentIntegration $integration,
    ) {}

    public function createCustomer(Client $client): array
    {
        $this->ensureFreshAccessToken();

        return [
            'customer_id' => 'client:'.$client->id,
            'raw' => [
                'email' => $client->email,
                'name' => $client->name,
            ],
        ];
    }

    public function createMembershipCharge(CustomerMembership $membership, array $options = []): array
    {
        $token = $this->ensureFreshAccessToken();
        $client = $membership->client()->firstOrFail();
        $plan = $membership->plan()->firstOrFail();

        $payload = [
            'items' => [[
                'id' => 'membership-plan-'.$plan->id,
                'title' => 'Assinatura: '.$plan->name,
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => (float) $plan->price,
            ]],
            'payer' => array_filter([
                'email' => $client->email,
                'name' => $client->name,
            ]),
            'external_reference' => 'membership:'.$membership->id,
            'notification_url' => route('webhooks.company-payments.mercado-pago'),
            'back_urls' => [
                'success' => route('dashboard'),
                'pending' => route('dashboard'),
                'failure' => route('dashboard'),
            ],
            'auto_return' => 'approved',
            'statement_descriptor' => mb_substr($plan->name, 0, 13),
            'metadata' => [
                'company_id' => (int) $membership->company_id,
                'membership_id' => (int) $membership->id,
                'client_id' => (int) $client->id,
            ],
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->apiBaseUrl().'/checkout/preferences', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Mercado Pago: não foi possível criar a cobrança para esta empresa.');
        }

        $data = $response->json();
        $preferenceId = (string) ($data['id'] ?? '');
        if ($preferenceId === '') {
            throw new RuntimeException('Mercado Pago: resposta inválida ao criar cobrança.');
        }

        return [
            'provider_payment_id' => $preferenceId,
            'provider_subscription_id' => null,
            'invoice_url' => $data['init_point'] ?? $data['sandbox_init_point'] ?? null,
            'pix_qr_code' => null,
            'pix_copy_paste' => null,
            'billing_type' => MembershipPaymentBillingType::Unknown,
            'due_date' => $options['due_date'] ?? now()->addDays(3)->toDateString(),
            'amount' => (float) $plan->price,
            'status' => 'pending',
            'raw' => $data,
        ];
    }

    /**
     * @return array{preference_id:string,checkout_url:?string,init_point:?string,sandbox_init_point:?string,raw:array<string,mixed>}
     */
    public function createBookingPayment(Company $company, Appointment $appointment, BookingPayment $payment): array
    {
        $token = $this->ensureFreshAccessToken();
        $description = 'Reserva de horario - '.$appointment->bookedServices()->pluck('name')->join(', ').' - '.$company->name;

        $payload = [
            'items' => [[
                'id' => 'booking-payment-'.$payment->id,
                'title' => mb_substr($description, 0, 120),
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => (float) $payment->amount,
            ]],
            'payer' => array_filter([
                'email' => $appointment->client?->email,
                'name' => $appointment->client?->name,
            ]),
            'external_reference' => $payment->external_reference,
            'notification_url' => route('webhooks.company-payments.mercado-pago', [
                'payment_context' => 'booking',
                'company_id' => $company->id,
                'external_reference' => $payment->external_reference,
            ]),
            'back_urls' => [
                'success' => route('public-bookings.payment.success', [
                    'company' => $company,
                    'reference' => $payment->external_reference,
                ]),
                'pending' => route('public-bookings.payment.pending', [
                    'company' => $company,
                    'reference' => $payment->external_reference,
                ]),
                'failure' => route('public-bookings.payment.failure', [
                    'company' => $company,
                    'reference' => $payment->external_reference,
                ]),
            ],
            'auto_return' => 'approved',
            'statement_descriptor' => mb_substr($company->name, 0, 13),
            'metadata' => [
                'company_id' => (int) $company->id,
                'appointment_id' => (int) $appointment->id,
                'booking_payment_id' => (int) $payment->id,
                'payment_type' => (string) $payment->payment_type,
            ],
        ];

        if ($payment->expires_at) {
            $payload['expires'] = true;
            $payload['expiration_date_to'] = $payment->expires_at->copy()->utc()->toIso8601String();
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->apiBaseUrl().'/checkout/preferences', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Mercado Pago: nao foi possivel criar a cobranca de reserva para esta empresa.');
        }

        $data = $response->json();
        $preferenceId = (string) ($data['id'] ?? '');
        if ($preferenceId === '') {
            throw new RuntimeException('Mercado Pago: resposta invalida ao criar a cobranca de reserva.');
        }

        return [
            'preference_id' => $preferenceId,
            'checkout_url' => $data['init_point'] ?? $data['sandbox_init_point'] ?? null,
            'init_point' => $data['init_point'] ?? null,
            'sandbox_init_point' => $data['sandbox_init_point'] ?? null,
            'raw' => $data,
        ];
    }

    public function cancelCharge(MembershipPayment $payment): void
    {
        throw new RuntimeException('Mercado Pago: cancelamento desta cobrança ainda não está disponível no StudioFlow.');
    }

    public function getPaymentStatus(MembershipPayment $payment): array
    {
        if (! $payment->provider_payment_id) {
            return [];
        }

        $token = $this->ensureFreshAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->apiBaseUrl().'/checkout/preferences/'.$payment->provider_payment_id);

        if (! $response->successful()) {
            throw new RuntimeException('Mercado Pago: não foi possível consultar a cobrança da empresa.');
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPaymentDetails(string $externalPaymentId): array
    {
        $token = $this->ensureFreshAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->apiBaseUrl().'/v1/payments/'.$externalPaymentId);

        if (! $response->successful()) {
            throw new RuntimeException('Mercado Pago: nao foi possivel consultar o pagamento da reserva.');
        }

        return $response->json();
    }

    public function parseWebhook(array $payload): ParsedGatewayWebhook
    {
        return new ParsedGatewayWebhook(
            event: (string) ($payload['type'] ?? $payload['action'] ?? 'unknown'),
            providerPaymentId: (string) ($payload['data']['id'] ?? $payload['id'] ?? ''),
            payload: $payload,
        );
    }

    public function ping(): void
    {
        $token = $this->ensureFreshAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->apiBaseUrl().'/users/me');

        if (! $response->successful()) {
            throw new RuntimeException('Mercado Pago: credenciais inválidas ou conta não autorizada.');
        }
    }

    public function refreshAccessToken(CompanyPaymentIntegration $integration): CompanyPaymentIntegration
    {
        $refreshToken = trim((string) $integration->refresh_token);
        if ($refreshToken === '') {
            throw new RuntimeException('Conecte a conta Mercado Pago da empresa novamente para renovar o acesso.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->post($this->apiBaseUrl().'/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => (string) config('services.mercado_pago.client_id'),
                    'client_secret' => (string) config('services.mercado_pago.client_secret'),
                    'refresh_token' => $refreshToken,
                ])
                ->throw();
        } catch (RequestException $e) {
            $integration->forceFill(['status' => 'error'])->save();

            throw new RuntimeException('Mercado Pago: não foi possível renovar o acesso da empresa. Reconecte a conta.');
        }

        $payload = $response->json();
        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        if ($accessToken === '') {
            $integration->forceFill(['status' => 'error'])->save();

            throw new RuntimeException('Mercado Pago: renovação inválida. Reconecte a conta da empresa.');
        }

        $integration->forceFill([
            'access_token' => $accessToken,
            'refresh_token' => trim((string) ($payload['refresh_token'] ?? '')) !== ''
                ? (string) $payload['refresh_token']
                : $integration->refresh_token,
            'public_key' => $payload['public_key'] ?? $integration->public_key,
            'account_identifier' => (string) ($payload['user_id'] ?? $payload['collector_id'] ?? $integration->account_identifier ?? ''),
            'expires_at' => isset($payload['expires_in']) ? now()->addSeconds((int) $payload['expires_in']) : $integration->expires_at,
            'status' => 'connected',
            'active' => true,
            'metadata' => array_filter(array_merge($integration->metadata ?? [], [
                'oauth' => Arr::except($payload, ['access_token', 'refresh_token']),
                'last_refresh_at' => now()->toIso8601String(),
            ])),
        ])->save();

        return $integration->fresh();
    }

    private function ensureFreshAccessToken(): string
    {
        $integration = $this->integration->fresh() ?? $this->integration;

        if (! $integration->active) {
            throw new RuntimeException('Conecte a conta Mercado Pago da empresa antes de gerar cobranças.');
        }

        $token = trim((string) $integration->access_token);
        if ($token === '') {
            throw new RuntimeException('Conecte a conta Mercado Pago da empresa antes de gerar cobranças.');
        }

        if ($integration->tokenExpiresSoon()) {
            $integration = $this->refreshAccessToken($integration);
            $token = trim((string) $integration->access_token);
        }

        return $token;
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) config('services.mercado_pago.api_base_url'), '/');
    }
}
