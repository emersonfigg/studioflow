<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $company = Company::firstOrCreate([
            'name' => 'Empresa Padrao',
        ], [
            'active' => true,
        ]);

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'company_id' => $company->id,
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        );

        User::updateOrCreate(
            ['email' => 'superadmin@studioflow.local'],
            [
                'company_id' => null,
                'name' => 'Super Admin',
                'password' => Hash::make('12345678'),
                'role' => 'admin',
                'global_role' => 'super_admin',
            ],
        );
    }
}
