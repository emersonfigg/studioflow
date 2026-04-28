<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_professionals_from_own_company(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        Storage::disk('public')->put('professionals/admin-master.jpg', 'admin-master');
        $admin = User::factory()->admin()->for($company)->create(['name' => 'Admin Master']);
        $admin->update(['photo_path' => 'professionals/admin-master.jpg']);
        User::factory()->for($company)->create(['name' => 'Profissional Interno']);
        User::factory()->create(['name' => 'Outro Profissional']);

        $this
            ->actingAs($admin)
            ->get(route('team.index', absolute: false))
            ->assertOk()
            ->assertSee('Profissional Interno')
            ->assertSee('Admin Master')
            ->assertSee('/storage/professionals/admin-master.jpg')
            ->assertDontSee('Outro Profissional');
    }

    public function test_admin_creates_staff_professional_with_photo_path(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $photo = UploadedFile::fake()->image('lucas.jpg');

        $this
            ->actingAs($admin)
            ->post(route('team.store', absolute: false), [
                'name' => 'Lucas Corte',
                'email' => 'lucas@example.com',
                'password' => 'password123',
                'role' => 'staff',
                'active' => '1',
                'commission_type' => 'none',
                'commission_value' => null,
                'photo' => $photo,
            ])
            ->assertRedirect(route('team.index', absolute: false));

        $member = User::query()->where('email', 'lucas@example.com')->firstOrFail();

        $this->assertSame($company->id, $member->company_id);
        $this->assertSame('staff', $member->role);
        $this->assertNotNull($member->photo_path);
        Storage::disk('public')->assertExists($member->photo_path);
    }

    public function test_admin_edits_professional_and_can_keep_or_replace_photo(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        Storage::disk('public')->put('professionals/original-photo.jpg', 'original-photo');

        $member = User::factory()->for($company)->create([
            'commission_type' => 'none',
            'commission_value' => null,
            'photo_path' => 'professionals/original-photo.jpg',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('team.update', $member, absolute: false), [
                '_method' => 'PATCH',
                'name' => $member->name,
                'email' => $member->email,
                'password' => '',
                'role' => 'staff',
                'active' => '1',
                'commission_type' => 'percent',
                'commission_value' => '45.50',
            ])
            ->assertRedirect(route('team.index', absolute: false));

        $member->refresh();

        $this->assertSame('professionals/original-photo.jpg', $member->photo_path);
        $this->assertSame('percent', $member->commission_type);
        $this->assertSame('45.50', $member->commission_value);
        Storage::disk('public')->assertExists('professionals/original-photo.jpg');

        $newPhoto = UploadedFile::fake()->image('updated-photo.png');

        $this
            ->actingAs($admin)
            ->post(route('team.update', $member, absolute: false), [
                '_method' => 'PATCH',
                'name' => $member->name,
                'email' => $member->email,
                'password' => '',
                'role' => 'staff',
                'active' => '1',
                'commission_type' => 'percent',
                'commission_value' => '45.50',
                'photo' => $newPhoto,
            ])
            ->assertRedirect(route('team.index', absolute: false));

        $member->refresh();

        $this->assertNotSame('professionals/original-photo.jpg', $member->photo_path);
        Storage::disk('public')->assertExists($member->photo_path);
    }

    public function test_admin_can_inactivate_professional(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $member = User::factory()->for($company)->create([
            'active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('team.toggle-active', $member, absolute: false))
            ->assertRedirect(route('team.index', absolute: false));

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'active' => false,
        ]);
    }

    public function test_staff_cannot_access_team_management(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->for($company)->create();
        $member = User::factory()->for($company)->create();

        $this
            ->actingAs($staff)
            ->get(route('team.index', absolute: false))
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->get(route('team.create', absolute: false))
            ->assertForbidden();

        $this
            ->actingAs($staff)
            ->get(route('team.edit', $member, absolute: false))
            ->assertForbidden();
    }

    public function test_admin_cannot_access_or_edit_user_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $otherUser = User::factory()->for($otherCompany)->create([
            'name' => 'Outro Time',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('team.edit', $otherUser, absolute: false))
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->patch(route('team.update', $otherUser, absolute: false), [
                'name' => 'Nao Pode',
                'email' => 'naopode@example.com',
                'password' => '',
                'role' => 'staff',
                'active' => '1',
                'commission_type' => 'fixed',
                'commission_value' => '50.00',
            ])
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->patch(route('team.toggle-active', $otherUser, absolute: false))
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'name' => 'Outro Time',
            'company_id' => $otherCompany->id,
        ]);
    }
}
