<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Models\CustomerMembership;
use App\Models\MembershipCheckoutIntention;
use App\Models\MembershipInvoice;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgresFoundationUpgradeTest extends TestCase
{
    public function test_postgresql_18_additive_upgrade_preserves_contracts_and_money(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        $this->assertSame('55438', (string) config('database.connections.pgsql.port'));
        $this->assertStringStartsWith('studioflow_finance_test_', config('database.connections.pgsql.database'));
        $this->assertStringContainsString('PostgreSQL 18.', DB::selectOne('SELECT version() AS version')->version);
        Http::preventStrayRequests();
        $schema = 'upgrade_'.bin2hex(random_bytes(6));
        DB::statement('CREATE SCHEMA '.$schema);
        config(['database.connections.foundation_pg_upgrade' => array_replace(config('database.connections.pgsql'), ['search_path' => $schema])]);
        DB::setDefaultConnection('foundation_pg_upgrade');
        try {
            $additive = [];
            foreach (glob(database_path('migrations/*.php')) as $file) {
                if (basename($file) >= '2026_10_20_') {
                    $additive[] = $file;
                } else {
                    (require $file)->up();
                }
            }
            $company = Company::factory()->create();
            $client = Client::factory()->for($company)->create();
            $plan = MembershipPlan::create(['company_id' => $company->id, 'name' => 'Preserve', 'price' => '50.00', 'billing_cycle' => 'monthly']);
            foreach (CustomerMembership::STATUSES as $status) {
                $membership = CustomerMembership::create(['company_id' => $company->id, 'client_id' => $client->id, 'membership_plan_id' => $plan->id, 'status' => $status,
                    'starts_at' => '2026-01-01', 'current_cycle_starts_at' => '2026-10-01', 'current_cycle_ends_at' => '2026-10-30', 'auto_renew' => true]);
                MembershipPayment::create(['company_id' => $company->id, 'client_id' => $client->id, 'customer_membership_id' => $membership->id,
                    'provider' => 'asaas', 'provider_payment_id' => 'pay_'.$membership->id, 'amount' => '50.00', 'status' => 'paid', 'paid_at' => '2026-10-01']);
            }
            $before = [];
            foreach (['customer_memberships', 'membership_payments'] as $table) {
                $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            }
            foreach ($additive as $file) {
                (require $file)->up();
            }
            $this->assertCount(4, $additive);
            foreach ($before as $table => $rows) {
                $after = DB::table($table)->orderBy('id')->get();
                $this->assertCount(6, $after);
                foreach ($rows as $index => $row) {
                    $this->assertSame($row, array_intersect_key((array) $after[$index], $row));
                    $this->assertNull($after[$index]->payment_account_id);
                }
            }
            Http::assertNothingSent();
        } finally {
            DB::purge('foundation_pg_upgrade');
            DB::setDefaultConnection('pgsql');
        }
    }

    public function test_stage_three_alone_preserves_stage_two_financial_records(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertStringStartsWith('studioflow_finance_test_', config('database.connections.pgsql.database'));
        Http::preventStrayRequests();
        $schema = 'checkout_upgrade_'.bin2hex(random_bytes(6));
        DB::statement('CREATE SCHEMA '.$schema);
        config(['database.connections.checkout_pg_upgrade' => array_replace(config('database.connections.pgsql'), ['search_path' => $schema])]);
        DB::setDefaultConnection('checkout_pg_upgrade');
        try {
            foreach (glob(database_path('migrations/*.php')) as $file) {
                if (! str_starts_with(basename($file), '2026_10_23_')) {
                    (require $file)->up();
                }
            }
            $company = Company::factory()->create();
            $client = Client::factory()->for($company)->create();
            $plan = MembershipPlan::create(['company_id' => $company->id, 'name' => 'Stage 2 sentinel', 'price' => '50.00', 'billing_cycle' => 'monthly']);
            $account = CompanyPaymentIntegration::create(['company_id' => $company->id, 'provider' => 'asaas', 'environment' => 'sandbox',
                'external_account_id' => 'upgrade_account', 'account_verified_at' => now(), 'api_key' => 'dummy']);
            $membership = CustomerMembership::create(['company_id' => $company->id, 'client_id' => $client->id, 'membership_plan_id' => $plan->id,
                'payment_account_id' => $account->id, 'billing_mode' => 'recurring_v2', 'external_subscription_id' => 'sub_sentinel',
                'status' => 'active', 'financial_status' => 'active', 'starts_at' => '2026-10-01', 'current_cycle_starts_at' => '2026-10-01', 'current_cycle_ends_at' => '2026-10-31']);
            $intention = MembershipCheckoutIntention::create(['reference' => (string) Str::uuid(), 'company_id' => $company->id,
                'payment_account_id' => $account->id, 'client_id' => $client->id, 'customer_membership_id' => $membership->id,
                'commercial_snapshot' => ['amount' => '50.00'], 'terms_version' => 'stage2', 'accepted_terms_at' => now(), 'external_checkout_id' => 'checkout_sentinel']);
            $invoice = MembershipInvoice::create(['company_id' => $company->id, 'payment_account_id' => $account->id, 'customer_membership_id' => $membership->id,
                'external_invoice_id' => 'invoice_sentinel', 'currency' => 'BRL', 'amount' => '50.00', 'status' => 'confirmed', 'due_date' => '2026-10-01', 'confirmed_at' => now()]);
            MembershipPayment::create(['company_id' => $company->id, 'payment_account_id' => $account->id, 'client_id' => $client->id,
                'customer_membership_id' => $membership->id, 'membership_invoice_id' => $invoice->id, 'external_transaction_id' => 'invoice_sentinel',
                'provider' => 'asaas', 'amount' => '50.00', 'status' => 'paid']);
            PaymentWebhookEvent::create(['company_id' => $company->id, 'payment_account_id' => $account->id, 'external_event_id' => 'evt_sentinel',
                'event_type' => 'PAYMENT_CONFIRMED', 'received_at' => now(), 'payload_hash' => hash('sha256', '{}'), 'processing_status' => 'processed']);
            $tables = ['company_payment_integrations', 'customer_memberships', 'membership_checkout_intentions', 'membership_invoices', 'membership_payments', 'payment_webhook_events'];
            $before = [];
            foreach ($tables as $table) {
                $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            }
            (require database_path('migrations/2026_10_23_000001_add_public_recurring_enrollment.php'))->up();
            foreach ($before as $table => $rows) {
                $after = DB::table($table)->orderBy('id')->get();
                $this->assertCount(count($rows), $after);
                foreach ($rows as $index => $row) {
                    $this->assertSame($row, array_intersect_key((array) $after[$index], $row));
                }
            }
            $this->assertNull(DB::table('membership_checkout_intentions')->where('id', $intention->id)->value('public_identity_id'));
            $this->assertSame('active', CustomerMembership::findOrFail($membership->id)->status);
            Http::assertNothingSent();
        } finally {
            DB::purge('checkout_pg_upgrade');
            DB::setDefaultConnection('pgsql');
        }
    }
}
