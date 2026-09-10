<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Organization;
use App\Models\User;
use App\Support\MemberCardService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_card_for_active_member(): void
    {
        [$member, $creator] = $this->fixture();

        $card = (new MemberCardService)->issue($member, $creator);

        $this->assertDatabaseHas('member_cards', [
            'id' => $card->id,
            'organization_id' => $member->organization_id,
            'member_id' => $member->id,
            'card_number' => $card->card_number,
            'status' => MemberCard::STATUS_ACTIVE,
            'created_by' => $creator->id,
        ]);
        $this->assertTrue($card->issued_at !== null);
        $this->assertMatchesRegularExpression('/^FSBMM-\d{4}-[A-Z0-9]{8}$/', $card->card_number);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $card->verification_token);
    }

    public function test_card_number_is_unique(): void
    {
        [$member, $creator] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $creator);

        $this->expectException(QueryException::class);

        MemberCard::create([
            'organization_id' => $member->organization_id,
            'member_id' => $member->id,
            'card_number' => $card->card_number,
            'verification_token' => str_repeat('f', 64),
            'status' => MemberCard::STATUS_ACTIVE,
            'issued_at' => now(),
            'created_by' => $creator->id,
        ]);
    }

    public function test_verification_token_is_unique(): void
    {
        [$member, $creator] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $creator);

        $this->expectException(QueryException::class);

        MemberCard::create([
            'organization_id' => $member->organization_id,
            'member_id' => $member->id,
            'card_number' => 'FSBMM-2026-A1B2C3D4',
            'verification_token' => $card->verification_token,
            'status' => MemberCard::STATUS_ACTIVE,
            'issued_at' => now(),
            'created_by' => $creator->id,
        ]);
    }

    public function test_only_one_active_card_per_member(): void
    {
        [$member, $creator] = $this->fixture();
        $service = new MemberCardService;

        $first = $service->issue($member, $creator);
        $second = $service->issue($member, $creator);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(MemberCard::STATUS_REVOKED, $first->fresh()->status);
        $this->assertSame('Digantikan kartu baru', $first->fresh()->revocation_reason);
        $this->assertSame(1, MemberCard::where('member_id', $member->id)
            ->where('status', MemberCard::STATUS_ACTIVE)->count());
    }

    public function test_revoke_card(): void
    {
        [$member, $creator] = $this->fixture();
        $service = new MemberCardService;

        $card = $service->issue($member, $creator);
        $service->revoke($card, 'Kartu rusak', $creator);

        $card->refresh();
        $this->assertSame(MemberCard::STATUS_REVOKED, $card->status);
        $this->assertTrue($card->revoked_at !== null);
        $this->assertSame('Kartu rusak', $card->revocation_reason);
    }

    public function test_reissue_card(): void
    {
        [$member, $creator] = $this->fixture();
        $service = new MemberCardService;

        $old = $service->issue($member, $creator);
        $new = $service->reissue($old, $creator);

        $this->assertSame(MemberCard::STATUS_REVOKED, $old->fresh()->status);
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame(MemberCard::STATUS_ACTIVE, $new->fresh()->status);
    }

    public function test_card_history_retained(): void
    {
        [$member, $creator] = $this->fixture();
        $service = new MemberCardService;

        $first = $service->issue($member, $creator);
        $second = $service->issue($member, $creator);
        $service->revoke($second, 'Hilang', $creator);
        $third = $service->issue($member, $creator);

        $this->assertSame(3, MemberCard::where('member_id', $member->id)->count());
        $this->assertSame(2, MemberCard::where('status', MemberCard::STATUS_REVOKED)
            ->where('member_id', $member->id)->count());
        $this->assertSame('Digantikan kartu baru', $first->fresh()->revocation_reason);
        $this->assertSame('Hilang', $second->fresh()->revocation_reason);
        $this->assertSame(MemberCard::STATUS_ACTIVE, $third->fresh()->status);
    }

    public function test_inactive_member_cannot_receive_new_card(): void
    {
        [$member, $creator] = $this->fixture();

        $member->update(['status' => Member::STATUS_INACTIVE]);

        $this->expectException(DomainException::class);

        (new MemberCardService)->issue($member, $creator);
    }

    public function test_sba_admin_cannot_issue_for_other_sba_member(): void
    {
        $orgB = Organization::factory()->create();
        $adminB = User::factory()->sbaAdmin($orgB)->create();
        $memberB = Member::factory()->for($orgB)->create(['status' => Member::STATUS_ACTIVE]);

        [$member, $creator] = $this->fixture();

        $this->expectException(AuthorizationException::class);

        (new MemberCardService)->issue($memberB, $creator);
    }

    public function test_validate_token_returns_card_for_valid_token(): void
    {
        [$member, $creator] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $creator);

        $found = (new MemberCardService)->validateToken($card->verification_token);

        $this->assertNotNull($found);
        $this->assertSame($card->id, $found->id);
    }

    public function test_validate_token_returns_null_for_invalid_token(): void
    {
        $found = (new MemberCardService)->validateToken('nonexistent-token');

        $this->assertNull($found);
    }

    public function test_validate_token_returns_revoked_card(): void
    {
        [$member, $creator] = $this->fixture();
        $service = new MemberCardService;
        $card = $service->issue($member, $creator);
        $service->revoke($card, 'Rusak', $creator);

        $found = $service->validateToken($card->verification_token);

        $this->assertNotNull($found);
        $this->assertSame(MemberCard::STATUS_REVOKED, $found->status);
    }

    public function test_get_card_history_returns_all_cards(): void
    {
        [$member, $creator] = $this->fixture();
        $service = new MemberCardService;

        $first = $service->issue($member, $creator);
        $second = $service->issue($member, $creator);

        $history = $service->getCardHistory($member);

        $this->assertCount(2, $history);
        $this->assertSame($second->id, $history->first()->id);
    }

    private function fixture(): array
    {
        $org = Organization::factory()->create();
        $creator = User::factory()->sbaAdmin($org)->create();
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);

        return [$member, $creator];
    }
}
