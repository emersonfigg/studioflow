<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\PaymentAccountCustomer;
use App\Models\SubscriptionStatusHistory;
use App\Services\WebhookIngestionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FinancialConcurrencyTest extends TestCase
{
    public function test_two_independent_postgresql_workers_process_event_once(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        Http::preventStrayRequests();
        Queue::fake();
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $plan = MembershipPlan::create(['company_id' => $company->id, 'name' => 'Concurrent', 'price' => '50.00', 'billing_cycle' => 'monthly']);
        $secret = bin2hex(random_bytes(24));
        $account = CompanyPaymentIntegration::create(['company_id' => $company->id, 'provider' => 'asaas', 'environment' => 'sandbox',
            'external_account_id' => 'concurrent_account', 'account_verified_at' => now(), 'webhook_secret' => $secret]);
        $membership = CustomerMembership::create(['company_id' => $company->id, 'client_id' => $client->id, 'membership_plan_id' => $plan->id,
            'payment_account_id' => $account->id, 'external_subscription_id' => 'sub_concurrent', 'billing_mode' => 'recurring_v2', 'status' => 'pending',
            'starts_at' => '2026-10-01', 'current_cycle_starts_at' => '2026-10-01', 'current_cycle_ends_at' => '2026-10-30', 'commercial_snapshot' => ['amount' => '50.00']]);
        PaymentAccountCustomer::create(['company_id' => $company->id, 'payment_account_id' => $account->id, 'client_id' => $client->id, 'external_customer_id' => 'cus_concurrent']);
        $event = app(WebhookIngestionService::class)->ingestAsaas(['id' => 'evt_concurrent', 'event' => 'PAYMENT_CONFIRMED', 'payment' => ['id' => 'pay_concurrent']], $secret, $account->id, 'sandbox');
        $gate = storage_path('framework/financial-concurrency-'.bin2hex(random_bytes(6)));
        mkdir($gate, 0777, true);
        $command = [PHP_BINARY, '-d', 'extension=pdo_pgsql', base_path('tests/Support/financial-event-worker.php'), (string) $event->id, $gate];
        $one = new Process($command, base_path(), timeout: 30);
        $two = new Process($command, base_path(), timeout: 30);
        $one->start();
        $two->start();
        $one->wait();
        $two->wait();
        $this->assertTrue($one->isSuccessful(), $one->getErrorOutput());
        $this->assertTrue($two->isSuccessful(), $two->getErrorOutput());
        $this->assertSame(1, json_decode($one->getOutput(), true)['gateway_reads'] + json_decode($two->getOutput(), true)['gateway_reads']);
        $this->assertSame('processed', $event->fresh()->processing_status);
        $this->assertSame(1, $event->fresh()->attempts);
        $this->assertSame(1, MembershipPayment::where('customer_membership_id', $membership->id)->count());
        $this->assertSame(1, SubscriptionStatusHistory::where('customer_membership_id', $membership->id)->count());
        $this->assertSame('pending', $membership->fresh()->status);
        Http::assertNothingSent();
    }
}
