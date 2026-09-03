<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_sba_login_page_is_reachable(): void
    {
        $this->get('/panel-sba/login')->assertStatus(200);
    }

    public function test_anonymous_is_redirected_away_from_sba_panel(): void
    {
        $this->get('/panel-sba')->assertRedirect('/panel-sba/login');
    }

    public function test_sba_admin_with_organization_can_enter_the_panel(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create();

        $this->actingAs($sba)->get('/panel-sba')->assertSuccessful();
    }

    public function test_sba_admin_without_organization_is_blocked_from_the_panel(): void
    {
        $orphan = User::factory()->create(['role' => User::ROLE_SBA_ADMIN]); // organization_id null

        $this->actingAs($orphan)->get('/panel-sba')->assertForbidden();
    }

    public function test_super_admin_and_editor_cannot_enter_sba_panel(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba')->assertForbidden();
    }

    public function test_sba_admin_cannot_enter_admin_panel(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create();

        $this->actingAs($sba)->get('/admin')->assertForbidden();
    }

    public function test_sba_admin_can_log_in_via_the_sba_login_form(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create([
            'email' => 'pengurus@spm-demo.fsbmm.test',
            'password' => 'password', // hashed by cast
        ]);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'pengurus@spm-demo.fsbmm.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_sba_admin_can_open_own_profile_page(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create();

        $this->actingAs($sba)->get('/panel-sba/profile')->assertSuccessful();
    }

    public function test_can_access_panel_matrix_per_panel_id(): void
    {
        $org = Organization::factory()->create();
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $sba = User::factory()->sbaAdmin($org)->create();
        $orphan = User::factory()->create(['role' => User::ROLE_SBA_ADMIN]); // no org

        $adminPanel = Filament::getPanel('admin');
        $sbaPanel = Filament::getPanel('sba');

        $this->assertTrue($super->canAccessPanel($adminPanel));
        $this->assertTrue($editor->canAccessPanel($adminPanel));
        $this->assertFalse($sba->canAccessPanel($adminPanel));
        $this->assertTrue($sba->canAccessPanel($sbaPanel));
        $this->assertFalse($super->canAccessPanel($sbaPanel));
        $this->assertFalse($editor->canAccessPanel($sbaPanel));
        $this->assertFalse($orphan->canAccessPanel($sbaPanel)); // organization required
    }
}
