<?php

namespace Tests\Feature;

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
}
