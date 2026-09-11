<?php

namespace Tests\Feature;

use App\Exports\MemberExport;
use App\Filament\Sba\Pages\AuditTrailPage as SbaAuditTrailPage;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Support\MemberCardService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_creation_is_audited_without_pii(): void
    {
        $org = Organization::factory()->create();

        Member::factory()->for($org)->create([
            'name' => 'Budi Rahasia',
            'nik' => '9988776655443322',
        ]);

        $log = AuditLog::where('action', 'member.created')->firstOrFail();

        $this->assertSame($org->id, $log->organization_id);
        $this->assertStringNotContainsString('Budi Rahasia', $log->description);
        $this->assertStringNotContainsString('9988776655443322', $log->description);
    }

    public function test_member_update_records_column_names_only(): void
    {
        $org = Organization::factory()->create();
        $member = Member::factory()->for($org)->create();

        $member->update(['status' => Member::STATUS_INACTIVE]);

        $log = AuditLog::where('action', 'member.updated')->firstOrFail();

        $this->assertStringContainsString('status', $log->description);
        $this->assertStringNotContainsString($member->name, $log->description);
    }

    public function test_card_lifecycle_is_audited(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->sbaAdmin($org)->create(['name' => 'Admin Audit']);
        $member = Member::factory()->for($org)->create();

        $service = new MemberCardService;
        $card = $service->issue($member, $admin);
        $service->revoke($card, 'Kartu rusak', $admin);

        $issued = AuditLog::where('action', 'card.issued')->firstOrFail();
        $this->assertSame($admin->id, $issued->user_id);
        $this->assertSame('Admin Audit', $issued->user_name);
        $this->assertSame($org->id, $issued->organization_id);
        $this->assertStringContainsString($card->card_number, $issued->description);

        $this->assertDatabaseHas('audit_logs', ['action' => 'card.revoked']);
    }

    public function test_complaint_status_change_is_audited(): void
    {
        $org = Organization::factory()->create();
        $complaint = Complaint::factory()->for($org, 'organization')->create([
            'status' => 'baru',
            'title' => 'Judul Pengaduan Rahasia',
        ]);

        $complaint->update(['status' => 'selesai']);

        $log = AuditLog::where('action', 'complaint.status_changed')->firstOrFail();

        $this->assertStringContainsString('baru', $log->description);
        $this->assertStringContainsString('selesai', $log->description);
        $this->assertStringNotContainsString('Judul Pengaduan Rahasia', $log->description);
    }

    public function test_export_generation_is_audited(): void
    {
        $org = Organization::factory()->create();
        Member::factory()->for($org)->create();

        MemberExport::generate($org->id, [], 'csv');

        $log = AuditLog::where('action', 'export.generated')->firstOrFail();

        $this->assertSame($org->id, $log->organization_id);
        $this->assertStringContainsString('anggota', $log->description);
        $this->assertStringContainsString('CSV', $log->description);
    }

    public function test_complaint_delete_is_audited_without_pii(): void
    {
        $org = Organization::factory()->create();
        $complaint = Complaint::factory()->for($org, 'organization')->create([
            'title' => 'Judul Pengaduan Rahasia',
            'description' => 'Isi pengaduan yang sangat rahasia',
        ]);

        $complaint->delete();

        $log = AuditLog::where('action', 'complaint.deleted')->firstOrFail();

        $this->assertSame($org->id, $log->organization_id);
        $this->assertStringNotContainsString('Judul Pengaduan Rahasia', $log->description);
        $this->assertStringNotContainsString('Isi pengaduan yang sangat rahasia', $log->description);
    }

    public function test_admin_audit_page_is_super_admin_only(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();

        $this->actingAs($super)->get('/admin/audit-trail')->assertOk()->assertSee('Jejak Audit');
        $this->actingAs($editor)->get('/admin/audit-trail')->assertForbidden();
        $this->actingAs($sba)->get('/admin/audit-trail')->assertForbidden();
    }

    public function test_admin_audit_page_never_exposes_member_names(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $org = Organization::factory()->create();

        Member::factory()->for($org)->create(['name' => 'Budi Rahasia']);

        $this->actingAs($super)
            ->get('/admin/audit-trail')
            ->assertOk()
            ->assertSee('member.created')
            ->assertDontSee('Budi Rahasia');
    }

    public function test_sba_audit_page_is_tenant_scoped(): void
    {
        $orgA = Organization::factory()->create();
        $adminA = User::factory()->sbaAdmin($orgA)->create();
        $orgB = Organization::factory()->create();

        Member::factory()->for($orgA)->create();
        Member::factory()->for($orgB)->create();

        $logA = AuditLog::where('organization_id', $orgA->id)->firstOrFail();
        $logB = AuditLog::where('organization_id', $orgB->id)->firstOrFail();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($adminA)
            ->test(SbaAuditTrailPage::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$logA])
            ->assertCanNotSeeTableRecords([$logB]);
    }

    public function test_sba_audit_page_renders_for_an_sba_admin(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->sbaAdmin($org)->create();
        Member::factory()->for($org)->create();

        $this->actingAs($admin)
            ->get('/panel-sba/audit-trail')
            ->assertOk()
            ->assertSee('Jejak Audit')
            ->assertSee('member.created');
    }
}
