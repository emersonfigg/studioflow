<?php

namespace App\Console\Commands;

use App\Enums\PaymentProvider;
use App\Jobs\IdentifyOverdueInvoices;
use App\Jobs\ProcessFinancialEvent;
use App\Jobs\ReconcileMembershipCheckout;
use App\Jobs\ReconcileSubscription;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipCheckoutIntention;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\V2\FinancialAccountResolver;
use Illuminate\Console\Command;

class DispatchFinancialWork extends Command
{
    protected $signature = 'financial:dispatch';

    protected $description = 'Recupera eventos persistidos e agenda conciliação apenas em Sandbox habilitado.';

    public function handle(FinancialGatewayRegistry $gateways, FinancialAccountResolver $resolver): int
    {
        PaymentWebhookEvent::query()->where('attempts', '<', config('financial.max_attempts'))
            ->where(function ($query) {
                $query->whereIn('processing_status', ['pending', 'failed'])->orWhere(function ($query) {
                    $query->where('processing_status', 'processing')->where('lease_until', '<', now());
                });
            })->chunkById(100, function ($events) {
                foreach ($events as $event) {
                    ProcessFinancialEvent::dispatch($event->id);
                }
            });
        if (! $gateways->enabled()) {
            $this->info('Eventos persistidos recuperados; conciliação recorrente desabilitada.');

            return self::SUCCESS;
        }
        CompanyPaymentIntegration::query()->where('environment', 'sandbox')->whereNotNull('account_verified_at')->where('active', true)->chunkById(100, function ($accounts) use ($resolver) {
            foreach ($accounts as $account) {
                $context = $resolver->resolve($account->company_id, $account->id, $account->provider, $account->environment);
                if ($account->provider === PaymentProvider::Asaas && config('financial.asaas_v2_enabled') === true) {
                    MembershipCheckoutIntention::where('company_id', $account->company_id)->where('payment_account_id', $account->id)
                        ->whereNotNull('public_identity_id')->whereNotNull('external_checkout_id')->whereNotNull('external_customer_id')
                        ->whereIn('customer_membership_id', CustomerMembership::where('company_id', $account->company_id)->where('origin', 'public_checkout')->whereNull('external_subscription_id')->select('id'))
                        ->chunkById(100, function ($intentions) use ($context) {
                            foreach ($intentions as $intention) {
                                ReconcileMembershipCheckout::dispatch($context, $intention->id);
                            }
                        });
                }
                CustomerMembership::query()->where('payment_account_id', $account->id)->where('billing_mode', 'recurring_v2')->whereNotNull('external_subscription_id')
                    ->chunkById(100, function ($memberships) use ($context) {
                        foreach ($memberships as $membership) {
                            ReconcileSubscription::dispatch($context, $membership->id);
                            IdentifyOverdueInvoices::dispatch($context, $membership->id);
                        }
                    });
            }
        });

        return self::SUCCESS;
    }
}
