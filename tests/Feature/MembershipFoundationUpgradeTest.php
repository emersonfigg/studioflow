<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CustomerMembership;
use App\Models\MembershipPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MembershipFoundationUpgradeTest extends TestCase
{
    public function test_additive_upgrade_preserves_all_legacy_contract_values(): void
    {
        $this->assertSame('testing', app()->environment());
        Http::preventStrayRequests();
        $original = config('database.default');
        config(['database.connections.foundation_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('foundation_upgrade');
        try {
            $foundation = [];
            foreach (glob(database_path('migrations/*.php')) as $file) {
                if (basename($file) >= '2026_10_20_') {
                    $foundation[] = $file;
                } else {
                    (require $file)->up();
                }
            }
            $company = Company::factory()->create();
            $client = Client::factory()->for($company)->create();
            $plan = MembershipPlan::create(['company_id' => $company->id, 'name' => 'Existing', 'price' => 50, 'billing_cycle' => 'monthly']);
            foreach (CustomerMembership::STATUSES as $status) {
                CustomerMembership::create(['company_id' => $company->id, 'client_id' => $client->id, 'membership_plan_id' => $plan->id, 'status' => $status, 'starts_at' => '2026-01-01', 'current_cycle_starts_at' => '2026-10-01', 'current_cycle_ends_at' => '2026-10-30', 'auto_renew' => true]);
            }
            $before = DB::table('customer_memberships')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            foreach ($foundation as $file) {
                (require $file)->up();
            }
            $after = DB::table('customer_memberships')->orderBy('id')->get();
            foreach ($before as $index => $row) {
                $this->assertSame($row, array_intersect_key((array) $after[$index], $row));
                foreach (['payment_account_id', 'legacy_classification', 'financial_status', 'paid_through', 'suspended_at'] as $field) {
                    $this->assertNull($after[$index]->$field);
                }
            }
            $this->assertCount(count(CustomerMembership::STATUSES), $after);
            Http::assertNothingSent();
        } finally {
            DB::disconnect('foundation_upgrade');
            DB::setDefaultConnection($original);
        }
    }
}
