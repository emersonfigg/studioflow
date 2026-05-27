<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Services\BirthdayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BirthdayAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_today_birthday_alert(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        Client::factory()->for($company)->create([
            'name' => 'Maria Aniversariante',
            'birthday' => now()->format('Y-m-d'),
            'phone' => '11999998888',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertSee('Aniversariante do dia')
            ->assertSee('Maria Aniversariante')
            ->assertSee('Mensagem de felicitações');
    }

    public function test_dashboard_hides_birthday_alert_when_no_birthdays_today(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        Client::factory()->for($company)->create([
            'birthday' => now()->subMonth()->format('Y-m-d'),
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertDontSee('Aniversariante do dia');
    }

    public function test_admin_can_save_birthday_message_template(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $message = 'Parabéns, {nome}! Com carinho, {empresa}.';

        $this->actingAs($admin)
            ->patchJson(route('company.birthday-message.update', absolute: false), [
                'birthday_congratulations_message' => $message,
            ])
            ->assertOk();

        $this->assertSame($message, $company->fresh()->birthday_congratulations_message);
    }

    public function test_birthday_service_builds_whatsapp_url_with_message(): void
    {
        $company = Company::factory()->create(['name' => 'Studio Beleza']);
        $client = Client::factory()->for($company)->create([
            'name' => 'João',
            'phone' => '(11) 99999-8888',
        ]);

        $service = app(BirthdayService::class);
        $message = $service->resolveMessage($company, $client, 'Oi {nome}, feliz aniversário da {empresa}!');
        $url = $service->whatsAppUrl($client, $message);

        $this->assertNotNull($url);
        $this->assertStringContainsString('https://wa.me/11999998888', $url);
        $this->assertStringContainsString('text=', $url);
        $this->assertStringContainsString(rawurlencode('João'), $url);
    }
}
