# Task 4.4: Complaint Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `app/Exports/ComplaintExport.php` (CSV+XLSX, tenant-scoped, whitelist {ID, judul, status, tanggal pengajuan, tanggal penyelesaian} + Deskripsi HANYA saat flag eksplisit `include_description`), plus threading `$filters` ke `columns()`/`row()` di `ReportExport`.

**Architecture:** Kelanjutan `ReportExport` (commit `cab5684`). Base mendapat threading opsional: signature abstract `columns(array $filters = [])` / `row($model, array $filters = [])`; `streamFor`→`writeCsv`/`writeXlsx` meneruskan `$filters`. Empat exporter lain tidak berubah perilaku (param opsional diabaikan). Route di `SbaPanelProvider::authenticatedRoutes()`; tenancy murni dari `auth()->user()->organization_id`.

**Tech Stack:** PHP 8.3+, Laravel 12 stream temp-file `deleteFileAfterSend(true)`, `openspout/openspout` XLSX, `fputcsv` native. Test: PHPUnit (`tests/Feature/ExportTest.php`).

## Global Constraints

- Whitelist eksplisit (spec §8.2); Deskripsi TIDAK auto-export — hanya dengan query param `include_description` (diverifikasi server-side; data tetap hanya org sendiri — zona SBA).
- Tenancy dari `auth()->user()->organization_id`; query param `organization_id` TIDAK dibaca (spoof diabaikan; §8.5/8.6).
- Filename `pengaduan-{orgId}-{Y-m-d}.{ext}` (tanpa NIK; §8.4).
- Label status: `baru`→`Baru`, `diproses`→`Diproses`, default→`Selesai` (sama badge `ComplaintReportPage`).
- fputcsv hanya meng-quote sel berisi spasi — header riil `ID,Judul,Status,"Tanggal Pengajuan","Tanggal Penyelesaian"` (+`,Deskripsi` bila flag).
- Filter `status`/`submitted_start`/`submitted_end` identik `ComplaintReportPage::getFilteredQuery()`.
- AGENTS.md quirks: `file_get_contents((string) $response->baseResponse->getFile())`; `Complaint::factory()->for($org)` (relasi `organization` konvensional).
- WIP Phase-2 tidak boleh disentuh/di-commit; staging presisi WAJIB (blob/`update-index`) + sinkronisasi WORKING TREE (pelajaran 4.3: PHP membaca working tree — index-only staging → route tidak aktif → 404).
- **Commit-tes DIAGENDKAN dalam plan ini** (perbaikan M1 review 4.3): commit `test` mendahului `feat`.
- Baseline: HEAD `13272a4`, suite `301 passed / 1 failed`, `ExportTest` 14 passed. Target akhir: suite `307 passed / 1 failed`, `ExportTest` 20 passed. Satu-satunya failure yang diharapkan = `Sp5SecurityTest::test_anonymous_can_access_card_verification` (Phase-6 placeholder, jangan disentuh).
- Anotasi kanonik `(** executed ... **)` APPEND-only; Task 4.5 tetap `- [ ]`.
- pint: `vendor/bin/pint` bersih; bila churn kolateral di file komit lain — `git restore` sebelum commit.

---

### Task 1: Tes complaint export (red baseline)

**Files:**
- Modify: `tests/Feature/ExportTest.php` (tambah `use App\Models\Complaint;` di use-block alphabetical; tambah 6 method public verbatim di bawah; `readXlsx`/`createSbaAdmin` sudah ada — jangan duplikasi; 14 method eksisting byte-identik).

- [ ] **Step 1: Tambah 6 test berikut verbatim** (indentasi 4 spasi, ponytail comments apa adanya):

```php
public function test_csv_complaint_export_has_exact_whitelist_header_and_rows(): void
{
    [$user, $org] = $this->createSbaAdmin('SBA Alpha');
    Complaint::factory()->for($org)->create([
        'title' => 'Fasilitas rusak', 'status' => 'baru',
        'submitted_at' => '2026-09-05', 'resolved_at' => null,
        'description' => 'AC kantor mati sejak seminggu',
    ]);

    $response = $this->actingAs($user)->get('/panel-sba/complaint-report/export');

    $response->assertOk();
    $csv = file_get_contents((string) $response->baseResponse->getFile());

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

    $response = $this->actingAs($user)
        ->get('/panel-sba/complaint-report/export?include_description=1')
        ->assertOk();
    $csv = file_get_contents((string) $response->baseResponse->getFile());

    $this->assertStringContainsString('Deskripsi', $csv);
    $this->assertStringContainsString('AC kantor mati sejak seminggu', $csv);
}

public function test_complaint_export_ignores_spoofed_organization_param(): void
{
    [$sbaA, $orgA] = $this->createSbaAdmin('SBA Alpha');
    [$sbaB, $orgB] = $this->createSbaAdmin('SBA Beta');

    Complaint::factory()->for($orgA)->create(['title' => 'Keluhan A']);
    Complaint::factory()->for($orgB)->create(['title' => 'Keluhan B']);

    $response = $this->actingAs($sbaA)
        ->get('/panel-sba/complaint-report/export?organization_id='.$orgB->id)
        ->assertOk();
    $csv = file_get_contents((string) $response->baseResponse->getFile());

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

    $response = $this->actingAs($user)->get('/panel-sba/complaint-report/export?format=xlsx')->assertOk();
    $rows = $this->readXlsx((string) $response->baseResponse->getFile());

    $this->assertCount(2, $rows);
    $this->assertSame(['ID', 'Judul', 'Status', 'Tanggal Pengajuan', 'Tanggal Penyelesaian'], $rows[0]);
    $this->assertSame([$complaint->id, 'Fasilitas rusak', 'Selesai', '2026-09-05', '2026-09-08'], $rows[1]);
}

public function test_complaint_export_applies_submitted_date_filter(): void
{
    [$user, $org] = $this->createSbaAdmin('SBA Alpha');
    Complaint::factory()->for($org)->create(['title' => 'Keluhan September', 'submitted_at' => '2026-09-10']);
    Complaint::factory()->for($org)->create(['title' => 'Keluhan Agustus', 'submitted_at' => '2026-08-10']);

    $response = $this->actingAs($user)
        ->get('/panel-sba/complaint-report/export?submitted_start=2026-09-01')
        ->assertOk();
    $csv = file_get_contents((string) $response->baseResponse->getFile());

    $this->assertStringContainsString('Keluhan September', $csv);
    $this->assertStringNotContainsString('Keluhan Agustus', $csv);
}

public function test_anonymous_cannot_export_complaints(): void
{
    $this->get('/panel-sba/complaint-report/export')->assertRedirect('/panel-sba/login');
}
```

- [ ] **Step 2: Run dan pastikan merah terprediksi**

Run: `php artisan test --filter ExportTest`
Expected: **14 passed, 6 failed** — keenam test baru gagal 404 (`GET /panel-sba/complaint-report/export` route belum ada). Catat output aktual; tandai bila ada yang red dengan alasan lain atau tiba-tiba hijau.

- [ ] **Step 3: Tulis laporan** ke `<workspace>/task-1-report.md` (yang ditambahkan, output test, baseline reasoning).

TANPA commit.

### Task 2: Base threading `$filters` → columns/row

**Files:**
- Modify: `app/Exports/ReportExport.php` (2 signature abstract + threading), `app/Exports/MemberExport.php`, `app/Exports/DuesExport.php`, `app/Exports/AttendanceExport.php` (signature method saja — body TIDAK berubah).

**Interfaces:**
- Consumes: base dari commit `cab5684`.
- Produces: abstract `columns(array $filters = []): array` dan `row($model, array $filters = []): array`; `streamFor(int, array, string='csv')` TIDAK berubah signature (route call sites valid).

- [ ] **Step 1: `app/Exports/ReportExport.php`** — ubah PERSIS:
   - `abstract protected static function columns(): array;` → `abstract protected static function columns(array $filters = []): array;`
   - `abstract protected static function row($model): array;` → `abstract protected static function row($model, array $filters = []): array;`
   - di `streamFor`, baris `$format === 'xlsx' ? static::writeXlsx($query, $path) : static::writeCsv($query, $path);` → `$format === 'xlsx' ? static::writeXlsx($query, $path, $filters) : static::writeCsv($query, $path, $filters);`
   - `private static function writeCsv(Builder $query, string $path): void` → `(Builder $query, string $path, array $filters): void`, dan `fputcsv($handle, array_values(static::columns()));` → `static::columns($filters)`, closure `use ($handle)` → `use ($handle, $filters)` + `static::row($model, $filters)`.
   - `private static function writeXlsx(...)` analog (kolom + row menerima `$filters`).

- [ ] **Step 2: Tiga subclass** — hanya ganti signature:
   - `protected static function columns(): array` → `protected static function columns(array $filters = []): array`
   - `protected static function row($model): array` → `protected static function row($model, array $filters = []): array`
   Body verbatim, param tidak dipakai.

- [ ] **Step 3: Verifikasi**
   - `php artisan test --filter ExportTest` → **14 passed, 6 failed** (6 failure = complaint 404, bukan dari refactor; pastikan tidak ada test 14 lama yang berubah).
   - `vendor/bin/pint app/Exports/` → bersih.
   - `git status` → hanya 4 file exporter berubah (+ WIP pre-eksisting; jangan disentuh).

- [ ] **Step 4: Commit**

```bash
git add app/Exports/ReportExport.php app/Exports/MemberExport.php app/Exports/DuesExport.php app/Exports/AttendanceExport.php
git commit -m "refactor(sp5): thread export filters into ReportExport columns/row"
```

### Task 3: `ComplaintExport` + route + commit tes

**Files:**
- Create: `app/Exports/ComplaintExport.php` (verbatim di bawah).
- Modify: `app/Providers/Filament/SbaPanelProvider.php` (import + route; WAJIB staging presisi + sinkronisasi working tree).
- Commit: `tests/Feature/ExportTest.php` (6 tes Task 1) sebagai commit `test` BERURUTAN sebelum commit `feat`.

**Interfaces:**
- Consumes: `ReportExport` (Task 2).
- Produces: `ComplaintExport::streamFor(int, array, string='csv'): BinaryFileResponse`; route `GET /panel-sba/complaint-report/export` nama `complaint-report.export`.

- [ ] **Step 1: Create `app/Exports/ComplaintExport.php` verbatim:**

```php
<?php

namespace App\Exports;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Builder;

class ComplaintExport extends ReportExport
{
    protected static function columns(array $filters = []): array
    {
        $columns = [
            'id' => 'ID',
            'title' => 'Judul',
            'status' => 'Status',
            'submitted_at' => 'Tanggal Pengajuan',
            'resolved_at' => 'Tanggal Penyelesaian',
        ];

        if (! empty($filters['include_description'])) {
            $columns['description'] = 'Deskripsi';
        }

        return $columns;
    }

    protected static function filenamePrefix(): string
    {
        return 'pengaduan';
    }

    protected static function scopedQuery(int $organizationId, array $filters): Builder
    {
        $query = Complaint::query()->where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['submitted_start'])) {
            $query->whereDate('submitted_at', '>=', $filters['submitted_start']);
        }
        if (! empty($filters['submitted_end'])) {
            $query->whereDate('submitted_at', '<=', $filters['submitted_end']);
        }

        return $query->orderBy('id');
    }

    protected static function row($model, array $filters = []): array
    {
        $complaint = $model;

        $values = [
            $complaint->id,
            $complaint->title,
            match ($complaint->status) {
                'baru' => 'Baru',
                'diproses' => 'Diproses',
                default => 'Selesai',
            },
            $complaint->submitted_at?->format('Y-m-d'),
            $complaint->resolved_at?->format('Y-m-d') ?? '',
        ];

        if (! empty($filters['include_description'])) {
            $values[] = $complaint->description;
        }

        return $values;
    }
}
```

- [ ] **Step 2: `app/Providers/Filament/SbaPanelProvider.php`** — tambah import `use App\Exports\ComplaintExport;` (dekat blok `App\Exports\...` sesuai susunan aktual HEAD) dan route SETELAH blok `attendance-report.export` (di dalam closure `authenticatedRoutes`), verbatim:

```php
Route::get('/complaint-report/export', function (): BinaryFileResponse {
    return ComplaintExport::streamFor(
        auth()->user()->organization_id,
        request()->only(['status', 'submitted_start', 'submitted_end', 'include_description']),
        request()->string('format', 'csv')->toString(),
    );
})->name('complaint-report.export'),
```

- [ ] **Step 3: Staging presisi + sinkronisasi working tree (WAJIB)** — teknik tetap 4.2/4.3 plus pelajaran 4.3 (PHP membaca working tree — index-only staging membuat route tidak aktif → 404):
  1. `git show HEAD:app/Providers/Filament/SbaPanelProvider.php > /tmp/opencode/prov_target.php`
  2. Python-replace: insert import baris `use App\Exports\ComplaintExport;` di blok `App\Exports\...` (dekat `AttendanceExport`/`DuesExport`/`MemberExport` sesuai HEAD) dan route setelah `})->name('attendance-report.export'),` lalu `                ];`.
  3. `blob=$(git hash-object -w /tmp/opencode/prov_target.php)`; `git update-index --cacheinfo 100644 "$blob" app/Providers/Filament/SbaPanelProvider.php`
  4. **Terapkan import+route yang sama ke working-tree provider** (edit file langsung supaya runtime aktif), lalu verifikasi `git diff HEAD -- app/Providers/Filament/SbaPanelProvider.php` TIDAK menampilkan baris complaint/perubahan lain di luar WIP pre-eksisting.
  5. `git add app/Exports/ComplaintExport.php`
  6. VERIFIKASI `git diff --cached app/Providers/Filament/SbaPanelProvider.php` berisi HANYA import + route sebelum commit apa pun.

- [ ] **Step 4: Commit urutan (test dulu, lalu feat)**

```bash
git add tests/Feature/ExportTest.php
git commit -m "test(sp5): complaint export tests"
git add app/Exports/ComplaintExport.php
# provider sudah via update-index
git commit -m "feat(sp5): complaint export CSV/XLSX with tenant scoping + whitelist"
```

- [ ] **Step 5: Verifikasi**
   - `php artisan test --filter ExportTest` → **20 passed**.
   - Jika ada test yang gagal karena perbedaan format/output NYATA (mis. tipe sel id di xlsx sebagai float vs int oleh OpenSpout Reader), sesuaikan EXPECTATION di test dan sebutkan di report — jangan menurunkan kontrak ekspor.
   - `git show <test-commit> --stat` = ExportTest.php saja; `git show <feat-commit> --stat` = ComplaintExport.php + provider.

### Task 4: Anotasi kanonik + verifikasi penuh

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` (ticks + append annotation).

- [ ] **Step 1: Tick** `- [ ]` → `- [x]` PERSIS 5 item:
   - Task 4.4: `Column whitelist (ID, judul, status, tanggal pengajuan, tanggal penyelesaian)`
   - Task 4.4: `Description only with explicit permission`
   - Task 4.4: `Tenant-scoped`
   - Task 4.4: `CSV + XLSX`
   - Task 4.6: `Test: Complaint export`
   Task 4.5 tetap `- [ ]`; sisanya tak tersentuh.

- [ ] **Step 2: Append annotation** di bawah checklist Task 4.4, gaya persis anotasi 4.1–4.3 (baca `git show 13272a4 -- docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md`). Isi:
   - commit hashes (refactor Task-2, test, feat, docs tanpa self-hash).
   - Base-threading: `columns()`/`row()` kini menerima opsional `array $filters = []`; `streamFor`→`write*` meneruskan `$filters`; empat exporter lain diabaikan param-nya (byte-identical).
   - Deskripsi digerbang `include_description` query param (server-side, zona SBA), TIDAK auto-export; header riil `ID,Judul,Status,"Tanggal Pengajuan","Tanggal Penyelesaian"` (+`,Deskripsi`) — fputcsv quoting keluarga sama.
   - Label status Baru/Diproses/Selesai; `resolved_at` nullable → cell kosong; filter `status`/`submitted_start`/`submitted_end` identik `ComplaintReportPage`; filename `pengaduan-{org}-{Y-m-d}`; `id` diekspor (kolom whitelist kanonik; bukan NIK).
   - Anonim + spoof di-assert via `ExportTest` (total 20 tes).

- [ ] **Step 3: Verifikasi penuh**
   - `composer test` → **307 passed / 1 failed** (satu-satunya gagal = `test_anonymous_can_access_card_verification`; jangan disentuh). Catat angka.
   - `vendor/bin/pint` → bersih; BILA churn kolateral pint di file komit lain: `git restore <file>` sebelum commit.
   - `npm run build` → sukses.
   - `git log --oneline <2-3>..HEAD` catat.
- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md
git commit -m "docs(sp5): task 4.4 complaint export annotations + checklist"
```

---

## Self-Review Checklist

- [x] Spec §8.1 (CSV+XLSX) di Task 3
- [x] Spec §8.2 whitelist eksplisit + Deskripsi hanya eksplisit (bukan `*`/`getAttributes()`)
- [x] Spec §8.3: isi pengaduan hanya org sendiri; zona SBA (spec Zona 2); tidak ke publik/federation
- [x] Spec §8.5 isolasi tenant + test; §8.6 auth + anonim redirect
- [x] §8.4: filename tanpa NIK, stream pattern dipertahankan
- [x] Tidak ada placeholder; setiap task memuat kode lengkap verbatim
- [x] Signature konsisten: abstract `columns(array $filters = [])`/`row($model, array $filters = [])` ↔ subclass; `streamFor(int, array, string='csv')` tetap dipanggil route
- [x] Commit-tes diagendakan (test sebelum feat); baseline/expected numbers tercatat