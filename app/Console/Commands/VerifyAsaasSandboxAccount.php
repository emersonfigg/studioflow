<?php

namespace App\Console\Commands;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Models\CompanyPaymentIntegration;
use App\Services\Payments\V2\PublicMembershipAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Operator-assisted verification of the merchant's own Sandbox account; never creates a subaccount. */
class VerifyAsaasSandboxAccount extends Command
{
    protected $signature = 'financial:verify-asaas-sandbox {integration} {account-id} {wallet-id} {--confirm-account-ownership}';

    protected $description = 'Verifica credencial e habilitação Sandbox com consultas, vinculando a identidade conferida pelo responsável.';

    public function handle(): int
    {
        if (app()->environment('production') || ! $this->option('confirm-account-ownership')) {
            $this->error('Permitido apenas em homologação, após conferir a conta do estabelecimento no painel Asaas.');

            return self::FAILURE;
        }
        $account = CompanyPaymentIntegration::where('provider', PaymentProvider::Asaas)->where('environment', PaymentIntegrationEnvironment::Sandbox)->findOrFail($this->argument('integration'));
        if (! $account->api_key || ! $account->webhook_secret || strlen($account->webhook_secret) < 32 || ! $account->active
            || ! preg_match('/^[a-zA-Z0-9_-]{1,200}$/D', $this->argument('account-id'))
            || ($account->account_verified_at && $account->external_account_id !== $this->argument('account-id'))) {
            $this->error('Configuração inválida ou identidade diferente. Uma identidade verificada não pode ser substituída.');

            return self::FAILURE;
        }
        try {
            $http = Http::baseUrl('https://api-sandbox.asaas.com/v3')->withHeaders(['access_token' => $account->api_key, 'User-Agent' => 'StudioFlow/verification'])
                ->acceptJson()->connectTimeout(5)->timeout(20)->withoutRedirecting();
            $wallets = $http->get('/wallets');
            $status = $http->get('/myAccount/status');
            if (! $wallets->successful() || ! $status->successful() || $status->json('general') !== 'APPROVED' || $status->json('id') !== $this->argument('account-id')
                || ! collect($wallets->json('data', []))->contains(fn ($wallet) => ($wallet['id'] ?? null) === $this->argument('wallet-id'))) {
                $this->error('Credenciais, Wallet ID ou habilitação não confirmados no Sandbox.');

                return self::FAILURE;
            }
            DB::transaction(function () use ($account) {
                $locked = CompanyPaymentIntegration::whereKey($account->id)->lockForUpdate()->firstOrFail();
                if ($locked->api_key !== $account->api_key || $locked->company_id !== $account->company_id
                    || $locked->provider !== $account->provider || $locked->environment !== $account->environment || ! $locked->active
                    || ($locked->account_verified_at && $locked->external_account_id !== $this->argument('account-id'))) {
                    throw new \RuntimeException('configuration_changed');
                }
                if (! $locked->account_verified_at) {
                    $locked->external_account_id = $this->argument('account-id');
                    $locked->account_verified_at = now();
                }
                $locked->status = 'connected';
                $locked->connected_at = now();
                $locked->last_synced_at = now();
                $locked->account_enablement_status = 'approved';
                $locked->connection_type = 'merchant_api_key';
                $locked->metadata = array_merge($locked->metadata ?? [], ['recurring_v2_wallet_id' => $this->argument('wallet-id'),
                    'recurring_v2_identity_evidence' => 'api_verified_account_id_wallet_and_approval',
                    'recurring_v2_credential_fingerprint' => app(PublicMembershipAccount::class)->credentialFingerprint($locked)]);
                $locked->save();
            }, 3);
        } catch (\Throwable) {
            $this->error('Não foi possível verificar a conexão. Nenhuma cobrança foi criada.');

            return self::FAILURE;
        }
        $this->info('Conta Sandbox verificada. A contratação continua dependente das flags e dos testes de homologação.');

        return self::SUCCESS;
    }
}
