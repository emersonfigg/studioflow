<?php

use App\Jobs\ProcessFinancialEvent;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\PaymentAccountCustomer;
use App\Models\PaymentWebhookEvent;
use App\Services\FinancialEventProcessor;
use App\Services\Payments\FinancialGatewayRegistry;
use App\Services\Payments\V2\GatewayResource;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeRecurringGateway;

// Worker used only by the isolated PostgreSQL concurrency test.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_PORT') !== '55438'
    || ! preg_match('/^studioflow_finance_test_[a-z0-9_]+$/D', getenv('DB_DATABASE') ?: '')) {
    throw new RuntimeException('Not an isolated test database.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Http::preventStrayRequests();
config(['financial.reconciliation_enabled' => true]);
$event = PaymentWebhookEvent::findOrFail((int) $argv[1]);
$account = CompanyPaymentIntegration::findOrFail($event->payment_account_id);
$membership = CustomerMembership::where('payment_account_id', $account->id)->where('billing_mode', 'recurring_v2')->firstOrFail();
$customer = PaymentAccountCustomer::where('payment_account_id', $account->id)->where('client_id', $membership->client_id)->firstOrFail();
$gateway = new FakeRecurringGateway(new GatewayResource($event->external_resource_id, 'confirmed', [
    'account_id' => $account->external_account_id, 'subscription_id' => $membership->external_subscription_id, 'customer_id' => $customer->external_customer_id,
    'amount' => '50.00', 'currency' => 'BRL', 'due_date' => '2026-10-01', 'coverage_starts_at' => '2026-10-01 00:00:00',
    'coverage_ends_at' => '2026-11-01 00:00:00', 'confirmed_at' => '2026-10-01 10:00:00', 'observed_at' => now()->toIso8601String(),
]));
$gateway->delayMicroseconds = 300000;
$app->make(FinancialGatewayRegistry::class)->registerForTesting($account->id, $gateway);
$gate = realpath($argv[2]);
if (! $gate || ! str_starts_with($gate, realpath(dirname(__DIR__, 2).'/storage').DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Invalid test barrier.');
}
file_put_contents($gate.'/ready-'.getmypid(), 'ready');
$deadline = microtime(true) + 10;
while (count(glob($gate.'/ready-*')) < 2) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Test barrier timed out.');
    }
    usleep(10000);
}
(new ProcessFinancialEvent($event->id))->handle($app->make(FinancialEventProcessor::class));
echo json_encode(['gateway_reads' => $gateway->calls], JSON_THROW_ON_ERROR);
