# SP5 Phase 4 — Evaluasi Pengerjaan (Task 4.1–4.6) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans.

**Goal:** Menutup P0 VALIDATION GATE (14 item) + DoD P0 (spec §19) berbasis bukti — setelah mengamankan baseline reporting Phase-2 yang masih WIP — disertai review commit Phase-4 dan keputusan "proceed/delay ke P1".

**Architecture:** Tidak ada perubahan fungsi. Dua bagian: (A) komit baseline WIP (10 file) agar evaluasi berjalan atas kode yang ter-komit & reproducible; (B) audit bukti gate + review kode Phase-4 + fiks temuan (bila ada) + tick & anotasi.

**Tech Stack:** Laravel 12 / PHPUnit 11 / Filament 3.3.55; verifikasi `composer test`, `vendor/bin/pint`, `npm run build`.

## Global Constraints
- File plan `docs/superpowers/plans/*.md` TETAP untracked (konvensi) — jangan commit.
- Komit baseline presisi: `git add` 10 file bernama eksplisit, bukan `git add .`.
- Komit yang di-review: `3cae1f4`..`ce1bbd8` (Phase-4). Eksklusi `04c51b5`/`6c67021` (uploads/GD — terpisah).
- WIP Phase-2 (nav `Laporan`, tenant-scoped) tidak diubah fungsinya — hanya di-commit.
- Expected failure Phase-6 (`Sp5SecurityTest::test_anonymous_can_access_card_verification`) TIDAK diperbaiki; dicatat pengecualian gate.
- Anotasi append-only `(** ... **)`; tick hanya dengan bukti valid. Bahasa: Indonesia. Style commit `feat(sp5):`.

---

### Task 1: Amankan baseline reporting Phase-2 (commit `feat`)

**Files:**
- Add: `app/Filament/Sba/Pages/{Dues,Attendance,Complaint}ReportPage.php`, blade `{attendance,complaint,dues}-report-page.blade.php`, `tests/Feature/ReportingTest.php`
- Modify: `AGENTS.md`, `MemberReportPage.php`, `SbaPanelProvider.php`

- [ ] **Step 1:** Pra-kondisi — provider diff WIP-only; status hanya 10 file ini + plan docs.
- [ ] **Step 2:** `git add` 10 file eksplisit + commit `feat(sp5): commit reporting phase-2 baseline — report pages, blades, provider registration, ReportingTest`
- [ ] **Step 3:** Verifikasi: `php artisan test --filter ReportingTest` → 16 passed; `git status` hanya plan docs untracked; pint --test pada 3 file.
- [ ] **Step 4:** Catat hash baseline.

---

### Task 2: Audit bukti P0 gate & DoD

- [ ] **Step 1:** Tabel bukti 14 item gate → `File::method`/output command (liat plan inline).
- [ ] **Step 2:** Audit DoD P0 (spec §19): Reporting 7 + Export 5; deferral: `migrate:fresh --seed` (incl. MemberCardSeeder) → Phase 5+; manual smoke → Final Verification.
- [ ] **Step 3:** Daftar partial/deferred untuk anotasi.

---

### Task 3: Review kode Phase-4 (skill `requesting-code-review`)

- [ ] **Step 1:** Review `3cae1f4..ce1bbd8` + baseline: whitelist §8.2, nilai row, filter-threading `3cae1f4`, scoping, storage 4.5 (private, TTL, sweep, realpath, param org tersign, 403, route di atas catch-all), PII §10.
- [ ] **Step 2:** Klasifikasi must-fix vs nice-to-have vs catatan. Must-fix → stop + present + fix-task TDD.
- [ ] **Step 3:** Ringkasan temuan.

---

### Task 4: Gate eksekusi + tick + anotasi (commit `docs`)

- [ ] **Step 1:** `composer test` → 316/1; `vendor/bin/pint`; `npm run build`.
- [ ] **Step 2:** Tick P0 gate 14 + DoD P0 terpenuhi; item composer all-green ber-notasi pengecualian; DoD Quality defer.
- [ ] **Step 3:** Anotasi `(** evaluated @2026-09-10 ... **)`: tabel bukti, temuan review, deferral, hash baseline+docs, verdict gate PASS → lanjut P1 (atau HOLD).
- [ ] **Step 4:** Commit `docs(sp5): P0 gate evaluation — evidence audit + review findings`