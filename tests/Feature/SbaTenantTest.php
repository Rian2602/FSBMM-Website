<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SbaTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_migration_has_nullable_organization_id(): void
    {
        $this->assertNull(User::factory()->create()->organization_id);
    }

    public function test_sba_admin_factory_state_links_an_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->sbaAdmin($org)->create();

        $this->assertSame(User::ROLE_SBA_ADMIN, $user->role);
        $this->assertTrue($user->organization->is($org));
        $this->assertTrue($org->users->contains($user));
    }

    public function test_sba_role_cannot_access_the_admin_panel(): void
    {
        // 'admin' panel exists from SP1; the full per-panel matrix lives in
        // SbaAuthTest (Task 2), once the 'sba' panel is registered.
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();

        $this->assertFalse($sba->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_organization_with_sba_account_reports_has_sba_accounts(): void
    {
        $with = Organization::factory()->create();
        User::factory()->sbaAdmin($with)->create();

        $without = Organization::factory()->create();

        $this->assertTrue($with->hasSbaAccounts());
        $this->assertFalse($without->hasSbaAccounts());
    }

    public function test_deleting_an_organization_detaches_accounts_instead_of_deleting_them(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();

        $org->delete(); // DB-level nullOnDelete (bypasses UI guards on purpose)

        $this->assertNull($sba->fresh()->organization_id);
        $this->assertDatabaseHas('users', ['id' => $sba->id]);
    }
}
