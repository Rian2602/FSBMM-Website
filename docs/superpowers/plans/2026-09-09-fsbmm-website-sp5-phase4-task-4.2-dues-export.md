# Task 4.2 — Dues Export Implementation Plan

> **For agentic workers:** gunakan `superpowers:subagent-driven-development`. Step pakai checkbox (`- [ ]`) untuk tracking.

**Goal:** SBA admin meng-export data iuran organisasinya via `GET /panel-sba/dues-report/export` (CSV native `fputcsv` + XLSX openspout), 5 kolom whitelist, tenant-scoped server-side, spoof `organization_id` diabaikan.

**Architecture:** Meniru `MemberExport` (Task 4.1): class stateless `App\Exports\DuesExport` menulis file temp lalu `response()->download(...)->deleteFileAfterSend(true)`. Route di `SbaPanelProvider::authenticatedRoutes()` (pola Filament 3.3.55). Tenancy murni `auth()->user()->organization_id`; query param `organization_id` tidak pernah dibaca.

**Tech Stack:** Laravel 12, Filament 3.3.55, `openspout ^4.0` (XLSX), `fputcsv` native (CSV).

## Global Constraints (verbatim spec §8 + Task 4.2)

- Whitelist Task 4.2 (5 kolom): **nama anggota** (`member.name`), **periode** (`period`), **nominal** (`amount`), **tanggal pembayaran** (`paid_at`), **pencatat** (`recordedBy.name`). Order & label tetap. Kolom DB lain TIDAK ikut export (§8.2).
- Dilarang export (§8.3): password/token/session, secret sistem, data SBA lain.
- Filename TANPA NIK/identitas tunggal (§8.4) — `iuran-{orgId}-{Y-m-d}.{ext}`.
- File temp auto-hapus setelah kirim; bukan public asset (§8.4 stream branch).
- Isolasi tenant (§8.5): export SBA A hanya berisi iuran SBA A; tes buktikan baris SBA B absent.
- Auth server-side (§8.6); route di belakang auth panel → anonim redirect `/panel-sba/login`.
- Bahasa produk: Indonesia.
- Anotasi `(** executed ... **)` append, jangan rewrite.
- Deviasi disetujui user: **duplikasi** skeleton `MemberExport` (konsolidasi helper bersama dijadwalkan Task 4.3, exporter ke-3).
- Commit langsung di master; staging presisi agar WIP Phase-2 di `SbaPanelProvider.php` tidak ikut ter-commit.

---

### Task 1: Tes dues export yang gagal dulu (di `tests/Feature/ExportTest.php`)

**Files:**
- Modify: `tests/Feature/ExportTest.php` (tambah 5 method + helper `readXlsx` + import `use App\Models\Due;`; jangan ubah tes yang ada)

- [ ] **Step 1: Tulis 5 tes + helper**

```php
public function test_csv_dues_export_has_column_whitelist_and_content(): void
{
    [$user, $org] = $this->createSbaAdmin('SBA Alpha');
    $member = Member::factory()->for($org)->create(['name' => 'Yoga Pratama']);
    User::factory()->create(['name' => 'Bendahara Beta']);
    Due::factory()->for($org)->for($member, 'member')->create([
        'period' => '2026-09', 'amount' => 50000, 'paid_at' => '2026-09-05',
        'recorded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get('/panel-sba/dues-report/export');

    $response->assertOk();
    $csv = file_get_contents((string) $response->baseResponse->getFile());

    $this->assertStringStartsWith('"Nama Anggota",Periode,Nominal,"Tanggal Pembayaran","Pencatat"', $csv);
    $this->assertStringContainsString('Yoga Pratama', $csv);
    $this->assertStringContainsString('50000.00', $csv);
    $this->assertStringContainsString('2026-09-05', $csv);
    $this->assertStringContainsString('Bendahara Beta', $csv);
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
```

Helper privat di class (reuse untuk 4.3/4.4):

```php
private function readXlsx(string $path): array
{
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

    return $rows;
}
```

Plus import `use App\Models\Due;`.

- [ ] **Step 2: Jalankan `php artisan test --filter ExportTest`**

Expected: 6 failed — 5 tes baru gagal (route `/panel-sba/dues-report/export` 404), 4 tes lama tetap pass. Baseline merah benar.

---

### Task 2: `DuesExport` + route

**Files:**
- Create: `app/Exports/DuesExport.php`
- Modify: `app/Providers/Filament/SbaPanelProvider.php` (import `use App\Exports\DuesExport;` + route di `authenticatedRoutes()`)

**Interfaces:**
- Consumes: `Due` (5 field + relasi `member`, `recordedBy`), filter keys `period`, `period_start`, `period_end` (identik `DuesReportPage::getFilteredQuery()`).
- Produces: `DuesExport::streamFor(int $organizationId, array $filters, string $format = 'csv'): BinaryFileResponse`.

- [ ] **Step 1: Tulis `app/Exports/DuesExport.php`**

```php
<?php

namespace App\Exports;

use App\Models\Due;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DuesExport
{
    public const COLUMNS = [
        'member.name' => 'Nama Anggota',
        'period' => 'Periode',
        'amount' => 'Nominal',
        'paid_at' => 'Tanggal Pembayaran',
        'recordedBy.name' => 'Pencatat',
    ];

    public static function streamFor(int $organizationId, array $filters, string $format = 'csv'): BinaryFileResponse
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';
        $path = sys_get_temp_dir().'/fsbmm_'.Str::random(8).'.'.$format;
        $query = static::scopedQuery($organizationId, $filters);

        $format === 'xlsx' ? static::writeXlsx($query, $path) : static::writeCsv($query, $path);

        return response()
            ->download($path, sprintf('iuran-%s-%s.%s', $organizationId, now()->format('Y-m-d'), $format))
            ->deleteFileAfterSend(true);
    }

    private static function scopedQuery(int $organizationId, array $filters): Builder
    {
        $query = Due::query()->with('member', 'recordedBy')->where('organization_id', $organizationId);

        if (! empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }
        if (! empty($filters['period_start'])) {
            $query->where('period', '>=', $filters['period_start']);
        }
        if (! empty($filters['period_end'])) {
            $query->where('period', '<=', $filters['period_end']);
        }

        return $query->orderBy('id');
    }

    private static function writeCsv(Builder $query, string $path): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, array_values(self::COLUMNS));
        static::rows($query)->each(function (Due $due) use ($handle): void {
            fputcsv($handle, static::row($due));
        });
        fclose($handle);
    }

    private static function writeXlsx(Builder $query, string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(self::COLUMNS)));
        static::rows($query)->each(function (Due $due) use ($writer): void {
            $writer->addRow(Row::fromValues(static::row($due)));
        });
        $writer->close();
    }

    private static function rows(Builder $query): \Illuminate\Support\LazyCollection
    {
        return $query->cursor();
    }

    private static function row(Due $due): array
    {
        return [
            $due->member?->name,
            $due->period,
            $due->amount,
            $due->paid_at?->format('Y-m-d'),
            $due->recordedBy?->name ?? '',
        ];
    }
}
```

- [ ] **Step 2: Route di `SbaPanelProvider.php`** — tambah import `use App\Exports\DuesExport;` (di grup import `App\Exports\...`) dan di `authenticatedRoutes()` setelah route member-report.export:

```php
Route::get('/dues-report/export', function (): BinaryFileResponse {
    return DuesExport::streamFor(
        auth()->user()->organization_id,
        request()->only(['period', 'period_start', 'period_end']),
        request()->string('format', 'csv')->toString(),
    );
})->name('dues-report.export'),
```

Catatan staging: file ini membawa WIP Phase-2 di working tree. Saat commit, stage HANYA hunk import `DuesExport` + hunk route via `git apply --cached` patch terpotong — jangan `git add` seluruh file.

- [ ] **Step 3: `php artisan test --filter ExportTest`**

Expected: 9 passed (4 lama + 5 baru). Jika `assertSame(... $user->name)` mismatch (nama faker menabrak), pakai nama eksplisit (`'Bendahara Beta'`).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/ExportTest.php
git commit -m "test(sp5): dues export tests"
# lalu stage presisi import+route SbaPanelProvider (tanpa WIP) + app/Exports/DuesExport.php
git commit -m "feat(sp5): dues export CSV/XLSX with tenant scoping + whitelist"
```

---

### Task 3: Anotasi kanonik + verifikasi penuh

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` (Task 4.2 checklist + Task 4.6 item dues)

- [ ] **Step 1:** Centang 3 item Task 4.2 (`- [x]`): whitelist kolom, tenant-scoped, CSV+XLSX.
- [ ] **Step 2:** Centang 1 item Task 4.6: "Test: CSV/XLSX dues export". (Attendance/complaint tetap `- [ ]`. Item whitelist/isolasi/anonymous/sba-admin di Task 4.6 sudah dicentang Task 4.1 — jangan diganggu.)
- [ ] **Step 3:** Append anotasi deviation di bawah Task 4.2:

```markdown
(** executed @2026-09-09: meniru struktur `MemberExport` (stream temp-file +
`deleteFileAfterSend(true)`, §8.4 cabang stream). Duplikasi skeleton ~25 baris
sengaja dibiarkan utuh — konsolidasi ke helper bersama dijadwalkan saat Task 4.3
(exporter ke-3). `pencatat` dari `recordedBy.name` (relasi `recorded_by`); jika
user terhapus → sel kosong. `amount` diekspor string decimal (`50000.00`),
`paid_at` `Y-m-d`, konsisten `MemberExport`. Filter `period`/`period_start`/
`period_end` identik `DuesReportPage` (string `YYYY-MM`). Route di
`SbaPanelProvider::authenticatedRoutes()`; query param `organization_id` tak
dibaca (spoof diabaikan). Anonim export iuran di-assert via `ExportTest`
(redirect `/panel-sba/login`). **)
```

- [ ] **Step 4:** `composer test` (expected **296 passed / 1 failed** — hanya card verification Phase 6), `vendor/bin/pint`, `npm run build`.
- [ ] **Step 5:** Commit docs (`docs(sp5): task 4.2 dues export annotations + checklist`).

---

## Self-Review

- **Spec coverage:** §8.1 (CSV fputcsv + XLSX openspout) ✓; §8.2 whitelist 5 kolom exact + tes negatif `member_id,`/`organization_id,` ✓; §8.3 tanpa secret/data SBA lain ✓; §8.4 filename `iuran-...` tanpa identitas tunggal + auto-delete ✓; §8.5 isolasi tenant (spoof `?organization_id=B` → isi SBA B absent) ✓; §8.6 auth server-side (route auth panel + anonim redirect di-assert) ✓. Item Task 4.6 attendance/complaint didefer ke 4.3/4.4.
- **Placeholder scan:** semua step berisi kode lengkap, tidak ada TBD.
- **Type consistency:** `streamFor(int, array, string): BinaryFileResponse` identik `MemberExport`; `readXlsx(string): array` reusable di 4.3/4.4; filter keys sama dengan `DuesReportPage::getFilteredQuery()`; `For::for($member, 'member')` mengikuti quirk non-conventional relation (AGENTS.md).