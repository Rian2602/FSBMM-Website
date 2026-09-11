<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\ComplaintResource\Pages\ListComplaints;
use App\Filament\Sba\Resources\DuesResource\Pages\ListDues;
use App\Filament\Sba\Resources\MemberResource\Pages\ListMembers;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaBatchActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_activate_and_deactivate_members_keeps_member_count_in_sync(): void
    {
        [$org, $admin] = $this->fixture();
        $a = Member::factory()->for($org)->create(['status' => Member::STATUS_INACTIVE]);
        $b = Member::factory()->for($org)->create(['status' => Member::STATUS_INACTIVE]);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($admin)
            ->test(ListMembers::class)
            ->callTableBulkAction('activate', [$a->id, $b->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(Member::STATUS_ACTIVE, $a->fresh()->status);
        $this->assertSame(Member::STATUS_ACTIVE, $b->fresh()->status);
        $this->assertSame(2, $org->fresh()->member_count);

        Livewire::actingAs($admin)
            ->test(ListMembers::class)
            ->callTableBulkAction('deactivate', [$a->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(Member::STATUS_INACTIVE, $a->fresh()->status);
        $this->assertSame(1, $org->fresh()->member_count);
    }

    public function test_bulk_member_action_cannot_touch_another_organization(): void
    {
        [$orgA, $adminA] = $this->fixture();
        $orgB = Organization::factory()->create();
        $foreign = Member::factory()->for($orgB)->create(['status' => Member::STATUS_INACTIVE]);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($adminA)
            ->test(ListMembers::class)
            ->callTableBulkAction('activate', [$foreign->id]);

        $this->assertSame(Member::STATUS_INACTIVE, $foreign->fresh()->status);
    }

    public function test_bulk_resolve_complaints_sets_status_and_resolution_date(): void
    {
        [$org, $admin] = $this->fixture();
        $a = Complaint::factory()->for($org, 'organization')->create(['status' => 'baru']);
        $b = Complaint::factory()->for($org, 'organization')->create(['status' => 'diproses']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($admin)
            ->test(ListComplaints::class)
            ->callTableBulkAction('resolve', [$a->id, $b->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('selesai', $a->fresh()->status);
        $this->assertNotNull($a->fresh()->resolved_at);
        $this->assertSame('selesai', $b->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.status_changed']);
    }

    public function test_bulk_complaint_action_is_tenant_scoped(): void
    {
        [$orgA, $adminA] = $this->fixture();
        $orgB = Organization::factory()->create();
        $foreign = Complaint::factory()->for($orgB, 'organization')->create(['status' => 'baru']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($adminA)
            ->test(ListComplaints::class)
            ->callTableBulkAction('resolve', [$foreign->id]);

        $this->assertSame('baru', $foreign->fresh()->status);
    }

    public function test_bulk_delete_dues_is_tenant_scoped_and_audited(): void
    {
        [$orgA, $adminA] = $this->fixture();
        $memberA = Member::factory()->for($orgA)->create();
        $dueA = Due::factory()->for($orgA, 'organization')->for($memberA, 'member')->create();

        $orgB = Organization::factory()->create();
        $memberB = Member::factory()->for($orgB)->create();
        $dueB = Due::factory()->for($orgB, 'organization')->for($memberB, 'member')->create();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($adminA)
            ->test(ListDues::class)
            ->callTableBulkAction('deleteDues', [$dueA->id, $dueB->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseMissing('dues', ['id' => $dueA->id]);
        // The other organization's row is filtered out of the scoped table query.
        $this->assertDatabaseHas('dues', ['id' => $dueB->id]);

        $log = AuditLog::where('action', 'dues.bulk_deleted')->firstOrFail();
        $this->assertSame($orgA->id, $log->organization_id);
        $this->assertStringContainsString('1 catatan', $log->description);
    }

    public function test_bulk_delete_complaints_is_tenant_scoped_and_audited(): void
    {
        [$orgA, $adminA] = $this->fixture();
        $complaintA = Complaint::factory()->for($orgA, 'organization')->create(['title' => 'Judul Rahasia']);

        $orgB = Organization::factory()->create();
        $complaintB = Complaint::factory()->for($orgB, 'organization')->create();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($adminA)
            ->test(ListComplaints::class)
            ->callTableBulkAction('delete', [$complaintA->id, $complaintB->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseMissing('complaints', ['id' => $complaintA->id]);
        $this->assertDatabaseHas('complaints', ['id' => $complaintB->id]);

        $log = AuditLog::where('action', 'complaint.deleted')->firstOrFail();
        $this->assertSame($orgA->id, $log->organization_id);
        $this->assertStringNotContainsString('Judul Rahasia', $log->description);
    }

    /** @return array{0: Organization, 1: User} */
    private function fixture(): array
    {
        $org = Organization::factory()->create(['member_count' => 0]);
        $admin = User::factory()->sbaAdmin($org)->create();

        return [$org, $admin];
    }
}
