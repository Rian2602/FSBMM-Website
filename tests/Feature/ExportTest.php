<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private function createSbaAdmin(string $orgName): array
    {
        $org = Organization::factory()->create(['name' => $orgName]);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    /**
     * The panel export route now redirects (302) to a signed, private-disk
     * download URL rather than streaming directly. Follow that hop the way
     * a real browser would. Caller must already be `actingAs()` the correct
     * session — this only follows the redirect, it doesn't authenticate.
     */
    private function followExport(string $url): TestResponse
    {
        $redirect = $this->get($url);
        $redirect->assertStatus(302);

        return $this->get($redirect->headers->get('Location'));
    }

    private function readXlsx(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn (Cell $cell) => $cell->getValue(), $row->getCells());
            }
        }
        $reader->close();
        unlink($path);

        return $rows;
    }

    public function test_csv_export_has_exact_whitelist_header_and_rows(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        Member::factory()->for($org)->create([
            'name' => 'Yoga Pratama',
            'nik' => '3201234567890001',
            'gender' => 'L',
            'basic_salary' => 3500000,
        ]);

        $download = $this->actingAs($user)->followExport('/panel-sba/member-report/export');

        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringStartsWith(
            'Nama,NIK,"Jenis Kelamin","Tempat Lahir","Tanggal Lahir",Alamat,Departemen,Jabatan,"Upah Dasar","Tanggal Bergabung",Pendidikan,Status',
            $csv,
        );
        $this->assertStringContainsString('Yoga Pratama', $csv);
        $this->assertStringContainsString('3201234567890001', $csv);
        $this->assertStringContainsString('Laki-laki', $csv);
        $this->assertStringContainsString('3500000.00', $csv);
        $this->assertStringNotContainsString('created_at,', $csv);
        $this->assertStringNotContainsString('organization_id,', $csv);
    }

    public function test_export_with_no_data_returns_header_only(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Kosong');

        $download = $this->actingAs($user)->followExport('/panel-sba/member-report/export');
        $download->assertOk();

        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertSame(
            'Nama,NIK,"Jenis Kelamin","Tempat Lahir","Tanggal Lahir",Alamat,Departemen,Jabatan,"Upah Dasar","Tanggal Bergabung",Pendidikan,Status',
            trim($csv),
        );
    }

    public function test_export_ignores_spoofed_organization_param(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA Alpha');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA Beta');

        Member::factory()->for($orgA)->create(['name' => 'Andi Wijaya']);
        Member::factory()->for($orgB)->create(['name' => 'Budi Santoso']);

        $download = $this->actingAs($sbaA)
            ->followExport('/panel-sba/member-report/export?organization_id='.$orgB->id);
        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringContainsString('Andi Wijaya', $csv);
        $this->assertStringNotContainsString('Budi Santoso', $csv);
    }

    public function test_xlsx_export_generates_correct_file(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        Member::factory()->for($org)->create(['name' => 'Yoga Pratama', 'gender' => 'P']);

        $download = $this->actingAs($user)->followExport('/panel-sba/member-report/export?format=xlsx');
        $download->assertOk();
        $rows = $this->readXlsx((string) $download->baseResponse->getFile());

        $this->assertCount(2, $rows);
        $this->assertSame('Nama', $rows[0][0] ?? null);
        $this->assertSame('Yoga Pratama', $rows[1][0] ?? null);
        $this->assertSame('Perempuan', $rows[1][2] ?? null);
    }

    public function test_export_applies_report_filters(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        Member::factory()->for($org)->create(['name' => 'Aktif Member', 'status' => 'aktif']);
        Member::factory()->for($org)->create(['name' => 'Nonaktif Member', 'status' => 'nonaktif']);

        $download = $this->actingAs($user)->followExport('/panel-sba/member-report/export?status=nonaktif');
        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringContainsString('Nonaktif Member', $csv);
        $this->assertStringNotContainsString('Aktif Member', $csv);
    }

    public function test_csv_dues_export_has_column_whitelist_and_content(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        $member = Member::factory()->for($org)->create(['name' => 'Yoga Pratama']);
        Due::factory()->for($org)->for($member, 'member')->create([
            'period' => '2026-09', 'amount' => 50000, 'paid_at' => '2026-09-05',
            'recorded_by' => $user->id,
        ]);

        $download = $this->actingAs($user)->followExport('/panel-sba/dues-report/export');

        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        // ponytail: fputcsv doesn't quote short ASCII strings; "Pencatat" has no comma/quote
        $this->assertStringStartsWith('"Nama Anggota",Periode,Nominal,"Tanggal Pembayaran",Pencatat', $csv);
        $this->assertStringContainsString('Yoga Pratama', $csv);
        $this->assertStringContainsString('50000.00', $csv);
        $this->assertStringContainsString('2026-09-05', $csv);
        $this->assertStringContainsString($user->name, $csv);
        $this->assertStringNotContainsString('member_id,', $csv);
        $this->assertStringNotContainsString('organization_id,', $csv);
    }

    public function test_dues_export_ignores_spoofed_organization_param(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA Alpha');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA Beta');

        $memberA = Member::factory()->for($orgA)->create(['name' => 'Andi Wijaya']);
        $memberB = Member::factory()->for($orgB)->create(['name' => 'Budi Santoso']);
        Due::factory()->for($orgA)->for($memberA, 'member')->create(['period' => '2026-09']);
        Due::factory()->for($orgB)->for($memberB, 'member')->create(['period' => '2026-09']);

        $download = $this->actingAs($sbaA)
            ->followExport('/panel-sba/dues-report/export?organization_id='.$orgB->id);
        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringContainsString('Andi Wijaya', $csv);
        $this->assertStringNotContainsString('Budi Santoso', $csv);
    }

    public function test_xlsx_dues_export_generates_correct_file(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        $member = Member::factory()->for($org)->create(['name' => 'Yoga Pratama']);
        Due::factory()->for($org)->for($member, 'member')->create([
            'period' => '2026-09', 'amount' => 50000, 'paid_at' => '2026-09-05', 'recorded_by' => $user->id,
        ]);

        $download = $this->actingAs($user)->followExport('/panel-sba/dues-report/export?format=xlsx');
        $download->assertOk();
        $rows = $this->readXlsx((string) $download->baseResponse->getFile());

        $this->assertCount(2, $rows);
        $this->assertSame(['Nama Anggota', 'Periode', 'Nominal', 'Tanggal Pembayaran', 'Pencatat'], $rows[0]);
        $this->assertSame(['Yoga Pratama', '2026-09', '50000.00', '2026-09-05', $user->name], $rows[1]);
    }

    public function test_dues_export_applies_period_filters(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        $yoga = Member::factory()->for($org)->create(['name' => 'Yoga Pratama']);
        $siti = Member::factory()->for($org)->create(['name' => 'Siti Rahma']);
        Due::factory()->for($org)->for($yoga, 'member')->create(['period' => '2026-09']);
        Due::factory()->for($org)->for($siti, 'member')->create(['period' => '2026-08']);

        $download = $this->actingAs($user)->followExport('/panel-sba/dues-report/export?period=2026-09');
        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringContainsString('Yoga Pratama', $csv);
        $this->assertStringNotContainsString('Siti Rahma', $csv);
    }

    public function test_anonymous_cannot_export_dues(): void
    {
        $this->get('/panel-sba/dues-report/export')->assertRedirect('/panel-sba/login');
    }

    public function test_csv_attendance_export_has_exact_whitelist_header_and_rows(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        $member = Member::factory()->for($org)->create(['name' => 'Yoga Pratama']);
        $event = Event::factory()->for($org)->create(['title' => 'Seminar Nasional', 'event_date' => '2026-09-05']);
        Attendance::factory()->for($org)->for($event, 'event')->for($member, 'member')->create([
            'status' => 'hadir', 'note' => 'Peserta aktif',
        ]);

        $download = $this->actingAs($user)->followExport('/panel-sba/attendance-report/export');

        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        // ponytail: fputcsv quotes only cells with spaces; "Nama Anggota" is the only spaced header
        $this->assertStringStartsWith('"Nama Anggota",Kegiatan,Tanggal,Status,Catatan', $csv);
        $this->assertStringContainsString('Yoga Pratama', $csv);
        $this->assertStringContainsString('Seminar Nasional', $csv);
        $this->assertStringContainsString('2026-09-05', $csv);
        $this->assertStringContainsString('Hadir', $csv);
        $this->assertStringContainsString('Peserta aktif', $csv);
        $this->assertStringNotContainsString('member_id,', $csv);
        $this->assertStringNotContainsString('event_id,', $csv);
        $this->assertStringNotContainsString('organization_id,', $csv);
    }

    public function test_attendance_export_ignores_spoofed_organization_param(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA Alpha');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA Beta');

        $memberA = Member::factory()->for($orgA)->create(['name' => 'Andi Wijaya']);
        $memberB = Member::factory()->for($orgB)->create(['name' => 'Budi Santoso']);
        $eventA = Event::factory()->for($orgA)->create(['title' => 'Rapat A', 'event_date' => '2026-09-01']);
        $eventB = Event::factory()->for($orgB)->create(['title' => 'Rapat B', 'event_date' => '2026-09-02']);
        Attendance::factory()->for($orgA)->for($eventA, 'event')->for($memberA, 'member')->create();
        Attendance::factory()->for($orgB)->for($eventB, 'event')->for($memberB, 'member')->create();

        $download = $this->actingAs($sbaA)
            ->followExport('/panel-sba/attendance-report/export?organization_id='.$orgB->id);
        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringContainsString('Andi Wijaya', $csv);
        $this->assertStringNotContainsString('Budi Santoso', $csv);
    }

    public function test_xlsx_attendance_export_generates_correct_file(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        $member = Member::factory()->for($org)->create(['name' => 'Yoga Pratama']);
        $event = Event::factory()->for($org)->create(['title' => 'Seminar Nasional', 'event_date' => '2026-09-05']);
        Attendance::factory()->for($org)->for($event, 'event')->for($member, 'member')->create([
            'status' => 'izin', 'note' => 'Izin sakit',
        ]);

        $download = $this->actingAs($user)->followExport('/panel-sba/attendance-report/export?format=xlsx');
        $download->assertOk();
        $rows = $this->readXlsx((string) $download->baseResponse->getFile());

        $this->assertCount(2, $rows);
        $this->assertSame(['Nama Anggota', 'Kegiatan', 'Tanggal', 'Status', 'Catatan'], $rows[0]);
        $this->assertSame(['Yoga Pratama', 'Seminar Nasional', '2026-09-05', 'Izin', 'Izin sakit'], $rows[1]);
    }

    public function test_attendance_export_applies_filters(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        $memberA = Member::factory()->for($org)->create(['name' => 'Anggota A']);
        $memberB = Member::factory()->for($org)->create(['name' => 'Anggota B']);
        $eventSep = Event::factory()->for($org)->create(['title' => 'Event September', 'event_date' => '2026-09-10']);
        $eventAgs = Event::factory()->for($org)->create(['title' => 'Event Agustus', 'event_date' => '2026-08-10']);
        Attendance::factory()->for($org)->for($eventSep, 'event')->for($memberA, 'member')->create();
        Attendance::factory()->for($org)->for($eventAgs, 'event')->for($memberB, 'member')->create();

        $download = $this->actingAs($user)
            ->followExport('/panel-sba/attendance-report/export?event_id='.$eventSep->id);
        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringContainsString('Event September', $csv);
        $this->assertStringContainsString('Anggota A', $csv);
        $this->assertStringNotContainsString('Anggota B', $csv);
    }

    public function test_anonymous_cannot_export_attendance(): void
    {
        $this->get('/panel-sba/attendance-report/export')->assertRedirect('/panel-sba/login');
    }

    public function test_csv_complaint_export_has_exact_whitelist_header_and_rows(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        Complaint::factory()->for($org)->create([
            'title' => 'Fasilitas rusak', 'status' => 'baru',
            'submitted_at' => '2026-09-05', 'resolved_at' => null,
            'description' => 'AC kantor mati sejak seminggu',
        ]);

        $download = $this->actingAs($user)->followExport('/panel-sba/complaint-report/export');

        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        // ponytail: fputcsv quotes only spaced cells
        $this->assertStringStartsWith('ID,Judul,Status,"Tanggal Pengajuan","Tanggal Penyelesaian"', $csv);
        $this->assertStringContainsString('Fasilitas rusak', $csv);
        $this->assertStringContainsString('Baru', $csv);
        $this->assertStringContainsString('2026-09-05', $csv);
        $this->assertStringNotContainsString('Deskripsi', $csv);
        $this->assertStringNotContainsString('AC kantor mati sejak seminggu', $csv);
        $this->assertStringNotContainsString('member_id,', $csv);
        $this->assertStringNotContainsString('organization_id,', $csv);
    }

    public function test_complaint_export_includes_description_only_with_explicit_flag(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        Complaint::factory()->for($org)->create([
            'title' => 'Fasilitas rusak', 'submitted_at' => '2026-09-05',
            'description' => 'AC kantor mati sejak seminggu',
        ]);

        $download = $this->actingAs($user)
            ->followExport('/panel-sba/complaint-report/export?include_description=1');
        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringContainsString('Deskripsi', $csv);
        $this->assertStringContainsString('AC kantor mati sejak seminggu', $csv);
    }

    public function test_complaint_export_ignores_spoofed_organization_param(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA Alpha');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA Beta');

        Complaint::factory()->for($orgA)->create(['title' => 'Keluhan A']);
        Complaint::factory()->for($orgB)->create(['title' => 'Keluhan B']);

        $download = $this->actingAs($sbaA)
            ->followExport('/panel-sba/complaint-report/export?organization_id='.$orgB->id);
        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringContainsString('Keluhan A', $csv);
        $this->assertStringNotContainsString('Keluhan B', $csv);
    }

    public function test_xlsx_complaint_export_generates_correct_file(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        $complaint = Complaint::factory()->for($org)->create([
            'title' => 'Fasilitas rusak', 'status' => 'selesai',
            'submitted_at' => '2026-09-05', 'resolved_at' => '2026-09-08',
        ]);

        $download = $this->actingAs($user)->followExport('/panel-sba/complaint-report/export?format=xlsx');
        $download->assertOk();
        $rows = $this->readXlsx((string) $download->baseResponse->getFile());

        $this->assertCount(2, $rows);
        $this->assertSame(['ID', 'Judul', 'Status', 'Tanggal Pengajuan', 'Tanggal Penyelesaian'], $rows[0]);
        $this->assertSame([$complaint->id, 'Fasilitas rusak', 'Selesai', '2026-09-05', '2026-09-08'], $rows[1]);
    }

    public function test_complaint_export_applies_submitted_date_filter(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        Complaint::factory()->for($org)->create(['title' => 'Keluhan September', 'submitted_at' => '2026-09-10']);
        Complaint::factory()->for($org)->create(['title' => 'Keluhan Agustus', 'submitted_at' => '2026-08-10']);

        $download = $this->actingAs($user)
            ->followExport('/panel-sba/complaint-report/export?submitted_start=2026-09-01');
        $download->assertOk();
        $csv = file_get_contents((string) $download->baseResponse->getFile());

        $this->assertStringContainsString('Keluhan September', $csv);
        $this->assertStringNotContainsString('Keluhan Agustus', $csv);
    }

    public function test_anonymous_cannot_export_complaints(): void
    {
        $this->get('/panel-sba/complaint-report/export')->assertRedirect('/panel-sba/login');
    }

    // ── Task 4.5: export storage rewire (private disk + signed URL + TTL) ──

    public function test_export_served_via_signed_url_only(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');

        $this->actingAs($user)->get('/panel-sba/member-report/export')->assertStatus(302);

        // Hand-built (unsigned) URL to the exact same file — no valid signature.
        $this->get(route('exports.download', [
            'file' => 'exports/member-'.$org->id.'-'.now()->format('Y-m-d').'.csv',
        ]))->assertForbidden();
    }

    public function test_export_expired_signed_url_is_rejected(): void
    {
        $url = URL::temporarySignedRoute('exports.download', now()->subHour(), ['file' => 'exports/dummy.csv']);

        $this->get($url)->assertForbidden();
    }

    public function test_export_stored_in_private_disk_not_public(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');

        $this->actingAs($user)->get('/panel-sba/member-report/export');

        $this->assertNotEmpty(Storage::disk('local')->files('exports'));
        $this->assertEmpty(Storage::disk('public')->files('exports'));
        $this->assertNotSame(Storage::disk('public')->path(''), Storage::disk('local')->path(''));
        $this->assertStringContainsString('private', Storage::disk('local')->path(''));
    }

    public function test_export_download_returns_404_after_ttl_expiry(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        $url = $this->actingAs($user)->get('/panel-sba/member-report/export')->headers->get('Location');

        $files = Storage::disk('local')->files('exports');
        touch(Storage::disk('local')->path($files[0]), now()->subHours(2)->timestamp);

        $this->get($url)->assertNotFound();
    }

    public function test_export_storage_sweeps_expired_files_on_next_generate(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA Alpha');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA Beta');

        $this->actingAs($sbaA)->get('/panel-sba/member-report/export');
        $fileA = Storage::disk('local')->files('exports')[0];
        touch(Storage::disk('local')->path($fileA), now()->subHours(2)->timestamp);

        $this->actingAs($sbaB)->get('/panel-sba/member-report/export');

        $this->assertTrue(Storage::disk('local')->missing($fileA));
    }

    // ── PII-sensitivity hardening beyond the base plan: a valid signature
    // alone must not be enough to read another SBA's export. Unlike
    // EresourceController (institutional PDFs, no PII), these files carry
    // individual member data — the download endpoint additionally requires
    // an authenticated session whose organization matches the file. ──

    public function test_signed_export_url_rejects_different_organization_session(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA Alpha');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA Beta');

        Member::factory()->for($orgA)->create(['name' => 'Rahasia SBA Alpha']);

        $location = $this->actingAs($sbaA)
            ->get('/panel-sba/member-report/export')
            ->headers->get('Location');

        // The signature is genuinely valid — SBA B simply isn't the
        // organization that generated it.
        $this->actingAs($sbaB)->get($location)->assertForbidden();
    }

    public function test_signed_export_url_rejects_anonymous_download(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA Alpha');

        $location = $this->actingAs($sbaA)
            ->get('/panel-sba/member-report/export')
            ->headers->get('Location');

        auth()->logout();

        $this->get($location)->assertForbidden();
    }
}
