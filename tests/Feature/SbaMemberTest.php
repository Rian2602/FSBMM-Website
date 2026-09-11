<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\MemberResource\Pages\CreateMember;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaMemberTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya (MemberTest)']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_anonymous_is_redirected_to_sba_login(): void
    {
        $this->get('/panel-sba/members')->assertRedirect('/panel-sba/login');
    }

    public function test_super_admin_and_editor_cannot_access_the_sba_members_page(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba/members')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba/members')->assertForbidden();
    }

    public function test_sba_admin_can_list_their_own_members_only(): void
    {
        [$user, $org] = $this->sbaUser();
        Member::factory()->for($org)->create(['name' => 'Anggota Milik Saya']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        Member::factory()->for($other)->create(['name' => 'Anggota Milik Orang Lain']);

        $this->actingAs($user)
            ->get('/panel-sba/members')
            ->assertOk()
            ->assertSee('Anggota Milik Saya')
            ->assertDontSee('Anggota Milik Orang Lain');
    }

    public function test_editing_another_organizations_member_url_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create(['name' => 'SPM Milik Orang Lain']);
        $otherMember = Member::factory()->for($other)->create();

        $this->actingAs($user)
            ->get('/panel-sba/members/' . $otherMember->id . '/edit')
            ->assertNotFound();
    }

    public function test_creating_a_member_auto_links_the_logged_in_sba_organization(): void
    {
        [$user, $org] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateMember::class)
            ->fillForm([
                'nik' => '1122334455667788',
                'name' => 'Anggota Baru',
                'status' => 'aktif',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('members', [
            'name' => 'Anggota Baru',
            'organization_id' => $org->id,
        ]);
    }

    public function test_required_fields_are_validated_not_500(): void
    {
        [$user] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateMember::class)
            ->fillForm(['nik' => '', 'name' => ''])
            ->call('create')
            ->assertHasFormErrors(['nik' => 'required', 'name' => 'required']);
    }
}
