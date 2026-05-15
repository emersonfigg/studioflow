<?php

namespace App\Services\Payments\Gateways;

use App\Enums\MembershipPaymentBillingType;
use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Models\Client;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipPayment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\ParsedGatewayWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AsaasGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly CompanyPaymentIntegration $integration,
    ) {}

    public function createCustomer(Client $client): array
    {
        $token = $this->requireToken();

        $refs = $client->gateway_customer_refs ?? [];
        if (! empty($refs[PaymentProvider::Asaas->value])) {
            return ['customer_id' => (string) $refs[PaymentProvider::Asaas->value], 'raw' => []];
        }

        $payload = array_filter([
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'cpfCnpj' => $this->digits($client->cpf),
            'externalReference' => 'client:'.$client->id,
        ], fn ($v) => $v !== null && $v !== '');

        $response = Http::withHeaders([
            'access_token' => $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'StudioFlow/1',
        ])->acceptJson()->post($this->baseUrl().'/customers', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Asaas: não foi possível criar o cliente. '.$response->body());
        }

        $data = $response->json();
        $id = $data['id'] ?? null;
        if (! is_string($id) && ! is_numeric($id)) {
            throw new RuntimeException('Asaas: resposta de cliente inválida.');
        }

        $refs[PaymentProvider::Asaas->value] = (string) $id;
        $client->gateway_customer_refs = $refs;
        $client->save();

        return ['customer_id' => (string) $id, 'raw' => $data];
    }

    public function createMembershipCharge(CustomerMembership $membership, array $options = []): array
    {
        $token = $this->requireToken();
        $client = $membership->client()->firstOrFail();
        $plan = $membership->plan()->firstOrFail();

        $customer = $this->createCustomer($client);
        $customerId = $customer['customer_id'];

        $dueDate = $options['due_date'] ?? now()->addDays(3)->toDateString();
        $billingType = strtoupper((string) ($options['billing_type'] ?? 'UNDEFINED'));

        $value = number_format((float) $plan->price, 2, '.', '');

        $body = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => $value,
            'dueDate' => $dueDate,
            'description' => 'Assinatura: '.$plan->name,
            'externalReference' => 'membership:'.$membership->id,
        ];

        $response = Http::withHeaders([
            'access_token' => $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'StudioFlow/1',
        ])->acceptJson()->post($this->baseUrl().'/payments', $body);

        if (! $response->successful()) {
            throw new RuntimeException('Asaas: não foi possível criar a cobrança. '.$response->body());
        }

        $data = $response->json();
        $paymentId = $data['id'] ?? null;
        if (! is_string($paymentId) && ! is_numeric($paymentId)) {
            throw new RuntimeException('Asaas: cobrança sem identificador.');
        }

        return [
            'provider_payment_id' => (string) $paymentId,
            'invoice_url' => $data['invoiceUrl'] ?? null,
            'pix_qr_code' => $data['encodedImage'] ?? null,
            'pix_copy_paste' => $data['pixCopiaECola'] ?? null,
            'billing_type' => $this->mapBillingType($data['billingType'] ?? $billingType),
            'due_date' => $data['dueDate'] ?? $dueDate,
            'amount' => (float) ($data['value'] ?? $plan->price),
            'status' => strtolower((string) ($data['status'] ?? 'PENDING')),
            'raw' => $data,
        ];
    }

    public function cancelCharge(MembershipPayment $payment): void
    {
        if (! $payment->provider_payment_id) {
            return;
        }

        $token = $this->requireToken();

        $response = Http::withHeaders([
            'access_token' => $token,
            'User-Agent' => 'StudioFlow/1',
        ])->acceptJson()->delete($this->baseUrl().'/payments/'.$payment->provider_payment_id);

        if (! $response->successful()) {
            throw new RuntimeException('Asaas: não foi possível cancelar a cobrança. '.$response->body());
        }
    }

    public function getPaymentStatus(MembershipPayment $payment): array
    {
        if (! $payment->provider_payment_id) {
            return [];
        }

        $token = $this->requireToken();

        $response = Http::withHeaders([
            'access_token' => $token,
            'User-Agent' => 'StudioFlow/1',
        ])->acceptJson()->get($this->baseUrl().'/payments/'.$payment->provider_payment_id);

        if (! $response->successful()) {
            throw new RuntimeException('Asaas: não foi possível consultar a cobrança. '.$response->body());
        }

        return $response->json();
    }

    public function parseWebhook(array $payload): ParsedGatewayWebhook
    {
        return AsaasWebhookParser::parse($payload);
    }

    public function ping(): void
    {
        $token = $this->requireToken();

        $response = Http::withHeaders([
            'access_token' => $token,
            'User-Agent' => 'StudioFlow/1',
        ])->acceptJson()->get($this->baseUrl().'/customers', ['limit' => 1]);

        if (! $response->successful()) {
            throw new RuntimeException('Asaas: credenciais inválidas ou ambiente incorreto. '.$response->body());
        }
    }

    private function requireToken(): string
    {
        $token = $this->integration->access_token ?: $this->integration->api_key;
        $token = is_string($token) ? trim($token) : '';

        if ($token === '') {
            throw new RuntimeException('Configure a chave de API (Asaas) na integração da empresa.');
        }

        return $token;
    }

    private function baseUrl(): string
    {
        return $this->integration->environment === PaymentIntegrationEnvironment::Sandbox
            ? 'https://api-sandbox.asaas.com/v3'
            : 'https://api.asaas.com/v3';
    }

    private function digits(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $d = preg_replace('/\D+/', '', $value);

        return $d !== '' ? $d : null;
    }

    /**
     * @return MembershipPaymentBillingType::*
     */
    private function mapBillingType(string $billingType): MembershipPaymentBillingType
    {
        return match (Str::upper($billingType)) {
            'PIX' => MembershipPaymentBillingType::Pix,
            'CREDIT_CARD' => MembershipPaymentBillingType::CreditCard,
            'BOLETO' => MembershipPaymentBillingType::Boleto,
            default => MembershipPaymentBillingType::Unknown,
        };
    }
}
