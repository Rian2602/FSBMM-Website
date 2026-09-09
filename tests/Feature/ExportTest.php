<?php

namespace Tests\Feature;

use App\Models\Due;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->actingAs($user)->get('/panel-sba/member-report/export');

        $response->assertOk();
        $csv = file_get_contents((string) $response->baseResponse->getFile());

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

    public function test_export_ignores_spoofed_organization_param(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA Alpha');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA Beta');

        Member::factory()->for($orgA)->create(['name' => 'Andi Wijaya']);
        Member::factory()->for($orgB)->create(['name' => 'Budi Santoso']);

        $response = $this->actingAs($sbaA)
            ->get('/panel-sba/member-report/export?organization_id='.$orgB->id)
            ->assertOk();
        $csv = file_get_contents((string) $response->baseResponse->getFile());

        $this->assertStringContainsString('Andi Wijaya', $csv);
        $this->assertStringNotContainsString('Budi Santoso', $csv);
    }

    public function test_xlsx_export_generates_correct_file(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        Member::factory()->for($org)->create(['name' => 'Yoga Pratama', 'gender' => 'P']);

        $response = $this->actingAs($user)
            ->get('/panel-sba/member-report/export?format=xlsx')
            ->assertOk();
        $path = (string) $response->baseResponse->getFile();

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

        $response = $this->actingAs($user)
            ->get('/panel-sba/member-report/export?status=nonaktif')
            ->assertOk();
        $csv = file_get_contents((string) $response->baseResponse->getFile());

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

        $response = $this->actingAs($user)->get('/panel-sba/dues-report/export');

        $response->assertOk();
        $csv = file_get_contents((string) $response->baseResponse->getFile());

        // ponytail: fputcsv doesn't quote short ASCII strings; "Pencatat" has no comma/quote
        $this->assertStringStartsWith('"Nama Anggota",Periode,Nominal,"Tanggal Pembayaran",Pencatat', $csv);
        $this->assertStringContainsString('Yoga Pratama', $csv);
        $this->assertStringContainsString('50000.00', $csv);
        $this->assertStringContainsString('2026-09-05', $csv);
        // ponytail: recorded_by is $user (sba_admin), not the unused Bendahara Beta factory user
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

        $response = $this->actingAs($sbaA)
            ->get('/panel-sba/dues-report/export?organization_id='.$orgB->id)
            ->assertOk();
        $csv = file_get_contents((string) $response->baseResponse->getFile());

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

        $response = $this->actingAs($user)->get('/panel-sba/dues-report/export?format=xlsx')->assertOk();
        $rows = $this->readXlsx((string) $response->baseResponse->getFile());

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

        $response = $this->actingAs($user)->get('/panel-sba/dues-report/export?period=2026-09')->assertOk();
        $csv = file_get_contents((string) $response->baseResponse->getFile());

        $this->assertStringContainsString('Yoga Pratama', $csv);
        $this->assertStringNotContainsString('Siti Rahma', $csv);
    }

    public function test_anonymous_cannot_export_dues(): void
    {
        $this->get('/panel-sba/dues-report/export')->assertRedirect('/panel-sba/login');
    }
}
