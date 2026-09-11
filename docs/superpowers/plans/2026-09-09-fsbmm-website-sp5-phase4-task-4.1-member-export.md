# Task 4.1 — Member Export Implementation Plan

> **For agentic workers:** gunakan `superpowers:subagent-driven-development` (rekomendasi) atau `superpowers:executing-plans`. Step pakai checkbox (`- [ ]`) syntax untuk tracking.

**Goal:** SBA admin dapat meng-export data anggota organisasinya sendiri ke CSV (native `fputcsv`) dan XLSX (openspout streaming) via `GET /panel-sba/member-report/export`, dengan whitelist kolom eksplisit sesuai spec §8.2 dan isolasi tenant server-side.

**Architecture:** Class stateless `App\Exports\MemberExport` menulis file temp (CSV via `fputcsv`, XLSX via openspout `Writer::openToFile`) lalu `response()->download(...)->deleteFileAfterSend(true)` — implementasi cabang "stream response" spec §8.4. Route didaftarkan di `SbaPanelProvider::authenticatedRoutes()` (pola yang sama dengan halaman learning, karena page-level `getRoutes()` tak ada di Filament 3.3.55). Scoping tenant PERSIS dari `auth()->user()->organization_id`; parameter query `organization_id` tidak pernah dibaca (spoof diabaikan).

**Tech Stack:** Laravel 12, Filament 3.3.55, PHP 8.x, `openspout/openspout ^4.0` (XLSX), `fputcsv` native (CSV).

## Global Constraints (dari spec — verbatim)

- Whitelist kolom PERSIS §8.2 (12 kolom, order & label tetap): `name=Nama, nik=NIK, gender=Jenis Kelamin, birthplace=Tempat Lahir, birthdate=Tanggal Lahir, address=Alamat, department=Departemen, position=Jabatan, basic_salary=Upah Dasar, join_date=Tanggal Bergabung, education=Pendidikan, status=Status`. Kolom DB baru TIDAK otomatis masuk export.
- Dilarang export (§8.3): password/token/session, secret sistem, data SBA lain.
- JANGAN gunakan NIK sebagai filename (§8.4).
- Export bukan public asset; file temp auto-hapus setelah dikirim (§8.4 cabang stream).
- Isolasi tenant (§8.5): export SBA A hanya berisi data SBA A; tes harus buktikan nama anggota SBA B tidak ada di output.
- Authorization server-side, bukan hanya tombol UI (§8.6): `sba_admin` hanya export organisasinya sendiri.
- Bahasa produk: Indonesia.
- Route `/panel-sba/member-report/export` wajib di `authenticatedRoutes()` (auth panel → anonymous redirect `/panel-sba/login`).
- Tidak ada disk config baru.
- Deviation anotasi `(** executed ... **)` di plan kanonik — append, jangan rewrite.

---

### Task 1: Tes export yang gagal dulu (ExportTest)

**Files:**
- Create: `tests/Feature/ExportTest.php`
- Consumes: `App\Models\Member`, `App\Models\Organization`, `App\Models\User`, `User::factory()->sbaAdmin($org)`
- Produces: baseline merah terbukti (route belum ada → 404).

- [ ] **Step 1: Tulis tes**

```php
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
        $csv = $response->getContent();

        $this->assertStringStartsWith(
            "Nama,NIK,Jenis Kelamin,Tempat Lahir,Tanggal Lahir,Alamat,Departemen,Jabatan,Upah Dasar,Tanggal Bergabung,Pendidikan,Status",
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

        $csv = $this->actingAs($sbaA)
            ->get('/panel-sba/member-report/export?organization_id='.$orgB->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Andi Wijaya', $csv);
        $this->assertStringNotContainsString('Budi Santoso', $csv);
    }

    public function test_xlsx_export_generates_correct_file(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA Alpha');
        Member::factory()->for($org)->create(['name' => 'Yoga Pratama', 'gender' => 'P']);

        $xlsx = $this->actingAs($user)
            ->get('/panel-sba/member-report/export?format=xlsx')
            ->assertOk()
            ->getContent();

        $path = tempnam(sys_get_temp_dir(), 'fsbmm_xlsx_');
        file_put_contents($path, $xlsx);

        $reader = new Reader();
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

        $csv = $this->actingAs($user)
            ->get('/panel-sba/member-report/export?status=nonaktif')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Nonaktif Member', $csv);
        $this->assertStringNotContainsString('Aktif Member', $csv);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter ExportTest`
Expected: 4 failed — `assertOk()` gagal karena route belum ada (404). Baseline merah yang benar.

---

### Task 2: Implementasi MemberExport + route

**Files:**
- Create: `app/Exports/MemberExport.php`
- Modify: `app/Providers/Filament/SbaPanelProvider.php` (blok `authenticatedRoutes()`)
- Test: `tests/Feature/ExportTest.php` (Task 1)

**Interfaces:**
- Consumes: `App\Models\Member` (12 field), filter keys sama dengan `MemberReportPage::getFilteredQuery()` (`status`, `department`, `position`, `education`, `gender`, `join_date_start`, `join_date_end`).
- Produces: `MemberExport::streamFor(int $organizationId, array $filters, string $format = 'csv'): BinaryFileResponse` — digunakan route closure Task 2 dan dikonsumsi penambahan test/export lain di Phase 4.

- [ ] **Step 1: Tulis `app/Exports/MemberExport.php`**

```php
<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MemberExport
{
    public const COLUMNS = [
        'name' => 'Nama',
        'nik' => 'NIK',
        'gender' => 'Jenis Kelamin',
        'birthplace' => 'Tempat Lahir',
        'birthdate' => 'Tanggal Lahir',
        'address' => 'Alamat',
        'department' => 'Departemen',
        'position' => 'Jabatan',
        'basic_salary' => 'Upah Dasar',
        'join_date' => 'Tanggal Bergabung',
        'education' => 'Pendidikan',
        'status' => 'Status',
    ];

    public static function streamFor(int $organizationId, array $filters, string $format = 'csv'): BinaryFileResponse
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';
        $path = sys_get_temp_dir().'/fsbmm_'.Str::random(8).'.'.$format;
        $query = static::scopedQuery($organizationId, $filters);

        $format === 'xlsx' ? static::writeXlsx($query, $path) : static::writeCsv($query, $path);

        return response()
            ->download($path, sprintf('anggota-%s-%s.%s', $organizationId, now()->format('Y-m-d'), $format))
            ->deleteFileAfterSend(true);
    }

    private static function scopedQuery(int $organizationId, array $filters): Builder
    {
        $query = Member::query()->where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['department'])) {
            $query->where('department', 'like', '%'.$filters['department'].'%');
        }
        if (! empty($filters['position'])) {
            $query->where('position', 'like', '%'.$filters['position'].'%');
        }
        if (! empty($filters['education'])) {
            $query->where('education', 'like', '%'.$filters['education'].'%');
        }
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if (! empty($filters['join_date_start'])) {
            $query->whereDate('join_date', '>=', $filters['join_date_start']);
        }
        if (! empty($filters['join_date_end'])) {
            $query->whereDate('join_date', '<=', $filters['join_date_end']);
        }

        return $query->orderBy('id');
    }

    private static function writeCsv(Builder $query, string $path): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, array_values(self::COLUMNS));
        static::rows($query)->each(function (Member $member) use ($handle): void {
            fputcsv($handle, static::row($member));
        });
        fclose($handle);
    }

    private static function writeXlsx(Builder $query, string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(self::COLUMNS)));
        static::rows($query)->each(function (Member $member) use ($writer): void {
            $writer->addRow(Row::fromValues(static::row($member)));
        });
        $writer->close();
    }

    private static function rows(Builder $query): \Illuminate\Support\LazyCollection
    {
        return $query->cursor();
    }

    private static function row(Member $member): array
    {
        return [
            $member->name,
            $member->nik,
            match ($member->gender) {
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
                default => $member->gender,
            },
            $member->birthplace,
            $member->birthdate?->format('Y-m-d'),
            $member->address,
            $member->department,
            $member->position,
            $member->basic_salary,
            $member->join_date?->format('Y-m-d'),
            $member->education,
            $member->status,
        ];
    }
}
```

- [ ] **Step 2: Daftarkan route di `SbaPanelProvider.php` blok `authenticatedRoutes()`**, plus 2 import (`use App\Exports\MemberExport;` dan `use Symfony\Component\HttpFoundation\BinaryFileResponse;`):

```php
Route::get('/member-report/export', function (): BinaryFileResponse {
    return MemberExport::streamFor(
        auth()->user()->organization_id,
        request()->only(['status', 'department', 'position', 'education', 'gender', 'join_date_start', 'join_date_end']),
        request()->string('format', 'csv')->toString(),
    );
})->name('member-report.export');
```

Catatan: parameter query `organization_id` tidak ikut diambil → spoof diabaikan (spec §8.5). Route di `authenticatedRoutes()` otomatis di belakang middleware auth panel → anonim redirect `/panel-sba/login`.

- [ ] **Step 3: Jalankan `php artisan test --filter ExportTest`**, Expected: 4 passed. Jika `->getContent()` pada BinaryFileResponse kosong, gunakan `(string) $response->getBaseResponse()->getFile()` sebagai fallback di test.

- [ ] **Step 4: Commit**

```bash
git add app/Exports/MemberExport.php app/Providers/Filament/SbaPanelProvider.php
git commit -m "feat(sp5): member export CSV/XLSX with tenant scoping + whitelist"
```

---

### Task 3: Update SecurityTest + verifikasi expected-failure ke passing

**Files:**
- Modify: `tests/Feature/Sp5SecurityTest.php` (`test_sba_a_cannot_export_sba_b_data`)

Alasan: asersi saat ini `assertStatus(404)` ("assuming route doesn't exist yet"). Route kini ada → asersi lama pasti gagal. Saatnya di-update (AGENTS.md: "don't fix them early" — Phase 4 adalah waktunya). `test_anonymous_cannot_access_exports` TIDAK perlu diubah — akan otomatis lulus karena route ada + auth panel redirect.

- [ ] **Step 1: Ganti isi `test_sba_a_cannot_export_sba_b_data`**

```php
public function test_sba_a_cannot_export_sba_b_data(): void
{
    [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
    [$sbaB, $orgB] = $this->createSbaAdmin('SBA B');

    Member::factory()->for($orgA)->create(['name' => 'Member SBA A']);
    Member::factory()->for($orgB)->create(['name' => 'Member SBA B']);

    $csv = $this->actingAs($sbaA)
        ->get('/panel-sba/member-report/export?organization_id='.$orgB->id)
        ->assertOk()
        ->getContent();

    $this->assertStringContainsString('Member SBA A', $csv);
    $this->assertStringNotContainsString('Member SBA B', $csv);
}
```

- [ ] **Step 2: Jalankan `php artisan test --filter 'Sp5SecurityTest|ExportTest'`**

Expected: `ExportTest` 4 passed + `Sp5SecurityTest` 11 passed. Sisa 1 failure yang MASIH benar: `test_anonymous_can_access_card_verification` (Phase 6, jangan disentuh).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Sp5SecurityTest.php
git commit -m "test(sp5): assert export tenant isolation + spoofed org ignored"
```

---

### Task 4: Anotasi kanonik + verifikasi penuh

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` (Task 4.1 checklist + item Task 4.6 yang tercakup)

- [ ] **Step 1: Centang checklist Task 4.1** — `- [x]` pada 4 item (whitelist, tenant-scoped, CSV, XLSX).
- [ ] **Step 2: Centang item Task 4.6 yang tercakup Task 4.1** — `- [x]`: CSV member export, XLSX member export, export correct columns (whitelist), export SBA A doesn't contain SBA B, anonymous cannot export, SBA admin cannot export other SBA. (Dues/attendance/complaint export tests TETAP kosong → Tasks 4.2–4.4.)
- [ ] **Step 3: Append anotasi deviation di bawah Task 4.1** (jangan rewrite history):

```markdown
(** executed @2026-09-09: stream branch spec §8.4 (
`MemberExport::streamFor()` → `response()->download(...)->deleteFileAfterSend(true)`),
bukan private-disk + TTL — file temp auto-hapus, tanpa job cleanup; flow storage
Task 4.5 tetap untuk download kartu Phase 6. Tenancy murni dari
`auth()->user()->organization_id`; query param `organization_id` sengaja tidak
dibaca (spoof diabaikan) — `test_sba_a_cannot_export_sba_b_data` di
`Sp5SecurityTest` di-update sesuai perilaku riil (sebelumnya assert 404 "route
belum ada"). Route di `SbaPanelProvider::authenticatedRoutes()` + middleware auth
panel → `test_anonymous_cannot_access_exports` otomatis hijau. XLSX via openspout
`openToFile` (bukan `openToBrowser` — output tak terbaca test client Laravel).
`gender` diekspor sebagai label (`L`→Laki-laki, `P`→Perempuan); tanggal
`Y-m-d`; `basic_salary` string decimal. Filename `anggota-{orgId}-{Ymd}`
(tanpa NIK, §8.4). **)
```

- [ ] **Step 4: Verifikasi penuh**

```bash
composer test          # expected: ~285 passed / 1 failed (hanya card verification Phase 6)
vendor/bin/pint        # clean (taruh di akhir)
npm run build          # harus clean (tidak ada perubahan frontend)
```

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md
git commit -m "docs(sp5): task 4.1 member export annotations + checklist"
```

---

## Self-Review

- **Spec coverage:** §8.1 (CSV fputcsv Task 2; XLSX openspout Task 2), §8.2 whitelist 12 kolom exact, §8.3 dilarang (whitelist tetap + tes negatif `created_at`/`organization_id`), §8.4 stream + auto-delete + bukan NIK filename, §8.5 tenant isolation (Task 1 test 2 + Task 3), §8.6 auth server-side. Item Task 4.6 dues/attendance/complaint sengaja didefer ke Tasks 4.2–4.4.
- **Placeholder scan:** semua step berisi kode nyata, tidak ada TBD.
- **Type consistency:** `streamFor(int, array, string): BinaryFileResponse` konsisten (implementasi + route closure). `rows()` mengembalikan `LazyCollection` (cursor). Filter keys sama dengan `MemberReportPage::getFilteredQuery()`.