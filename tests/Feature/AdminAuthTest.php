<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertStatus(200);
    }

    public function test_anonymous_is_redirected_away_from_admin(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_super_admin_can_enter_the_panel(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($user)->get('/admin')->assertSuccessful();
    }

    public function test_role_column_defaults_to_editor(): void
    {
        $this->assertSame(User::ROLE_EDITOR, User::factory()->create()->role);
    }

    public function test_seeded_super_admin_can_log_in_via_the_login_form(): void
    {
        // Real credential flow through Filament's Livewire login — not actingAs().
        putenv('FSBMM_ADMIN_EMAIL=admin@fsbmm.test');
        putenv('FSBMM_ADMIN_PASSWORD=password');
        $this->seed();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@fsbmm.test',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_editor_can_enter_panel_but_is_blocked_from_user_management(): void
    {
        $editor = User::factory()->create(); // default role editor

        $this->actingAs($editor)->get('/admin')->assertSuccessful();
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
    }

    public function test_super_admin_can_manage_users(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($super)->get('/admin/users')->assertSuccessful();
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($super);

        $this->assertFalse(UserResource::canDelete($super->fresh()));
    }

    public function test_super_admin_cannot_delete_the_last_super_admin(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($super);

        $this->assertFalse(UserResource::canDelete($super->fresh()));
    }

    public function test_super_admin_can_delete_another_super_admin_when_more_than_one(): void
    {
        $a = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $b = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($a);

        $this->assertTrue(UserResource::canDelete($b->fresh()));
    }

    public function test_super_admin_cannot_edit_their_own_account(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($super);

        $this->assertFalse(UserResource::canEdit($super->fresh()));
    }

    public function test_super_admin_can_edit_an_editor_when_only_one_super_admin_exists(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create();

        $this->actingAs($super);

        $this->assertTrue(UserResource::canEdit($editor->fresh()));
    }

    public function test_bulk_delete_is_blocked_when_only_one_super_admin_exists(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($super);

        $this->assertFalse(UserResource::canDeleteAny());
    }

    public function test_bulk_delete_is_allowed_when_multiple_super_admins_exist(): void
    {
        $a = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($a);

        $this->assertTrue(UserResource::canDeleteAny());
    }
}
