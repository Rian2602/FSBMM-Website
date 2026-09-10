<?php

namespace Tests\Feature;

use App\Filament\Sba\Pages\AttendanceReportPage;
use App\Filament\Sba\Pages\ComplaintReportPage;
use App\Filament\Sba\Pages\DuesReportPage;
use App\Filament\Sba\Pages\MemberReportPage;
use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    private function createSbaAdmin(string $orgName = 'SBA A'): array
    {
        $org = Organization::factory()->create(['name' => $orgName]);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    /**
     * Login as the user and resolve the given report page's stats with optional filter data.
     *
     * @param  class-string  $pageClass
     */
    private function statsFor(string $pageClass, User $user, array $data = []): array
    {
        auth()->login($user);

        $page = new $pageClass;
        $page->data = $data;

        return $page->getStats();
    }

    // ---------------------------------------------------------------
    // Member report
    // ---------------------------------------------------------------

    public function test_member_report_shows_correct_counts(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        Member::factory()->for($org)->count(3)->create(['status' => 'aktif']);
        Member::factory()->for($org)->count(2)->create(['status' => 'nonaktif']);

        $stats = $this->statsFor(MemberReportPage::class, $admin);

        $this->assertSame(5, $stats['total']);
        $this->assertSame(3, $stats['active']);
        $this->assertSame(2, $stats['inactive']);
    }

    public function test_member_report_filters_work_server_side(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        Member::factory()->for($org)->count(2)->create(['status' => 'aktif', 'department' => 'Produksi']);
        Member::factory()->for($org)->create(['status' => 'aktif', 'department' => 'Teknik']);
        Member::factory()->for($org)->create(['status' => 'nonaktif', 'department' => 'Teknik']);

        $byDepartment = $this->statsFor(MemberReportPage::class, $admin, ['department' => 'Produksi']);
        $this->assertSame(2, $byDepartment['total']);
        $this->assertSame(2, $byDepartment['active']);
        $this->assertSame(0, $byDepartment['inactive']);

        $byStatus = $this->statsFor(MemberReportPage::class, $admin, ['status' => 'nonaktif']);
        $this->assertSame(1, $byStatus['total']);
        $this->assertSame(0, $byStatus['active']);
        $this->assertSame(1, $byStatus['inactive']);
    }

    public function test_member_report_breakdowns_are_correct(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        Member::factory()->for($org)->count(3)->create(['department' => 'Produksi', 'position' => 'Operator']);
        Member::factory()->for($org)->count(2)->create(['department' => 'Teknik', 'position' => 'Supervisor']);

        auth()->login($admin);
        $page = new MemberReportPage;

        $this->assertSame(['Produksi' => 3, 'Teknik' => 2], $page->getBreakdownByDepartment());
        $this->assertSame(['Operator' => 3, 'Supervisor' => 2], $page->getBreakdownByPosition());
    }

    public function test_member_report_table_paginates(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        foreach (range(1, 30) as $i) {
            Member::factory()->for($org)->create(['name' => sprintf('Anggota %02d', $i)]);
        }

        Livewire::actingAs($admin)
            ->test(MemberReportPage::class)
            ->assertOk()
            ->assertSee('Anggota 01')
            ->assertDontSee('Anggota 30')
            // Default per page is 10 (first pagination option), so page 3 holds 21-30.
            ->call('gotoPage', 3)
            ->assertSee('Anggota 30')
            ->assertDontSee('Anggota 01');
    }

    public function test_member_report_is_tenant_scoped(): void
    {
        [$adminA, $orgA] = $this->createSbaAdmin();
        [, $orgB] = $this->createSbaAdmin('SBA B');

        Member::factory()->for($orgA)->create(['name' => 'Anggota A']);
        Member::factory()->for($orgB)->create(['name' => 'Anggota B']);

        $stats = $this->statsFor(MemberReportPage::class, $adminA);
        $this->assertSame(1, $stats['total']);

        Livewire::actingAs($adminA)
            ->test(MemberReportPage::class)
            ->assertSee('Anggota A')
            ->assertDontSee('Anggota B');
    }

    // ---------------------------------------------------------------
    // Dues report
    // ---------------------------------------------------------------

    public function test_dues_report_shows_correct_aggregates(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        $member1 = Member::factory()->for($org)->create(['status' => 'aktif']);
        $member2 = Member::factory()->for($org)->create(['status' => 'aktif']);
        $member3 = Member::factory()->for($org)->create(['status' => 'aktif']);
        Member::factory()->for($org)->create(['status' => 'nonaktif']);

        Due::factory()->for($org, 'organization')->for($member1, 'member')->create([
            'period' => '2026-09', 'amount' => 50000, 'paid_at' => '2026-09-05',
        ]);
        Due::factory()->for($org, 'organization')->for($member2, 'member')->create([
            'period' => '2026-09', 'amount' => 30000, 'paid_at' => '2026-09-06',
        ]);
        Due::factory()->for($org, 'organization')->for($member1, 'member')->create([
            'period' => '2026-08', 'amount' => 50000, 'paid_at' => '2026-08-05',
        ]);

        $stats = $this->statsFor(DuesReportPage::class, $admin);

        $this->assertSame(3, $stats['payment_count']);
        $this->assertSame(130000, (int) $stats['total_amount']);
        $this->assertEqualsWithDelta(43333.33, $stats['average_amount'], 0.01);
        $this->assertSame(3, $stats['active_members']);
        // 3 active members − 2 distinct payers (member1, member2) = 1 unpaid
        $this->assertSame(1, $stats['members_without_dues']);
    }

    public function test_dues_report_filters_work_server_side(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        $member1 = Member::factory()->for($org)->create();
        $member2 = Member::factory()->for($org)->create();

        Due::factory()->for($org, 'organization')->for($member1, 'member')->create([
            'period' => '2026-09', 'amount' => 50000,
        ]);
        Due::factory()->for($org, 'organization')->for($member2, 'member')->create([
            'period' => '2026-09', 'amount' => 30000,
        ]);
        Due::factory()->for($org, 'organization')->for($member1, 'member')->create([
            'period' => '2026-08', 'amount' => 20000,
        ]);

        $singlePeriod = $this->statsFor(DuesReportPage::class, $admin, ['period' => '2026-09']);
        $this->assertSame(2, $singlePeriod['payment_count']);
        $this->assertSame(80000, (int) $singlePeriod['total_amount']);

        $range = $this->statsFor(DuesReportPage::class, $admin, [
            'period_start' => '2026-08',
            'period_end' => '2026-08',
        ]);
        $this->assertSame(1, $range['payment_count']);
        $this->assertSame(20000, (int) $range['total_amount']);
    }

    public function test_dues_report_is_tenant_scoped(): void
    {
        [$adminA, $orgA] = $this->createSbaAdmin();
        [, $orgB] = $this->createSbaAdmin('SBA B');

        $memberA = Member::factory()->for($orgA)->create(['name' => 'Anggota A']);
        $memberB = Member::factory()->for($orgB)->create(['name' => 'Anggota B']);

        Due::factory()->for($orgA, 'organization')->for($memberA, 'member')->create(['amount' => 50000]);
        Due::factory()->for($orgB, 'organization')->for($memberB, 'member')->create(['amount' => 999999]);

        $stats = $this->statsFor(DuesReportPage::class, $adminA);
        $this->assertSame(1, $stats['payment_count']);
        $this->assertSame(50000, (int) $stats['total_amount']);

        Livewire::actingAs($adminA)
            ->test(DuesReportPage::class)
            ->assertSee('Anggota A')
            ->assertDontSee('Anggota B');
    }

    // ---------------------------------------------------------------
    // Attendance report
    // ---------------------------------------------------------------

    public function test_attendance_report_shows_correct_aggregates(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        $member1 = Member::factory()->for($org)->create();
        $member2 = Member::factory()->for($org)->create();
        $member3 = Member::factory()->for($org)->create();

        $event1 = Event::factory()->for($org)->create(['title' => 'Rapat Bulanan', 'event_date' => '2026-09-01']);
        $event2 = Event::factory()->for($org)->create(['title' => 'Pelatihan Anggota', 'event_date' => '2026-09-10']);

        Attendance::factory()->for($org, 'organization')->for($event1, 'event')->for($member1, 'member')->create(['status' => 'hadir']);
        Attendance::factory()->for($org, 'organization')->for($event1, 'event')->for($member2, 'member')->create(['status' => 'izin']);
        Attendance::factory()->for($org, 'organization')->for($event1, 'event')->for($member3, 'member')->create(['status' => 'tidak_hadir']);
        Attendance::factory()->for($org, 'organization')->for($event2, 'event')->for($member1, 'member')->create(['status' => 'hadir']);

        $stats = $this->statsFor(AttendanceReportPage::class, $admin);

        $this->assertSame(2, $stats['event_count']);
        $this->assertSame(4, $stats['participant_count']);
        $this->assertSame(2, $stats['hadir']);
        $this->assertSame(1, $stats['izin']);
        $this->assertSame(1, $stats['tidak_hadir']);
        $this->assertSame(50.0, $stats['attendance_rate']);
    }

    public function test_attendance_report_filters_work_server_side(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        $member1 = Member::factory()->for($org)->create();
        $member2 = Member::factory()->for($org)->create();

        $event1 = Event::factory()->for($org)->create(['title' => 'Rapat Bulanan', 'event_date' => '2026-09-01']);
        $event2 = Event::factory()->for($org)->create(['title' => 'Pelatihan Anggota', 'event_date' => '2026-09-10']);

        Attendance::factory()->for($org, 'organization')->for($event1, 'event')->for($member1, 'member')->create(['status' => 'hadir']);
        Attendance::factory()->for($org, 'organization')->for($event1, 'event')->for($member2, 'member')->create(['status' => 'tidak_hadir']);
        Attendance::factory()->for($org, 'organization')->for($event2, 'event')->for($member1, 'member')->create(['status' => 'hadir']);

        $byEvent = $this->statsFor(AttendanceReportPage::class, $admin, ['event_id' => $event1->id]);
        $this->assertSame(1, $byEvent['event_count']);
        $this->assertSame(2, $byEvent['participant_count']);
        $this->assertSame(1, $byEvent['hadir']);
        $this->assertSame(50.0, $byEvent['attendance_rate']);

        $byDate = $this->statsFor(AttendanceReportPage::class, $admin, [
            'event_date_start' => '2026-09-10',
            'event_date_end' => '2026-09-30',
        ]);
        $this->assertSame(1, $byDate['event_count']);
        $this->assertSame(1, $byDate['participant_count']);
        $this->assertSame(1, $byDate['hadir']);
    }

    public function test_attendance_report_per_event_rekap_is_correct(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        $member1 = Member::factory()->for($org)->create();
        $member2 = Member::factory()->for($org)->create();

        $event1 = Event::factory()->for($org)->create(['title' => 'Rapat Bulanan', 'event_date' => '2026-09-01']);

        Attendance::factory()->for($org, 'organization')->for($event1, 'event')->for($member1, 'member')->create(['status' => 'hadir']);
        Attendance::factory()->for($org, 'organization')->for($event1, 'event')->for($member2, 'member')->create(['status' => 'izin']);

        auth()->login($admin);
        $rekap = (new AttendanceReportPage)->getBreakdownByEvent();

        $this->assertCount(1, $rekap);
        $this->assertSame('Rapat Bulanan', $rekap[0]['title']);
        $this->assertSame(1, $rekap[0]['hadir']);
        $this->assertSame(1, $rekap[0]['izin']);
        $this->assertSame(0, $rekap[0]['tidak_hadir']);
    }

    public function test_attendance_report_rekap_includes_events_without_attendance(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        $event1 = Event::factory()->for($org)->create(['title' => 'Rapat Bulanan', 'event_date' => '2026-09-01']);
        Event::factory()->for($org)->create(['title' => 'Lomba Buruh', 'event_date' => '2026-09-20']);

        Attendance::factory()->for($org, 'organization')->for($event1, 'event')->for(Member::factory()->for($org)->create(), 'member')->create(['status' => 'hadir']);

        auth()->login($admin);
        $rekap = (new AttendanceReportPage)->getBreakdownByEvent();

        $this->assertCount(2, $rekap);
        $this->assertSame('Rapat Bulanan', $rekap[0]['title']);
        $this->assertSame(1, $rekap[0]['hadir']);
        $this->assertSame('Lomba Buruh', $rekap[1]['title']);
        $this->assertSame(0, $rekap[1]['hadir']);
        $this->assertSame(0, $rekap[1]['izin']);
        $this->assertSame(0, $rekap[1]['tidak_hadir']);
    }

    public function test_attendance_report_is_tenant_scoped(): void
    {
        [$adminA, $orgA] = $this->createSbaAdmin();
        [, $orgB] = $this->createSbaAdmin('SBA B');

        $memberA = Member::factory()->for($orgA)->create();
        $memberB = Member::factory()->for($orgB)->create();

        $eventA = Event::factory()->for($orgA)->create(['title' => 'Kegiatan A']);
        $eventB = Event::factory()->for($orgB)->create(['title' => 'Kegiatan B']);

        Attendance::factory()->for($orgA, 'organization')->for($eventA, 'event')->for($memberA, 'member')->create(['status' => 'hadir']);
        Attendance::factory()->for($orgB, 'organization')->for($eventB, 'event')->for($memberB, 'member')->create(['status' => 'hadir']);

        $stats = $this->statsFor(AttendanceReportPage::class, $adminA);
        $this->assertSame(1, $stats['event_count']);
        $this->assertSame(1, $stats['participant_count']);

        Livewire::actingAs($adminA)
            ->test(AttendanceReportPage::class)
            ->assertSee('Kegiatan A')
            ->assertDontSee('Kegiatan B');
    }

    // ---------------------------------------------------------------
    // Complaint report
    // ---------------------------------------------------------------

    public function test_complaint_report_shows_correct_counts(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        Complaint::factory()->for($org, 'organization')->count(1)->create(['status' => 'baru']);
        Complaint::factory()->for($org, 'organization')->count(1)->create(['status' => 'diproses']);
        Complaint::factory()->for($org, 'organization')->count(2)->create(['status' => 'selesai']);

        $stats = $this->statsFor(ComplaintReportPage::class, $admin);

        $this->assertSame(4, $stats['total']);
        $this->assertSame(1, $stats['baru']);
        $this->assertSame(1, $stats['diproses']);
        $this->assertSame(2, $stats['selesai']);
        $this->assertSame(2, $stats['open']);
    }

    public function test_complaint_report_filters_work_server_side(): void
    {
        [$admin, $org] = $this->createSbaAdmin();

        Complaint::factory()->for($org, 'organization')->create([
            'status' => 'baru', 'submitted_at' => '2026-09-01',
        ]);
        Complaint::factory()->for($org, 'organization')->create([
            'status' => 'diproses', 'submitted_at' => '2026-09-05',
        ]);
        Complaint::factory()->for($org, 'organization')->create([
            'status' => 'selesai', 'submitted_at' => '2026-08-20',
        ]);

        $byStatus = $this->statsFor(ComplaintReportPage::class, $admin, ['status' => 'selesai']);
        $this->assertSame(1, $byStatus['total']);
        $this->assertSame(1, $byStatus['selesai']);
        $this->assertSame(0, $byStatus['open']);

        $byDate = $this->statsFor(ComplaintReportPage::class, $admin, [
            'submitted_start' => '2026-09-01',
            'submitted_end' => '2026-09-30',
        ]);
        $this->assertSame(2, $byDate['total']);
        $this->assertSame(1, $byDate['baru']);
        $this->assertSame(1, $byDate['diproses']);
        $this->assertSame(2, $byDate['open']);
    }

    public function test_complaint_report_is_tenant_scoped(): void
    {
        [$adminA, $orgA] = $this->createSbaAdmin();
        [, $orgB] = $this->createSbaAdmin('SBA B');

        Complaint::factory()->for($orgA, 'organization')->create(['title' => 'Pengaduan A', 'status' => 'baru']);
        Complaint::factory()->for($orgB, 'organization')->create(['title' => 'Pengaduan B', 'status' => 'baru']);

        $stats = $this->statsFor(ComplaintReportPage::class, $adminA);
        $this->assertSame(1, $stats['total']);

        Livewire::actingAs($adminA)
            ->test(ComplaintReportPage::class)
            ->assertSee('Pengaduan A')
            ->assertDontSee('Pengaduan B');
    }
}
