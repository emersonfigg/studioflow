<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-04-15 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_internal_appointment_creates_open_service_order_with_service_item(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'name' => 'Corte Premium',
            'price' => 80.00,
            'duration_minutes' => 45,
        ]);
        $this->createWorkingHour($company, $admin, 4, '08:00', '18:00');

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-16 10:00:00',
                'status' => 'scheduled',
                'source' => 'internal',
            ])
            ->assertSessionHasNoErrors();

        $appointment = Appointment::query()->firstOrFail();
        $order = ServiceOrder::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->assertSame(ServiceOrder::STATUS_OPEN, $order->status);
        $this->assertSame('80.00', (string) $order->subtotal_services);
        $this->assertSame('80.00', (string) $order->total);
        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $order->id,
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $admin->id,
            'description' => 'Corte Premium',
            'quantity' => 1,
            'total_price' => '80.00',
        ]);
    }

    public function test_public_booking_multiple_services_become_multiple_order_items(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $professional = User::factory()->for($company)->create(['active' => true]);
        $firstService = Service::factory()->for($company)->create([
            'price' => 70.00,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $secondService = Service::factory()->for($company)->create([
            'price' => 40.00,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $this->createWorkingHour($company, $professional, 2, '08:00', '18:00');

        $this->post(route('public-bookings.store', $company, false), [
            'service_ids' => [$firstService->id, $secondService->id],
            'user_id' => $professional->id,
            'date' => '2026-04-21',
            'time' => '09:00',
            'client_name' => $client->name,
            'client_phone' => $client->phone,
            'client_email' => $client->email,
        ])->assertSessionHasNoErrors();

        $order = ServiceOrder::query()->firstOrFail();

        $this->assertSame('110.00', (string) $order->subtotal_services);
        $this->assertSame(2, $order->items()->where('type', ServiceOrderItem::TYPE_SERVICE)->count());
    }

    public function test_open_order_accepts_extra_service_and_product_and_recalculates_total(): void
    {
        [$company, $admin, $appointment, $order] = $this->appointmentWithOrder();
        $extraService = Service::factory()->for($company)->create(['price' => 50.00, 'active' => true]);
        $product = Product::factory()->for($company)->create(['price' => 35.00, 'stock_quantity' => 3, 'active' => true]);

        $this->actingAs($admin)
            ->post(route('service-orders.services.store', $order, false), [
                'service_id' => $extraService->id,
                'professional_id' => $appointment->user_id,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('service-orders.products.store', $order, false), [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('130.00', (string) $order->subtotal_services);
        $this->assertSame('70.00', (string) $order->subtotal_products);
        $this->assertSame('200.00', (string) $order->total);
    }

    public function test_product_without_stock_cannot_be_added_to_order(): void
    {
        [$company, $admin, , $order] = $this->appointmentWithOrder();
        $product = Product::factory()->for($company)->create(['stock_quantity' => 1, 'active' => true]);

        $this->actingAs($admin)
            ->from(route('appointments.orders.show', $order->appointment_id, false))
            ->post(route('service-orders.products.store', $order, false), [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect(route('appointments.orders.show', $order->appointment_id, false))
            ->assertSessionHasErrors('product_id');

        $this->assertSame('1.00', (string) $product->refresh()->stock_quantity);
    }

    public function test_closing_order_uses_order_total_splits_products_and_blocks_further_changes(): void
    {
        [$company, $admin, $appointment, $order] = $this->appointmentWithOrder();
        $product = Product::factory()->for($company)->create(['price' => 25.00, 'stock_quantity' => 5, 'active' => true]);

        $this->actingAs($admin)
            ->post(route('service-orders.products.store', $order, false), [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('service-orders.close', $order, false), [
                'payment_method' => 'pix',
            ])
            ->assertRedirect(route('appointments.show', $appointment, false));

        $order->refresh();
        $sale = ProductSale::query()->where('service_order_id', $order->id)->firstOrFail();

        $this->assertSame(ServiceOrder::STATUS_PAID, $order->status);
        $this->assertSame('130.00', (string) $order->total);
        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'service_order_id' => $order->id,
            'gross_amount' => '80.00',
            'payment_method' => 'pix',
        ]);
        $this->assertDatabaseHas('product_sales', [
            'id' => $sale->id,
            'service_order_id' => $order->id,
            'gross_amount' => '50.00',
        ]);
        $this->assertSame('3.00', (string) $product->refresh()->stock_quantity);

        $extraService = Service::factory()->for($company)->create(['active' => true]);

        $this->actingAs($admin)
            ->from(route('appointments.orders.show', $appointment, false))
            ->post(route('service-orders.services.store', $order, false), [
                'service_id' => $extraService->id,
            ])
            ->assertRedirect(route('appointments.orders.show', $appointment, false))
            ->assertSessionHasErrors('order');
    }

    public function test_closing_empty_order_is_blocked(): void
    {
        [$company, $admin, $appointment, $order] = $this->appointmentWithOrder();
        $order->items()->delete();
        $order->update([
            'subtotal_services' => 0,
            'subtotal_products' => 0,
            'total' => 0,
        ]);

        $this->actingAs($admin)
            ->from(route('appointments.orders.show', $appointment, false))
            ->post(route('service-orders.close', $order, false), [
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('appointments.orders.show', $appointment, false))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * @return array{Company, User, Appointment, ServiceOrder}
     */
    private function appointmentWithOrder(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'price' => 80.00,
            'duration_minutes' => 45,
        ]);
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $admin->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($admin)
            ->get(route('appointments.orders.show', $appointment, false))
            ->assertOk();

        return [$company, $admin, $appointment, ServiceOrder::query()->where('appointment_id', $appointment->id)->firstOrFail()];
    }

    private function createWorkingHour(Company $company, User $user, int $weekday, string $startTime, string $endTime): void
    {
        ProfessionalWorkingHour::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'weekday' => $weekday,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'active' => true,
        ]);
    }
}
