# SP5 Phase 4 Task 4.6 — Export Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup Task 4.6 (Export Tests) — 2 test characterization tambahan (cross-tenant level-session untuk dues/attendance/complaint + dataset kosong header-only) + anotasi eksekusi kanonik.

**Architecture:** Test-level only. Tidak ada kode produksi yang berubah — behaviour sudah benar; test baru adalah regression guard + menutup asimetri keselamatan (baru member yang punya cross-tenant session-level test). Flow export 2-hop (302 → signed URL → download) dipakai sebagaimana `followExport()`.

**Tech Stack:** PHPUnit 11 / Laravel Feature tests, Filament panel route `/panel-sba/*-report/export`.

## Global Constraints

- JANGAN sentuh WIP Phase-2 working tree (`SbaPanelProvider.php`, dll.) — `git add` hanya file yang dimuat test/docs.
- Pola 2-hop: `actingAs` → `get(route)` → assert 302 → `get(Location)` → `assertOk()` → baca `getFile()`.
- `deleteFileAfterSend(true)`: file masih ada saat assertion (delete happen saat terminate) — sudah terbukti di suite berjalan.
- PII: test hanya assert token nama/amount/title, tidak pernah assert kolom PII terlarang.
- Pint + `npm run build` + `composer test` sebelum komplit.
- `Due` = model singular; `Complaint` field `title`; `Attendance` factory butuh `for($event, 'event')->for($member, 'member')`.
- Bahasa komunikasi: Indonesia; commit style mengikuti riwayat (`test(sp5): ...`, `docs(sp5): ...`).

---

### Task 1: Tambah 2 test characterization (commit `test`)

**Files:**
- Modify: `tests/Feature/Sp5SecurityTest.php` — tambah imports `App\Models\Due`, `App\Models\Event`, `App\Models\Attendance`, `App\Models\Complaint`; tambah 1 method setelah `test_sba_a_cannot_export_sba_b_data`
- Modify: `tests/Feature/ExportTest.php` — tambah 1 method

- [ ] **Step 1: Tulis `test_sba_a_cannot_export_sba_b_dues_attendance_complaints`** (menutup asimetri vs anonymous; assertion token unik per endpoint)

```php
public function test_sba_a_cannot_export_sba_b_dues_attendance_complaints(): void
{
    [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
    [$sbaB, $orgB] = $this->createSbaAdmin('SBA B');

    Due::factory()->for($orgA)->create(['period' => '2026-08', 'amount' => 100000]);
    Due::factory()->for($orgB)->create(['period' => '2026-08', 'amount' => 999000]);

    $memberA = Member::factory()->for($orgA)->create(['name' => 'Andi Wijaya']);
    $memberB = Member::factory()->for($orgB)->create(['name' => 'Budi Santoso']);
    $eventA = Event::factory()->for($orgA)->create(['title' => 'Rapat A']);
    $eventB = Event::factory()->for($orgB)->create(['title' => 'Rapat B']);
    Attendance::factory()->for($orgA)->for($eventA, 'event')->for($memberA, 'member')->create();
    Attendance::factory()->for($orgB)->for($eventB, 'event')->for($memberB, 'member')->create();

    Complaint::factory()->for($orgA)->create(['title' => 'Keluhan A']);
    Complaint::factory()->for($orgB)->create(['title' => 'Keluhan B']);

    foreach ([
        'dues-report' => '999000',
        'attendance-report' => 'Budi Santoso',
        'complaint-report' => 'Keluhan B',
    ] as $endpoint => $forbidden) {
        $redirect = $this->actingAs($sbaA)
            ->get('/panel-sba/'.$endpoint.'/export?organization_id='.$orgB->id)
            ->assertStatus(302);
        $downloaded = $this->get($redirect->headers->get('Location'));
        $downloaded->assertOk();
        $content = file_get_contents((string) $downloaded->baseResponse->getFile());

        $this->assertStringNotContainsString($forbidden, $content);
    }
}
```

- [ ] **Step 2: Tulis `test_export_with_no_data_returns_header_only`** (member CSV kosong → baris header persis literal whitelist)

```php
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
```

- [ ] **Step 3: Jalankan — harap PASS langsung (characterization, bukan TDD red)**

Run: `php artisan test --filter 'sba_a_cannot_export_sba_b_dues_attendance_complaints|export_with_no_data_returns_header_only'`
Expected: 2 passed. Jika salah satu fail → bug produksi nyata; berhenti dan laporkan ke user (perbaiki root cause, bukan test).

- [ ] **Step 4: Commit** (hanya 2 file test)

```bash
git add tests/Feature/Sp5SecurityTest.php tests/Feature/ExportTest.php
git commit -m "test(sp5): cross-tenant export guard for dues/attendance/complaint + empty-dataset header-only"
```

---

### Task 2: Anotasi + gate (commit `docs`)

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` (tambah balok anotasi di bawah daftar `- [x]` Task 4.6)

- [ ] **Step 1:** Tambah balok `(** executed @2026-09-10: Task 4.6 complete ... **)` — isi: seluruh item ticked lintas 4.1–4.5 (commits `3cae1f4`, `89dc627`, `d9720da`, `70c7b55`,`24e22ec`, `7613ab4`, `d30e3ad`); peta cakupan spec §11.3 → nama test aktual (`ExportTest` 27 + `Sp5SecurityTest`); commits Task 1 (test) + Task 2 (docs) ini; ekspektasi suite akhir **316 passed / 1 failed**; catatan 2-hop `followExport` + asimetri guard kini simetris.
- [ ] **Step 2:** `composer test` → **316 passed / 1 failed** (placeholder Phase-6 `test_anonymous_can_access_card_verification`).
- [ ] **Step 3:** `vendor/bin/pint` (harap `passed`; jika ada kolateral di file komit lain → `git restore` sebelum commit) + `npm run build`.
- [ ] **Step 4:** Commit

```bash
git add docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md
git commit -m "docs(sp5): task 4.6 export tests annotations + checklist"
```