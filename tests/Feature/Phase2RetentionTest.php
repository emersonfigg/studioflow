<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentReview;
use App\Models\Client;
use App\Models\Company;
use App\Models\CustomerBlock;
use App\Models\CustomerMembership;
use App\Models\MembershipPlan;
use App\Models\MembershipUsage;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\CustomerBlockService;
use App\Services\MembershipService;
use App\Services\ReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2RetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-05-14 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_mark_no_show_records_no_show_and_blocks_client(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create(['active' => true]);
        $appointment = Appointment::factory()
            ->for($company)
            ->for($client)
            ->for($admin, 'user')
            ->for($service)
            ->create([
                'start_time' => '2026-05-15 10:00:00',
                'end_time' => '2026-05-15 11:00:00',
                'status' => 'scheduled',
            ]);

        $this->actingAs($admin)
            ->from(route('appointments.show', $appointment, false))
            ->post(route('appointments.no-show', $appointment, false), ['reason' => 'Teste'])
            ->assertRedirect();

        $appointment->refresh();
        $this->assertSame('no_show', $appointment->status);
        $this->assertDatabaseHas('customer_no_shows', [
            'company_id' => $company->id,
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
        ]);
        $this->assertDatabaseHas('customer_blocks', [
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => CustomerBlock::TYPE_NO_SHOW,
            'active' => true,
        ]);
    }

    public function test_included_membership_records_single_usage_on_close(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'price' => 100.00,
            'duration_minutes' => 60,
            'active' => true,
        ]);
        // 2026-05-15 is Friday; AvailabilityService matches Carbon dayOfWeek (0=Sun … 5=Fri).
        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'weekday' => CarbonImmutable::parse('2026-05-15')->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano Ouro',
            'price' => 199,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);
        $plan->services()->sync([
            $service->id => [
                'company_id' => $company->id,
                'included' => true,
                'quantity_per_cycle' => 5,
                'discount_percent' => null,
            ],
        ]);

        CustomerMembership::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => CustomerMembership::STATUS_ACTIVE,
            'starts_at' => '2026-05-01',
            'ends_at' => null,
            'current_cycle_starts_at' => '2026-05-01',
            'current_cycle_ends_at' => '2026-05-30',
            'auto_renew' => false,
        ]);

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-05-15 10:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertSessionHasNoErrors();

        $appointment = Appointment::query()->firstOrFail();
        $order = ServiceOrder::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('service-orders.close', $order, false), [
                'payment_method' => 'pix',
            ])
            ->assertRedirect(route('appointments.show', $appointment, false));

        $this->assertDatabaseHas('membership_usages', [
            'company_id' => $company->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'reference_type' => MembershipUsage::REF_APPOINTMENT,
            'reference_id' => $appointment->id,
        ]);

        $this->assertSame(1, MembershipUsage::query()->where('appointment_id', $appointment->id)->count());
    }

    public function test_record_usages_from_closure_is_idempotent_for_same_appointment(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'price' => 80.00,
            'duration_minutes' => 60,
            'active' => true,
        ]);
        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'weekday' => CarbonImmutable::parse('2026-05-15')->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano',
            'price' => 99,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);
        $plan->services()->sync([
            $service->id => [
                'company_id' => $company->id,
                'included' => true,
                'quantity_per_cycle' => 5,
                'discount_percent' => null,
            ],
        ]);

        $membership = CustomerMembership::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => CustomerMembership::STATUS_ACTIVE,
            'starts_at' => '2026-05-01',
            'ends_at' => null,
            'current_cycle_starts_at' => '2026-05-01',
            'current_cycle_ends_at' => '2026-05-30',
            'auto_renew' => false,
        ]);

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-05-15 10:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertSessionHasNoErrors();

        $appointment = Appointment::query()->firstOrFail();
        $order = ServiceOrder::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $membershipService = app(MembershipService::class);
        $benefit = $membershipService->computeClosureBenefit($order->fresh('items'), $appointment, now());
        $this->assertNotEmpty($benefit['lines']);

        $membershipService->recordUsagesFromClosure($order, $appointment, $benefit, now());
        $membershipService->recordUsagesFromClosure($order, $appointment, $benefit, now());

        $this->assertSame(1, MembershipUsage::query()
            ->where('company_id', $company->id)
            ->where('customer_membership_id', $membership->id)
            ->where('reference_type', MembershipUsage::REF_APPOINTMENT)
            ->where('reference_id', $appointment->id)
            ->where('service_id', $service->id)
            ->count());
    }

    public function test_expired_membership_does_not_record_usage_on_close(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'price' => 50.00,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'weekday' => CarbonImmutable::parse('2026-05-15')->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Plano Exp',
            'price' => 50,
            'billing_cycle' => MembershipPlan::BILLING_MONTHLY,
            'active' => true,
            'auto_renew' => false,
        ]);
        $plan->services()->sync([
            $service->id => [
                'company_id' => $company->id,
                'included' => true,
                'quantity_per_cycle' => 10,
                'discount_percent' => null,
            ],
        ]);

        CustomerMembership::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => CustomerMembership::STATUS_ACTIVE,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'current_cycle_starts_at' => '2026-04-01',
            'current_cycle_ends_at' => '2026-04-30',
            'auto_renew' => false,
        ]);

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-05-15 10:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertSessionHasNoErrors();

        $appointment = Appointment::query()->firstOrFail();
        $order = ServiceOrder::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('service-orders.close', $order, false), ['payment_method' => 'pix'])
            ->assertRedirect(route('appointments.show', $appointment, false));

        $this->assertSame(0, MembershipUsage::query()->where('appointment_id', $appointment->id)->count());
    }

    public function test_review_service_creates_single_pending_review_per_appointment(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $admin = User::factory()->admin()->for($company)->create();
        $service = Service::factory()->for($company)->create(['active' => true]);
        $appointment = Appointment::factory()
            ->for($company)
            ->for($client)
            ->for($admin, 'user')
            ->for($service)
            ->create(['status' => 'completed']);

        $reviewService = app(ReviewService::class);
        $reviewService->createPendingReview($appointment);
        $reviewService->createPendingReview($appointment);

        $this->assertSame(1, AppointmentReview::query()->where('appointment_id', $appointment->id)->count());
    }

    public function test_public_review_accepts_valid_rating(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $admin = User::factory()->admin()->for($company)->create();
        $service = Service::factory()->for($company)->create(['active' => true]);
        $appointment = Appointment::factory()
            ->for($company)
            ->for($client)
            ->for($admin, 'user')
            ->for($service)
            ->create(['status' => 'completed']);

        $review = app(ReviewService::class)->createPendingReview($appointment);

        $this->post(route('public-reviews.store', ['token' => $review->token], false), [
            'rating' => 5,
            'comment' => 'Ótimo atendimento',
        ])->assertOk();

        $review->refresh();
        $this->assertNotNull($review->submitted_at);
        $this->assertSame(5, (int) $review->rating);
    }

    public function test_public_review_rejects_rating_out_of_range(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $admin = User::factory()->admin()->for($company)->create();
        $service = Service::factory()->for($company)->create(['active' => true]);
        $appointment = Appointment::factory()
            ->for($company)
            ->for($client)
            ->for($admin, 'user')
            ->for($service)
            ->create(['status' => 'completed']);

        $review = app(ReviewService::class)->createPendingReview($appointment);

        $this->from(route('public-reviews.show', ['token' => $review->token], false))
            ->post(route('public-reviews.store', ['token' => $review->token], false), [
                'rating' => 6,
                'comment' => null,
            ])
            ->assertSessionHasErrors('rating');
    }

    public function test_invalid_review_token_returns_not_found(): void
    {
        $this->post(route('public-reviews.store', ['token' => 'token-inexistente'], false), [
            'rating' => 5,
        ])->assertNotFound();
    }

    public function test_submitted_review_visible_on_internal_reviews_index(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $admin = User::factory()->admin()->for($company)->create();
        $service = Service::factory()->for($company)->create(['active' => true]);
        $appointment = Appointment::factory()
            ->for($company)
            ->for($client)
            ->for($admin, 'user')
            ->for($service)
            ->create(['status' => 'completed']);

        $review = app(ReviewService::class)->createPendingReview($appointment);
        app(ReviewService::class)->submitReview((string) $review->token, [
            'rating' => 4,
            'comment' => 'Comentario interno visivel',
        ]);

        $this->actingAs($admin)
            ->get(route('reviews.index', [], false))
            ->assertOk()
            ->assertSee('Comentario interno visivel', false);
    }

    public function test_blocked_client_cannot_use_public_booking(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create(['phone' => '11999990000']);
        $professional = User::factory()->for($company)->create(['active' => true]);
        $service = Service::factory()->for($company)->create([
            'active' => true,
            'duration_minutes' => 30,
            'price' => 50,
        ]);
        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $professional->id,
            'weekday' => CarbonImmutable::parse('2026-05-20')->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'active' => true,
        ]);

        app(CustomerBlockService::class)->blockCustomer(
            $company->id,
            $client->id,
            CustomerBlock::TYPE_MANUAL,
            'Teste',
            now()->subHour(),
            now()->addDay(),
            null,
        );

        $this->post(route('public-bookings.store', $company, false), [
            'user_id' => $professional->id,
            'service_ids' => [$service->id],
            'date' => '2026-05-20',
            'time' => '10:00',
            'client_name' => $client->name,
            'client_phone' => '11999990000',
            'client_email' => 'x@example.com',
        ])->assertSessionHasErrors('client_phone');
    }

    public function test_admin_can_unblock_client(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();

        app(CustomerBlockService::class)->blockCustomer(
            $company->id,
            $client->id,
            CustomerBlock::TYPE_MANUAL,
            'Bloqueio teste',
            now()->subHour(),
            now()->addDay(),
            null,
        );

        $this->actingAs($admin)
            ->from(route('clients.show', $client, false))
            ->post(route('clients.unblock', $client, false))
            ->assertRedirect(route('clients.show', $client, false));

        $this->assertDatabaseMissing('customer_blocks', [
            'company_id' => $company->id,
            'client_id' => $client->id,
            'active' => true,
        ]);
    }

    public function test_block_customer_does_not_create_duplicate_active_block(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $service = CustomerBlockService::class;

        $first = app($service)->blockCustomer(
            $company->id,
            $client->id,
            CustomerBlock::TYPE_MANUAL,
            'A',
            now()->subHour(),
            now()->addDay(),
            null,
        );
        $second = app($service)->blockCustomer(
            $company->id,
            $client->id,
            CustomerBlock::TYPE_MANUAL,
            'B',
            now()->subHour(),
            now()->addDay(),
            null,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CustomerBlock::query()
            ->where('company_id', $company->id)
            ->where('client_id', $client->id)
            ->where('active', true)
            ->count());
    }
}
