# Repo Renovation — Evaluation Gate Fase A–F

Status: **PASS** (verdict @2026-09-11, semua fase lulus dengan deviasi minor
yang sudah diperbaiki pada gate ini).

Berlaku atas rencana rekonstruksi Fase A–F (ditinjau dari konteks sesi; tidak
ada file plan tersendiri untuk renovasi repo — lihat commit `72c8077`,
`52bce42`, `d0ca1a4`, `6a960bf`, `a4f858c` dan commit review-nya).
Mengikuti format gate SP5 Phase 10: evaluasi berbasis bukti atas artifact
committed, review independen subagent, verdict, dan perbaikan temuan.

---

## 1. Tujuan

Menilai kepatuhan hasil pengerjaan Fase A–F terhadap plan yang ditetapkan,
memastikan tidak ada regresi/bug baru yang tak disadari, dan menutup deviasi
yang ditemukan. Output: verdict per fase + daftar temuan + perbaikan.

---

## 2. Lingkup evaluasi

| Fase | Plan (rekonstruksi) | Commit leading | Commit review |
|------|---------------------|----------------|---------------|
| A | Audit phase-4 enhancement, fix temuan | `ea460c8` | — |
| B | PHPStan L7 + baseline, paratest, pint strict, composer scripts | `72c8077` | `66753a4` |
| C | `.githooks`, `core.hooksPath`, CHANGELOG v1.0.0, CONTRIBUTING, blame-ignore-revs, tag `v1.0.0` | `52bce42` | `907bfed` |
| D | composer personalisasi, LICENSE, SECURITY.md, CODE_OF_CONDUCT, bare mirror | `d0ca1a4` | `b4f5f9c` |
| E | GitHub Actions (verify/coverage/audit), dependabot, PR/issue templates | `6a960bf` | `a09202a` |
| F | `REPO_MANAGEMENT.md` SOP maintainer + link dari AGENTS.md | `a4f858c` + `abca9b3` | `31b32f6` |

Di luar lingkup: fase SP1–SP5 (punya gate tersendiri), manual QA browser
(task 10.4 SP5 — sudah berjalan di kanal SP5).

---

## 3. Evidence run

- `composer verify` → **EXIT=0**: `Tests: 409 passed (1413 assertions)`,
  PHPStan `[OK] No errors`, Pint `passed`.
- `composer audit --locked` → **EXIT=0**: `No security vulnerability
  advisories found.`
- `npm run build` → **EXIT=0** (manifest + site.js diregenerasi).
- `php artisan test --filter "AuditTrailTest|SbaBatchActionTest"` →
  **16 passed (91 assertions)** — fase A regression spesifik.
- `composer validate` → `./composer.json is valid`.
- Mirror fsck → bersih; mirror HEAD = worktree HEAD (`31b32f6`).

---

## 4. Verdict per fase

| Fase | Verdict | Catatan |
|------|---------|---------|
| A | **PASS** | `deleted()` hook ada + PII-free; single & bulk delete ter-audit; 16 test hijau |
| B | **PASS** | pint strict dibuat di `72c8077` (tak berubah setelahnya), phpstan L7+baseline, semua script ada |
| C | **PASS** | tag `v1.0.0` → commit `52bce42`; blame-ignore berisi `72c8077`; CHANGELOG [1.0.0]; hooks aktif |
| D | **PASS** | composer `fsbmm/website` valid; LICENSE MIT; SECURITY tanpa email `.test` palsu; mirror fsck OK |
| E | **PASS** | YAML ci.yml + dependabot valid; urutan `.env`+`key:generate`+build aman |
| F | **PASS** | SOP fakta vs repo akurat; link dari AGENTS.md ada |

---

## 5. Temuan & perbaikan (gate ini)

Review independen (subagent) menemukan 1 temuan substansial + 2 minor yang
layak diperbaiki; sisanya kosmetik/out-of-scope (Linux-first).

1. **pre-commit verifikasi working tree, bukan staged blob** (substansial).
   `php -l` membaca file disk; skenario "stage rusak → memperbaiki working
   tree → commit" lolos dari hook. Diperbaiki: `git show ":<file>" | php -l`
   membaca blob staged (teruji: blob rusak DITOLAK walau worktree sehat; blob
   sehat LOLOS). Juga `--diff-filter=ACM` → **ACMR** agar rename terperiksa.
2. **Daftar jenis commit tidak harmonis**. `commit-msg` mengizinkan `perf`+
   `revert` (tak tercantum di docs) dan menolak `security` (dicantumkan
   REPO_MANAGEMENT.md). Source of truth = regex hook. Diperbaiki: daftar di
   `REPO_MANAGEMENT.md` diselaraskan ke hook (11 jenis; catatan anti-`security`).
   Opsi `security` di `pull_request_template.md` diganti keterangan bahwa
   perbaikan keamanan memakai `fix`/`chore`.
3. **`.styleci.yml` dirujuk di `.gitattributes` tapi file tak pernah ada**.
   Baris `export-ignore` dihapus (pemangkasan).

Temuan minor yang TIDAK diperbaiki (dicatat, tak memblokir):
- `xargs -d '\n'` GNU-only — repo/CI Linux-first, valid.
- `commit-msg` menolak `git revert` default dan breaking `!` — repo tak
  pernah menggunakan; backup disengaja.
- Actions di-pin tag (bukan SHA), job coverage tanpa threshold enforced —
  pipeline tanpa secret, dapat diterima.
- pre-commit pint membaca working tree (BUKAN blob) — perbaikan penuh blob
  pint memerlukan tempfile; php -l sudah menutup lubang syntax utama.

---

## 6. Perbaikan yang dikomit gate ini

`.gitattributes`, `.githooks/pre-commit`, `.github/pull_request_template.md`,
`REPO_MANAGEMENT.md` — lihat commit gate untuk diff penuh.

---

## 7. Ekspektasi status akhir

- Verdict keseluruhan: **PASS** — semua fase A–F lulus; semua deviasi materi
  (hook staged-blob, konsistensi commit types, residual `.styleci.yml`)
  diperbaiki dalam gate ini.
- `knowledge.md` (gitignored): status renovasi repo boleh ditandai selesai.
- Roadmap SP1–SP5 status tidak berubah oleh gate ini (di luar lingkup).