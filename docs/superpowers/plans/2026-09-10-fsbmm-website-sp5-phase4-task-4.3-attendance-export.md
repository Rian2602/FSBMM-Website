# Task 4.3: Attendance Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `app/Exports/AttendanceExport.php` (CSV+XLSX, tenant-scoped, 5-kolom whitelist) + konsolidasi skeleton exporter: ekstraksi `app/Exports/ReportExport.php` (base class bersama Member/Dues/Attendance), output byte-identical.

**Architecture:** Empat exporter turunan satu abstract base `ReportExport` yang membawa semua boilerplate (normalisasi format, temp file + `deleteFileAfterSend(true)`, penulisan CSV via `fputcsv` dan XLSX via OpenSpout, LazyCursor). Tiap subclass hanya mengimplementasi `columns()`, `filenamePrefix()`, `scopedQuery()`, `row()`. Route ekspor baru di `SbaPanelProvider::authenticatedRoutes()`; tenancy murni dari `auth()->user()->organization_id`.

**Tech Stack:** PHP 8.3+, Laravel 12 `response()->download()->deleteFileAfterSend(true)`, `openspout/openspout` (Writer XLSX streaming), `fputcsv` native. Test: PHPUnit (suite `ExportTest`).

## Global Constraints

- Whitelist eksplisit (spec §8.2); kolom DB baru tidak otomatis masuk export.
- Spec §8.3 — dilarang: password/token/session, internal auth data, data org lain, secrets.
- Tenancy dari `auth()->user()->organization_id`; query param `organization_id` TIDAK dibaca (spoof diabaikan; §8.5/8.6).
- Flow ekspor = stream temp-file `deleteFileAfterSend(true)` (deviasi §8.4 sudah dikunci anotasi Task 4.1/4.2 — JANGAN re-open private-disk/TTL).
- Route hanya di `SbaPanelProvider::authenticatedRoutes()` (`/admin` tidak tersentuh). Nama route: `attendance-report.export`.
- Filename TANPA NIK/identitas tunggal (§8.4) — pola `{prefix}-{orgId}-{Y-m-d}.{ext}` (prefix: `anggota`/`iuran`/`kehadiran`).
- Header CSV via `fputcsv` meng-quote hanya sel berisi spasi/koma → attendance header riil `"Nama Anggota",Kegiatan,Tanggal,Status,Catatan`.
- Status attendance diekspor sebagai label: `hadir`→`Hadir`, `izin`→`Izin`, lainnya→`Tidak Hadir` (sama dgn `AttendanceReportPage` badge format).
- AGENTS.md testing quirks: relasi non-konvensional → `->for($event, 'event')`, `->for($member, 'member')`; `BinaryFileResponse::getContent()` null di test → `file_get_contents((string) $response->baseResponse->getFile())`.
- WIP Phase-2 (modified `AGENTS.md`, `app/Filament/Sba/Pages/MemberReportPage.php`, `tests/Feature/Sp5SecurityTest.php`; untracked `AttendanceReportPage.php`/`ComplaintReportPage.php`/`DuesReportPage.php`/`MemberReportPage.php` views + `ReportingTest.php` dll) TIDAK boleh — jangan diedit, jangan masuk commit. `SbaPanelProvider.php` memuat WIP → staging presisi WAJIB (tanpa `git add` file penuh).
- Item kanonik Task 4.4/4.5 tetap `- [ ]`; satu-satunya suite failure yang diharapkan = `Sp5SecurityTest::test_anonymous_can_access_card_verification` (Phase-6 placeholder; jangan "diperbaiki").
- Baseline HEAD: `6a1dc3c`, suite `296 passed / 1 failed`. Setelah 4.3 target: `301 passed / 1 failed`.
- Anotasi kanonik `(** executed ... **)` selalu APPEND, tidak pernah rewrite yang lama.

---

### Task 1: Tes attendance export (red baseline)

**Files:**
- Modify: `tests/Feature/ExportTest.php` (tambah `use App\Models\Attendance;` + `use App\Models\Event;` di use-block alphabetical; tambah 5 method public di dalam class; helper `readXlsx` + `createSbaAdmin` SUDAH ada — jangan duplikasi; 9 method eksisting dibiarkan byte-identik).

**Interfaces:**
- Consumes: helper `createSbaAdmin(string $orgName): array` dan `readXlsx(string $path): array` yang sudah ada di file.
- Produces: 5 test yang akan fail 404 (route belum ada) — mendefinisikan kontrak URL `/panel-sba/attendance-report/export`, query params `event_id`/`event_date_start`/`event_date_end`, `format=xlsx`.

- [ ] **Step 1: Tambah 5 test berikut verbatim** (indentasi 4 spasi, ponytail comments apa adanya):

```php
public function test_csv_attendance_export_has_exact_whitelist_header_and_rows(): void
{
    [$user, $org] = $this->createSbaAdmin('SBA Alpha');
    $member = Member::factory()->for($org)->create(['name' => 'Yoga Pratama']);
    $event = Event::factory()->for($org)->create(['title' => 'Seminar Nasional', 'event_date' => '2026-09-05']);
    Attendance::factory()->for($org)->for($event, 'event')->for($member, 'member')->create([
        'status' => 'hadir', 'note' => 'Peserta aktif',
    ]);

    $response = $this->actingAs($user)->get('/panel-sba/attendance-report/export');

    $response->assertOk();
    $csv = file_get_contents((string) $response->baseResponse->getFile());

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

    $response = $this->actingAs($sbaA)
        ->get('/panel-sba/attendance-report/export?organization_id='.$orgB->id)
        ->assertOk();
    $csv = file_get_contents((string) $response->baseResponse->getFile());

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

    $response = $this->actingAs($user)->get('/panel-sba/attendance-report/export?format=xlsx')->assertOk();
    $rows = $this->readXlsx((string) $response->baseResponse->getFile());

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

    $response = $this->actingAs($user)
        ->get('/panel-sba/attendance-report/export?event_id='.$eventSep->id)
        ->assertOk();
    $csv = file_get_contents((string) $response->baseResponse->getFile());

    $this->assertStringContainsString('Event September', $csv);
    $this->assertStringContainsString('Anggota A', $csv);
    $this->assertStringNotContainsString('Anggota B', $csv);
}

public function test_anonymous_cannot_export_attendance(): void
{
    $this->get('/panel-sba/attendance-report/export')->assertRedirect('/panel-sba/login');
}
```

- [ ] **Step 2: Jalankan dan pastikan merah terprediksi**

Run: `php artisan test --filter ExportTest`
Expected: **9 passed, 5 failed** — kelima test baru gagal dengan 404 (`GET /panel-sba/attendance-report/export` route belum ada). Catat output aktual. Jika ada test baru yang gagal dengan alasan lain atau ada yang tiba-tiba hijau — tandai.

- [ ] **Step 3: Tulis laporan** ke `<workspace>/task-1-report.md` (yang ditambahkan, output test, baseline reasoning).

TANPA commit.

### Task 2: Ekstraksi `ReportExport` + migrasi Member & Dues

**Files:**
- Create: `app/Exports/ReportExport.php` (base abstract, verbatim di bawah).
- Modify: `app/Exports/MemberExport.php` dan `app/Exports/DuesExport.php` → extends `ReportExport`, seluruh body dipindah, kakas `streamFor`/`writeCsv`/`writeXlsx`/`rows` HAPUS dari child (diwarisi).

**Interfaces:**
- Consumes: file yang sudah di-commit (f88e940 dan 169be3e) sebagai sumber body scopedQuery/row.
- Produces: `ReportExport` abstract dengan 4 abstract member + 1 public static `streamFor(int, array, string='csv'): BinaryFileResponse`. Subclass memanggil `MemberExport::streamFor(...)` — signature route TIDAK berubah.

- [ ] **Step 1: Create `app/Exports/ReportExport.php` verbatim:**

```php
<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class ReportExport
{
    /** @return array<string, string> key => public label */
    abstract protected static function columns(): array;

    abstract protected static function filenamePrefix(): string;

    abstract protected static function scopedQuery(int $organizationId, array $filters): Builder;

    abstract protected static function row($model): array;

    public static function streamFor(int $organizationId, array $filters, string $format = 'csv'): BinaryFileResponse
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';
        $path = sys_get_temp_dir().'/fsbmm_'.Str::random(8).'.'.$format;
        $query = static::scopedQuery($organizationId, $filters);

        $format === 'xlsx' ? static::writeXlsx($query, $path) : static::writeCsv($query, $path);

        return response()
            ->download($path, sprintf('%s-%s-%s.%s', static::filenamePrefix(), $organizationId, now()->format('Y-m-d'), $format))
            ->deleteFileAfterSend(true);
    }

    private static function writeCsv(Builder $query, string $path): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, array_values(static::columns()));
        $query->cursor()->each(function ($model) use ($handle): void {
            fputcsv($handle, static::row($model));
        });
        fclose($handle);
    }

    private static function writeXlsx(Builder $query, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(static::columns())));
        $query->cursor()->each(function ($model) use ($writer): void {
            $writer->addRow(Row::fromValues(static::row($model)));
        });
        $writer->close();
    }
}
```

- [ ] **Step 2: `app/Exports/MemberExport.php` → target akhir verbatim:**

```php
<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

class MemberExport extends ReportExport
{
    protected static function columns(): array
    {
        return [
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
    }

    protected static function filenamePrefix(): string
    {
        return 'anggota';
    }

    protected static function scopedQuery(int $organizationId, array $filters): Builder
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

    protected static function row($model): array
    {
        $member = $model;

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

- [ ] **Step 3: `app/Exports/DuesExport.php` → target akhir verbatim:**

```php
<?php

namespace App\Exports;

use App\Models\Due;
use Illuminate\Database\Eloquent\Builder;

class DuesExport extends ReportExport
{
    protected static function columns(): array
    {
        return [
            'member.name' => 'Nama Anggota',
            'period' => 'Periode',
            'amount' => 'Nominal',
            'paid_at' => 'Tanggal Pembayaran',
            'recordedBy.name' => 'Pencatat',
        ];
    }

    protected static function filenamePrefix(): string
    {
        return 'iuran';
    }

    protected static function scopedQuery(int $organizationId, array $filters): Builder
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

    protected static function row($model): array
    {
        $due = $model;

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

- [ ] **Step 4: Verifikasi byte-identical**

Run: `php artisan test --filter ExportTest` → **9 passed** (9 method eksisting: 4 member + 5 dues — output TIDAK berubah).
Run: `vendor/bin/pint tests app/Exports` → clean.
Java import yang tidak terpakai di child (jika masih ada `LazyCollection`, `Str`, `Row`, `Writer`, `BinaryFileResponse`) — hapus.

- [ ] **Step 5: Commit**

```bash
git add app/Exports/ReportExport.php app/Exports/MemberExport.php app/Exports/DuesExport.php
git commit -m "refactor(sp5): extract ReportExport base for CSV/XLSX exporters"
```

### Task 3: `AttendanceExport` + route

**Files:**
- Create: `app/Exports/AttendanceExport.php` (verbatim di bawah).
- Modify: `app/Providers/Filament/SbaPanelProvider.php` (IMPORT + 1 route saja — file membawa WIP Phase-2; staging presisi WAJIB, lihat Step 3).

**Interfaces:**
- Consumes: `ReportExport` (Task 2).
- Produces: `AttendanceExport::streamFor(int, array, string='csv'): BinaryFileResponse`; route `GET /panel-sba/attendance-report/export` nama `attendance-report.export`.

- [ ] **Step 1: Create `app/Exports/AttendanceExport.php` verbatim:**

```php
<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;

class AttendanceExport extends ReportExport
{
    protected static function columns(): array
    {
        return [
            'member.name' => 'Nama Anggota',
            'event.title' => 'Kegiatan',
            'event.event_date' => 'Tanggal',
            'status' => 'Status',
            'note' => 'Catatan',
        ];
    }

    protected static function filenamePrefix(): string
    {
        return 'kehadiran';
    }

    protected static function scopedQuery(int $organizationId, array $filters): Builder
    {
        $eventQuery = Event::query()->where('events.organization_id', $organizationId);
        if (! empty($filters['event_id'])) {
            $eventQuery->whereKey($filters['event_id']);
        }
        if (! empty($filters['event_date_start'])) {
            $eventQuery->whereDate('events.event_date', '>=', $filters['event_date_start']);
        }
        if (! empty($filters['event_date_end'])) {
            $eventQuery->whereDate('events.event_date', '<=', $filters['event_date_end']);
        }

        return Attendance::query()
            ->with('member', 'event')
            ->where('attendances.organization_id', $organizationId)
            ->whereIn('attendances.event_id', $eventQuery->select('id'))
            ->orderBy('id');
    }

    protected static function row($model): array
    {
        $attendance = $model;

        return [
            $attendance->member?->name,
            $attendance->event?->title,
            $attendance->event?->event_date?->format('Y-m-d'),
            match ($attendance->status) {
                'hadir' => 'Hadir',
                'izin' => 'Izin',
                default => 'Tidak Hadir',
            },
            $attendance->note ?? '',
        ];
    }
}
```

- [ ] **Step 2: `app/Providers/Filament/SbaPanelProvider.php`** — tambah import `use App\Exports\AttendanceExport;` di blok `App\Exports\...` (sebelum `DuesExport`) dan route SETELAH blok `dues-report.export` (di dalam closure `authenticatedRoutes`), verbatim:

```php
Route::get('/attendance-report/export', function (): BinaryFileResponse {
    return AttendanceExport::streamFor(
        auth()->user()->organization_id,
        request()->only(['event_id', 'event_date_start', 'event_date_end']),
        request()->string('format', 'csv')->toString(),
    );
})->name('attendance-report.export'),
```

- [ ] **Step 3: Staging presisi (WAJIB)** — JANGAN `git add` seluruh provider (file memuat WIP Phase-2). Gunakan teknik tetap dari Task 4.2:
  1. `git show HEAD:app/Providers/Filament/SbaPanelProvider.php > /tmp/opencode/prov_target.php`
  2. Insert import baris baru (`use App\Exports\AttendanceExport;\n` di satu blok alphabetics `App\Exports` — catatan: setelah 4.2, `use App\Exports\DuesExport;` ada di baris terpisah pasca namespace blank; sisip import dekat blok itu) dan route setelah `})->name('dues-report.export'),`.
  3. `blob=$(git hash-object -w /tmp/opencode/prov_target.php)` lalu `git update-index --cacheinfo 100644 "$blob" app/Providers/Filament/SbaPanelProvider.php`.
  4. `git add app/Exports/AttendanceExport.php`.
  5. VERIFIKASI `git diff --cached app/Providers/Filament/SbaPanelProvider.php` HANYA berisi 2 tambahan (import + route). Baru commit.

- [ ] **Step 4: Verifikasi**

Run: `php artisan test --filter ExportTest` → **14 passed**.
Jika ada test yang gagal karena perbedaan format output NYATA, sesuaikan EXPECTATION di test (katakan di report kenapa) — jangan menurunkan kontrak ekspor.

- [ ] **Step 5: Commit**

```bash
git add app/Exports/AttendanceExport.php
# lalu update-index untuk provider sdh tadi
git commit -m "feat(sp5): attendance export CSV/XLSX with tenant scoping + whitelist"
```

### Task 4: Anotasi kanonik + verifikasi penuh

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` (checklist ticks + append annotation).

- [ ] **Step 1: Tick** `- [ ]` → `- [x]` PERSIS 4 item saja:
  - Task 4.3: `Column whitelist (nama anggota, kegiatan, tanggal, status, catatan)`
  - Task 4.3: `Tenant-scoped`
  - Task 4.3: `CSV + XLSX`
  - Task 4.6: `Test: Attendance export`
  Semua item lain (4.4/4.5/4.6 sisanya) dibiarkan `- [ ]`.

- [ ] **Step 2: Append annotation** di bawah checklist Task 4.3, gaya persis anotasi 4.1/4.2 (baca `git show 6a1dc3c -- docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` untuk pola). Isi:
  - commit hashes (refactor, feat, docs) tanpa tanggal tempel.
  - Konsolidasi terealisasi: `ReportExport` abstract base (streamFor/writeCsv/writeXlsx shared; subclass implementasi columns/filenamePrefix/scopedQuery/row) — MemberExport+DuesExport dimigrasi, output byte-identical (9 tes existing hijau sebelum attendance ditambah).
  - AttendanceExport: whitelist {nama anggota, kegiatan, tanggal (`event.event_date` `Y-m-d`), status (label Hadir/Izin/Tidak Hadir), catatan}; filename `kehadiran-{org}-{Y-m-d}`; filter `event_id`/`event_date_start`/`event_date_end` identik `AttendanceReportPage` (Event discope org + `whereIn event_id`).
  - fputcsv quoting: header CSV riil `"Nama Anggota",Kegiatan,Tanggal,Status,Catatan` (keluarga quoting sama dgn Task 4.1/4.2).
  - Anonim + isolasi tenant di-assert via `ExportTest` (5 test baru; total 14).

- [ ] **Step 3: Verifikasi penuh**
  - `composer test` → **301 passed / 1 failed** (satu-satunya gagal = `test_anonymous_can_access_card_verification`; JANGAN disentuh). Catat angka.
  - `vendor/bin/pint` → bersih; BILA ada churn kolateral pint di file komit lain: `git restore <file>` SEBELUM commit (hanya kanonik plan yang boleh masuk stage).
  - `npm run build` → sukses.
- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md
git commit -m "docs(sp5): task 4.3 attendance export annotations + checklist"
```

---

## Self-Review Checklist

- [x] Spec §8.1 (CSV `fputcsv` + XLSX OpenSpout) di Task 2/3
- [x] Spec §8.2 whitelist eksplisit (tidak ada `->select('*')`/`getAttributes()`) di Task 2/3
- [x] Spec §8.5 isolasi tenant + test di Task 1
- [x] Spec §8.6 authorization (`sba_admin` only org sendiri; anonim redirect) di Task 1/3
- [x] §8.4 flow stream pattern dipertahankan (bukan private-disk) — konsisten anotasi 4.1/4.2
- [x] Tidak ada placeholder; setiap task memuat kode lengkap verbatim
- [x] Signatures konsisten antar task: `streamFor(int, array, string='csv'): BinaryFileResponse` dipanggil route; method abstract base konsisten dengan override child
- [x] Baseline/final suite numbers tercatat