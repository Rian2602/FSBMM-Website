<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\DuesResource\Pages\CreateDues;
use App\Models\Due;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaDuesTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya (DuesTest)']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_anonymous_is_redirected_to_sba_login(): void
    {
        $this->get('/panel-sba/dues')->assertRedirect('/panel-sba/login');
    }

    public function test_super_admin_and_editor_cannot_access_the_sba_dues_page(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba/dues')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba/dues')->assertForbidden();
    }

    public function test_sba_admin_can_list_their_own_dues_only(): void
    {
        [$user, $org] = $this->sbaUser();
        $member = Member::factory()->for($org)->create();
        Due::factory()->for($org)->for($member)->create(['period' => '2026-09']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        $otherMember = Member::factory()->for($other)->create();
        Due::factory()->for($other)->for($otherMember)->create(['period' => '2026-09']);

        $this->actingAs($user)
            ->get('/panel-sba/dues')
            ->assertOk()
            ->assertSee('2026-09')
            ->assertDontSee('Anggota Milik Orang Lain');
    }

    public function test_editing_another_organizations_dues_url_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create(['name' => 'SPM Milik Orang Lain']);
        $otherMember = Member::factory()->for($other)->create();
        $otherDue = Due::factory()->for($other)->for($otherMember)->create();

        $this->actingAs($user)
            ->get('/panel-sba/dues/'.$otherDue->id.'/edit')
            ->assertNotFound();
    }

    public function test_creating_a_due_auto_links_organization_and_recorded_by(): void
    {
        [$user, $org] = $this->sbaUser();
        $member = Member::factory()->for($org)->create(['name' => 'Anggota Uji']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $member->id,
                'period' => '2026-09',
                'amount' => 75000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('dues', [
            'member_id' => $member->id,
            'period' => '2026-09',
            'organization_id' => $org->id,
            'recorded_by' => $user->id,
        ]);
    }

    public function test_required_fields_are_validated_not_500(): void
    {
        [$user] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm(['member_id' => null, 'period' => '', 'amount' => null, 'paid_at' => null])
            ->call('create')
            ->assertHasFormErrors(['member_id' => 'required', 'period' => 'required', 'amount' => 'required', 'paid_at' => 'required']);
    }

    public function test_period_must_match_yyyy_mm_format(): void
    {
        [$user, $org] = $this->sbaUser();
        $member = Member::factory()->for($org)->create();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $member->id,
                'period' => 'bukan-periode',
                'amount' => 50000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors(['period']);
    }

    public function test_duplicate_member_period_fails_validation(): void
    {
        [$user, $org] = $this->sbaUser();
        $member = Member::factory()->for($org)->create();
        Due::factory()->for($org)->for($member)->create(['period' => '2026-09']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $member->id,
                'period' => '2026-09',
                'amount' => 50000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors(['member_id']);
    }

    public function test_member_from_another_org_is_rejected(): void
    {
        [$user, $org] = $this->sbaUser();
        $ownMember = Member::factory()->for($org)->create(['name' => 'Anggota Milik Sendiri']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        $foreignMember = Member::factory()->for($other)->create(['name' => 'Anggota Milik Orang Lain']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        // Positive path: own member succeeds
        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $ownMember->id,
                'period' => '2026-10',
                'amount' => 50000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Negative path: foreign member rejected by server-side validation
        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $foreignMember->id,
                'period' => '2026-11',
                'amount' => 50000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors(['member_id']);
    }
}
