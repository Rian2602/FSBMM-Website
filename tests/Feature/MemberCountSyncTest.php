<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCountSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_active_members_increments_member_count(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);

        Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);
        Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);

        $this->assertSame(2, $org->fresh()->member_count);
    }

    public function test_inactive_members_are_not_counted(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);

        Member::factory()->for($org)->create(['status' => Member::STATUS_INACTIVE]);

        $this->assertSame(0, $org->fresh()->member_count);
    }

    public function test_changing_status_to_inactive_decrements_count(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);

        $member->update(['status' => Member::STATUS_INACTIVE]);

        $this->assertSame(0, $org->fresh()->member_count);
    }

    public function test_soft_deleting_a_member_decrements_count(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);

        $member->delete(); // soft delete

        $this->assertSame(0, $org->fresh()->member_count);
        $this->assertSoftDeleted($member);
    }

    public function test_restoring_a_member_re_increments_count(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);
        $member->delete();

        $member->restore();

        $this->assertSame(1, $org->fresh()->member_count);
    }

    public function test_organization_with_members_reports_has_members(): void
    {
        $with = Organization::factory()->create();
        Member::factory()->for($with)->create();

        $without = Organization::factory()->create();

        $this->assertTrue($with->hasMembers());
        $this->assertFalse($without->hasMembers());
    }

    public function test_nik_is_unique_per_organization_not_globally(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Member::factory()->for($orgA)->create(['nik' => '11223344']);
        $sameNikOtherOrg = Member::factory()->for($orgB)->create(['nik' => '11223344']);

        $this->assertDatabaseHas('members', ['id' => $sameNikOtherOrg->id]);

        $this->expectException(QueryException::class);
        Member::factory()->for($orgA)->create(['nik' => '11223344']);
    }
}
