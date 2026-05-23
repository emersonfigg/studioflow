<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Company;
use App\Models\CustomerMembership;
use App\Models\Expense;
use App\Models\MembershipPlan;
use App\Models\MembershipUsage;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductSaleItem;
use App\Models\Service;
use App\Models\ServiceOrderItem;
use App\Models\User;
use App\Services\CashRegisterService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioFlowEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-05-22 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_public_membership_link_uses_company_slug_and_requires_terms(): void
    {
        $company = Company::factory()->create(['name' => 'Studio Flow Prime', 'slug' => 'studio-flow-prime']);
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Clube Corte',
            'price' => 99,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
            'terms_text' => 'Termo do plano.',
        ]);

        $this->get('/empresa/studio-flow-prime/assinaturas')
            ->assertOk()
            ->assertSee('Clube Corte');

        $this->post('/empresa/studio-flow-prime/assinaturas', [
            'membership_plan_id' => $plan->id,
            'name' => 'Maria Cliente',
            'phone' => '(71) 99999-0000',
        ])->assertSessionHasErrors('accepted_terms');

        $this->withHeader('User-Agent', 'Feature Test')
            ->post('/empresa/studio-flow-prime/assinaturas', [
                'membership_plan_id' => $plan->id,
                'name' => 'Maria Cliente',
                'phone' => '(71) 99999-0000',
                'accepted_terms' => '1',
            ])->assertSessionHasNoErrors();

        $membership = CustomerMembership::query()->firstOrFail();
        $this->assertSame(CustomerMembership::STATUS_PENDING, $membership->status);
        $this->assertNotNull($membership->accepted_terms_at);
        $this->assertSame('Feature Test', $membership->accepted_terms_user_agent);
    }

    public function test_pdv_sells_membership_and_records_financial_inflow(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano Ouro',
            'price' => 150,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => true,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'pix',
            'membership_items' => [
                ['membership_plan_id' => $plan->id],
            ],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $membership = CustomerMembership::query()->firstOrFail();
        $this->assertSame(CustomerMembership::STATUS_ACTIVE, $membership->status);
        $this->assertDatabaseHas('cash_movements', [
            'company_id' => $company->id,
            'type' => CashMovement::TYPE_INFLOW,
            'source_type' => CustomerMembership::class,
            'source_id' => $membership->id,
            'amount' => 150,
        ]);
    }

    public function test_active_membership_discounts_included_service_and_records_usage(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create(['price' => 80, 'active' => true]);
        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Mensal',
            'price' => 120,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
        ]);
        $plan->services()->attach($service->id, [
            'company_id' => $company->id,
            'included' => true,
        ]);
        CustomerMembership::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => CustomerMembership::STATUS_ACTIVE,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'current_cycle_starts_at' => now()->toDateString(),
            'current_cycle_ends_at' => now()->addMonth()->toDateString(),
            'auto_renew' => false,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'cash',
            'service_items' => [
                ['service_id' => $service->id],
            ],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $this->assertSame(0, CashMovement::query()->where('source_type', \App\Models\Payment::class)->count());
        $this->assertSame(1, MembershipUsage::query()->count());
    }

    public function test_editable_service_price_is_audited_in_pdv(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create([
            'price' => 100,
            'price_mode' => Service::PRICE_MODE_FROM,
            'allow_pdv_price_edit' => true,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('pdv.store', absolute: false), [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'payment_method' => 'card',
            'service_items' => [
                ['service_id' => $service->id, 'unit_price' => 130, 'price_adjustment_reason' => 'Cabelo longo'],
            ],
        ])->assertRedirect(route('pdv.index', absolute: false));

        $item = ServiceOrderItem::query()->where('type', ServiceOrderItem::TYPE_SERVICE)->firstOrFail();
        $this->assertSame('100.00', (string) $item->original_unit_price);
        $this->assertSame('130.00', (string) $item->unit_price);
        $this->assertSame('30.00', (string) $item->price_adjustment_amount);
        $this->assertSame('Cabelo longo', $item->price_adjustment_reason);
        $this->assertSame($admin->id, $item->price_adjusted_by);
    }

    public function test_expense_payment_creates_single_cash_outflow(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)->post(route('expenses.store', absolute: false), [
            'description' => 'Internet',
            'category_name' => 'Internet',
            'amount' => 120,
            'due_date' => '2026-05-20',
            'recurrence' => Expense::RECURRENCE_MONTHLY,
        ])->assertRedirect();

        $expense = Expense::query()->firstOrFail();
        $this->assertSame(Expense::STATUS_PENDING, $expense->status);

        $this->actingAs($admin)->patch(route('expenses.mark-paid', $expense, false), [
            'payment_method' => 'pix',
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('expenses.mark-paid', $expense, false), [
            'payment_method' => 'pix',
        ])->assertRedirect();

        $this->assertSame(1, CashMovement::query()->where('source_type', Expense::class)->where('source_id', $expense->id)->count());
    }

    public function test_client_operational_reports_render_company_scoped_data(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        Client::factory()->for($company)->create([
            'name' => 'Aniversariante',
            'birthday' => '1990-05-22',
            'last_visit_at' => now()->subDays(45),
            'active' => true,
        ]);

        $this->actingAs($admin)->get(route('clients.birthdays', ['range' => 'day'], false))
            ->assertOk()
            ->assertSee('Aniversariante');

        $this->actingAs($admin)->get(route('clients.absent', ['days' => 30], false))
            ->assertOk()
            ->assertSee('Aniversariante');
    }

    public function test_dashboard_daily_revenue_uses_cash_inflows_only(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $cash = app(CashRegisterService::class);
        $cash->recordMovement($company->id, now(), CashMovement::TYPE_INFLOW, 50, 'Venda hoje');
        $cash->recordMovement($company->id, now()->subDay(), CashMovement::TYPE_INFLOW, 70, 'Venda ontem');
        $cash->recordMovement($company->id, now(), CashMovement::TYPE_OUTFLOW, 10, 'Saida');

        $this->actingAs($admin)->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertViewHas('revenueToday', 50.0);
    }

    public function test_product_sales_report_lists_completed_product_sales(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create(['active' => true]);
        $client = Client::factory()->for($company)->create();
        $product = Product::factory()->for($company)->create(['name' => 'Pomada', 'category' => 'Finalizadores', 'price' => 40]);
        $sale = ProductSale::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'gross_amount' => 80,
            'payment_method' => 'pix',
            'sold_at' => now(),
        ]);
        ProductSaleItem::query()->create([
            'product_sale_id' => $sale->id,
            'product_id' => $product->id,
            'seller_id' => $admin->id,
            'quantity' => 2,
            'unit_price' => 40,
            'total_price' => 80,
            'commission_amount' => 0,
        ]);

        $this->actingAs($admin)->get(route('finance.product-sales', absolute: false))
            ->assertOk()
            ->assertSee('Pomada')
            ->assertSee('Finalizadores');
    }
}
