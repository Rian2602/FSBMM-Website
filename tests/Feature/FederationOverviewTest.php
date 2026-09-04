<?php

namespace Tests\Feature;

use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\OrganizationResource\Pages\ListOrganizations;
use App\Filament\Widgets\SbaAccountsOverviewWidget;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FederationOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_widget_lists_sba_accounts_with_their_organizations(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->create(['role' => User::ROLE_EDITOR]); // staff must not appear
        $orgA = Organization::factory()->create(['name' => 'SPM Alpha']);
        $orgB = Organization::factory()->create(['name' => 'SPM Beta']);
        User::factory()->sbaAdmin($orgA)->create(['name' => 'Pengurus Alpha']);
        User::factory()->sbaAdmin($orgB)->create(['name' => 'Pengurus Beta']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($super)
            ->test(SbaAccountsOverviewWidget::class)
            ->assertOk()
            ->assertSee('Pengurus Alpha')
            ->assertSee('SPM Alpha')
            ->assertSee('Pengurus Beta')
            ->assertDontSee('Editor Federasi');
    }

    public function test_overview_widget_is_super_admin_only(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor);
        $this->assertFalse(SbaAccountsOverviewWidget::canView());

        $this->actingAs($super);
        $this->assertTrue(SbaAccountsOverviewWidget::canView());
    }

    public function test_organization_with_sba_accounts_cannot_be_deleted(): void
    {
        $org = Organization::factory()->create();
        User::factory()->sbaAdmin($org)->create();

        $this->assertFalse(OrganizationResource::canDelete($org));
    }

    public function test_organization_without_sba_accounts_can_be_deleted(): void
    {
        $org = Organization::factory()->create();

        $this->assertTrue(OrganizationResource::canDelete($org));
    }

    public function test_bulk_delete_is_blocked_when_any_selected_organization_has_sba_accounts(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $withAccounts = Organization::factory()->create();
        User::factory()->sbaAdmin($withAccounts)->create();
        $empty = Organization::factory()->create();

        Livewire::actingAs($super)
            ->test(ListOrganizations::class)
            ->mountTableBulkAction('delete', [$withAccounts, $empty])
            ->callMountedTableBulkAction();

        $this->assertDatabaseHas('organizations', ['id' => $withAccounts->id]);
        $this->assertDatabaseHas('organizations', ['id' => $empty->id]); // whole batch aborted
    }
}
