<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CashMovement;
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
                'closing_amount' => '100.00',
            ])
            ->assertRedirect(route('finance.cash', ['date' => '2026-04-30'], false));

        $this->assertDatabaseHas('cash_registers', [
            'id' => $registerId,
            'closing_amount' => '100.00',
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

    public function test_financial_user_can_register_manual_cash_outflow_when_balance_is_sufficient(): void
    {
        $company = Company::factory()->create();
        $financial = User::factory()->financial()->for($company)->create();

        $register = $company->cashRegisters()->create([
            'date' => '2026-04-30',
            'opening_amount' => 100,
            'opened_by' => $financial->id,
            'opened_at' => now(),
        ]);

        $this->actingAs($financial)
            ->post(route('finance.cash.outflow', false), [
                'cash_register_id' => $register->id,
                'amount' => '30.00',
                'category' => 'Insumos',
                'description' => 'Compra de pomada',
                'payment_method' => 'pix',
            ])
            ->assertRedirect(route('finance.cash', ['date' => '2026-04-30'], false));

        $this->assertDatabaseHas('cash_movements', [
            'cash_register_id' => $register->id,
            'type' => 'outflow',
            'amount' => '30.00',
            'payment_method' => 'pix',
        ]);
    }

    public function test_admin_can_close_cash_register_with_negative_balance_when_checked_balance_matches_and_notes_are_present(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $register = $company->cashRegisters()->create([
            'date' => '2026-04-30',
            'opening_amount' => 0,
            'opened_by' => $admin->id,
            'opened_at' => now(),
        ]);

        $register->movements()->create([
            'company_id' => $company->id,
            'type' => CashMovement::TYPE_INFLOW,
            'amount' => 70,
            'occurred_at' => '2026-04-30 10:00:00',
            'description' => 'Entradas do dia',
        ]);

        $register->movements()->create([
            'company_id' => $company->id,
            'type' => CashMovement::TYPE_OUTFLOW,
            'amount' => 168.87,
            'occurred_at' => '2026-04-30 11:00:00',
            'description' => 'Conta de energia',
        ]);

        $this->actingAs($admin)
            ->post(route('finance.cash.close', absolute: false), [
                'cash_register_id' => $register->id,
                'closing_amount' => '-98,87',
                'notes' => 'Dia fraco com pagamento de energia.',
            ])
            ->assertRedirect(route('finance.cash', ['date' => '2026-04-30'], false));

        $this->assertDatabaseHas('cash_registers', [
            'id' => $register->id,
            'closing_amount' => '-98.87',
            'notes' => 'Dia fraco com pagamento de energia.',
        ]);
    }

    public function test_admin_must_justify_negative_cash_register_closing(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $register = $company->cashRegisters()->create([
            'date' => '2026-04-30',
            'opening_amount' => 0,
            'opened_by' => $admin->id,
            'opened_at' => now(),
        ]);

        $register->movements()->create([
            'company_id' => $company->id,
            'type' => CashMovement::TYPE_OUTFLOW,
            'amount' => 98.87,
            'occurred_at' => '2026-04-30 11:00:00',
            'description' => 'Conta de energia',
        ]);

        $this->actingAs($admin)
            ->from(route('finance.cash', ['date' => '2026-04-30'], false))
            ->post(route('finance.cash.close', absolute: false), [
                'cash_register_id' => $register->id,
                'closing_amount' => '-98,87',
                'notes' => '',
            ])
            ->assertRedirect(route('finance.cash', ['date' => '2026-04-30'], false))
            ->assertSessionHasErrors([
                'notes' => 'O caixa está fechando com saldo negativo. Informe uma justificativa para concluir o fechamento.',
            ]);

        $this->assertNull($register->fresh()->closed_at);
    }

    public function test_admin_cannot_close_cash_register_when_checked_balance_diverges_from_expected_balance(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $register = $company->cashRegisters()->create([
            'date' => '2026-04-30',
            'opening_amount' => 70,
            'opened_by' => $admin->id,
            'opened_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('finance.cash', ['date' => '2026-04-30'], false))
            ->post(route('finance.cash.close', absolute: false), [
                'cash_register_id' => $register->id,
                'closing_amount' => '69,99',
                'notes' => 'Conferencia feita no fim do dia.',
            ])
            ->assertRedirect(route('finance.cash', ['date' => '2026-04-30'], false))
            ->assertSessionHasErrors('closing_amount');

        $this->assertNull($register->fresh()->closed_at);
    }

    public function test_financial_user_can_register_manual_cash_outflow_that_makes_balance_negative(): void
    {
        $company = Company::factory()->create();
        $financial = User::factory()->financial()->for($company)->create();

        $register = $company->cashRegisters()->create([
            'date' => '2026-04-30',
            'opening_amount' => 70,
            'opened_by' => $financial->id,
            'opened_at' => now(),
        ]);

        $this->actingAs($financial)
            ->post(route('finance.cash.outflow', false), [
                'cash_register_id' => $register->id,
                'amount' => '168,87',
                'category' => 'Energia',
                'description' => 'Conta de energia',
                'payment_method' => 'pix',
            ])
            ->assertRedirect(route('finance.cash', ['date' => '2026-04-30'], false));

        $this->assertSame('-98.87', number_format($register->fresh('movements')->expectedBalance(), 2, '.', ''));
    }
}
