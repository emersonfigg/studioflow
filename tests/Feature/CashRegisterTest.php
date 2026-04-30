<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_and_close_daily_cash_register(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this->actingAs($admin)
            ->post(route('finance.cash.open', absolute: false), [
                'date' => '2026-04-30',
                'opening_amount' => '100.00',
                'notes' => 'Abertura normal.',
            ])
            ->assertRedirect(route('finance.cash', ['date' => '2026-04-30'], false));

        $register = $company->cashRegisters()->firstOrFail();
        $this->assertSame('2026-04-30', $register->date->format('Y-m-d'));
        $this->assertSame('100.00', number_format((float) $register->opening_amount, 2, '.', ''));

        $registerId = $register->id;

        $this->actingAs($admin)
            ->post(route('finance.cash.close', absolute: false), [
                'cash_register_id' => $registerId,
                'closing_amount' => '120.00',
            ])
            ->assertRedirect(route('finance.cash', ['date' => '2026-04-30'], false));

        $this->assertDatabaseHas('cash_registers', [
            'id' => $registerId,
            'closing_amount' => '120.00',
        ]);
    }

    public function test_commission_settlement_is_debited_from_daily_cash(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $professional = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create();
        $appointment = Appointment::factory()->for($company)->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'user_id' => $professional->id,
            'status' => 'completed',
        ]);

        Payment::factory()->create([
            'company_id' => $company->id,
            'appointment_id' => $appointment->id,
            'user_id' => $professional->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'gross_amount' => '100.00',
            'commission_amount' => '40.00',
            'net_amount' => '60.00',
            'paid_at' => '2026-04-30 10:00:00',
        ]);

        $this->actingAs($admin)
            ->post(route('finance.commissions.settlements.store', absolute: false), [
                'user_id' => $professional->id,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_movements', [
            'company_id' => $company->id,
            'type' => 'outflow',
            'source_type' => 'App\\Models\\CommissionSettlement',
            'amount' => '40.00',
        ]);
    }
}
