<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->admin()->for($company)->create();

        return [
            'company_id' => $company->id,
            'date' => now()->toDateString(),
            'opening_amount' => 100.00,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'closing_amount' => null,
            'closed_by' => null,
            'closed_at' => null,
            'notes' => null,
        ];
    }
}
