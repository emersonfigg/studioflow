<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('company.onboarding', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertSame('admin', $user->role);
        $this->assertTrue((bool) $user->active);
        $this->assertNotNull($user->company_id);
        $this->assertDatabaseHas(Company::class, [
            'id' => $user->company_id,
            'name' => 'Empresa de Test User',
            'active' => true,
        ]);
        $this->assertNull($user->company->onboarding_completed_at);
    }
}
