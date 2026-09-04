<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_creates_an_sba_account_with_organization(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $org = Organization::factory()->create();

        Livewire::actingAs($super)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Pengurus SPM Contoh',
                'email' => 'pengurus@contoh.fsbmm.test',
                'password' => 'rahasia-awal',
                'role' => User::ROLE_SBA_ADMIN,
                'organization_id' => $org->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'pengurus@contoh.fsbmm.test',
            'role' => User::ROLE_SBA_ADMIN,
            'organization_id' => $org->id,
        ]);
    }

    public function test_sba_account_requires_an_organization(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        Livewire::actingAs($super)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Pengurus Tanpa Org',
                'email' => 'yatim@contoh.fsbmm.test',
                'password' => 'rahasia-awal',
                'role' => User::ROLE_SBA_ADMIN,
                'organization_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['organization_id' => 'required']);
    }

    public function test_staff_roles_never_receive_an_organization(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $org = Organization::factory()->create();

        Livewire::actingAs($super)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Editor Federasi',
                'email' => 'editor@fsbmm.test',
                'password' => 'rahasia-awal',
                'role' => User::ROLE_EDITOR,
                'organization_id' => $org->id, // must be ignored
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'editor@fsbmm.test', 'organization_id' => null]);
    }

    public function test_super_admin_can_reassign_and_demote_an_sba_account(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($orgA)->create();

        Livewire::actingAs($super)
            ->test(EditUser::class, ['record' => $sba->getRouteKey()])
            ->fillForm(['organization_id' => $orgB->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($orgB->id, $sba->fresh()->organization_id);

        Livewire::actingAs($super)
            ->test(EditUser::class, ['record' => $sba->getRouteKey()])
            ->fillForm(['role' => User::ROLE_EDITOR])
            ->call('save')
            ->assertHasNoFormErrors();

        $sba->refresh();
        $this->assertSame(User::ROLE_EDITOR, $sba->role);
        $this->assertNull($sba->organization_id); // cleared on demotion
    }

    public function test_users_table_shows_sba_account_with_its_organization(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $org = Organization::factory()->create(['name' => 'SPM Terlihat Di Tabel']);
        $sba = User::factory()->sbaAdmin($org)->create();

        $this->actingAs($super)->get('/admin/users')
            ->assertOk()
            ->assertSee($sba->name)
            ->assertSee('SPM Terlihat Di Tabel');
    }
}
