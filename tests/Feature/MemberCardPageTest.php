<?php

namespace Tests\Feature;

use App\Filament\Sba\Pages\MemberCardPage;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Organization;
use App\Models\User;
use App\Support\MemberCardService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberCardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sba_admin_can_see_own_cards(): void
    {
        [$user, $org, $member] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $user);

        $this->actingAs($user)
            ->get('/panel-sba/member-cards')
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($card->card_number);
    }

    public function test_sba_admin_cannot_see_other_sba_cards(): void
    {
        [$user] = $this->fixture();

        $otherOrg = Organization::factory()->create(['name' => 'SPM Lain']);
        $otherAdmin = User::factory()->sbaAdmin($otherOrg)->create();
        $otherMember = Member::factory()->for($otherOrg)->create(['name' => 'Anggota Orang Lain']);
        $otherCard = (new MemberCardService)->issue($otherMember, $otherAdmin);

        $this->actingAs($user)
            ->get('/panel-sba/member-cards')
            ->assertOk()
            ->assertDontSee('Anggota Orang Lain')
            ->assertDontSee($otherCard->card_number);
    }

    public function test_issue_action_works(): void
    {
        [$user, $org, $member] = $this->fixture();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(MemberCardPage::class)
            ->callTableAction('issueNew', data: ['member_id' => $member->id])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('member_cards', [
            'member_id' => $member->id,
            'status' => MemberCard::STATUS_ACTIVE,
        ]);
    }

    public function test_revoke_action_works(): void
    {
        [$user, $org, $member] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $user);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(MemberCardPage::class)
            ->callTableAction('revoke', $card->id, data: ['reason' => 'Kartu rusak'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(MemberCard::STATUS_REVOKED, $card->fresh()->status);
    }

    public function test_reissue_action_works(): void
    {
        [$user, $org, $member] = $this->fixture();
        $old = (new MemberCardService)->issue($member, $user);
        (new MemberCardService)->revoke($old, 'Rusak', $user);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(MemberCardPage::class)
            ->callTableAction('reissue', $old->id)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, MemberCard::where('member_id', $member->id)
            ->where('status', MemberCard::STATUS_ACTIVE)->count());
        $this->assertSame(MemberCard::STATUS_REVOKED, $old->fresh()->status);
    }

    public function test_verification_token_not_displayed_by_default(): void
    {
        [$user, $org, $member] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $user);

        $this->actingAs($user)
            ->get('/panel-sba/member-cards')
            ->assertOk()
            ->assertDontSee($card->verification_token);
    }

    private function fixture(): array
    {
        $org = Organization::factory()->create();
        $user = User::factory()->sbaAdmin($org)->create();
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);

        return [$user, $org, $member];
    }
}
