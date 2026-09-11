# REPO_MANAGEMENT.md — SOP Maintenance Repo

Dokumen operasional untuk maintainer. Kontributor baca `CONTRIBUTING.md`;
dokumen ini untuk yang memelihara repo (commit master, tag release, merawat
CI, dependabot, mirror). Baca juga `AGENTS.md` (aturan permanen repo) dan
`README.md` (setup/stack).

## Peta kendali (one-page)

| Area            | File pengatur                                                                  |
|-----------------|--------------------------------------------------------------------------------|
| Arsitektur/data | `AGENTS.md`, `README.md`, `docs/superpowers/` (spec/plan per fase)             |
| Kontribusi      | `CONTRIBUTING.md`, `.github/pull_request_template.md`, `.github/ISSUE_TEMPLATE/`|
| CI              | `.github/workflows/ci.yml`                                                     |
| Dependency      | `.github/dependabot.yml`, `composer.lock`, `package-lock.json`                 |
| Git hooks       | `.githooks/` (diaktifkan `composer.json` `post-install-cmd`)                    |
| Lint/statis     | `pint.json`, `phpstan.neon`, `phpstan-baseline.neon`                           |
| Release history | `CHANGELOG.md` (norma rilis ditegakkan di sini), tag semver             |
| Keamanan        | `SECURITY.md` (laporan), `LICENSE` (MIT)                                       |
| Backup          | bare mirror `../fsbmm-website.git.bare` (lihat §Backup)                        |
| SOP ini         | `REPO_MANAGEMENT.md` (di-link dari `AGENTS.md`)                                |

## Branch & riwayat

- `master` adalah default dan production; **tidak ada force-push ke master**.
- Kontributor: `feature/*` / `hotfix/*`, merge `--no-ff`. Admin boleh commit
  langsung untuk docs/chore kecil dengan pesan conventional (lihat riwayat).
- Riwayat linier tidak dipaksakan — gunakan merge commit `--no-ff`.
- Reformat besar (mis. Pint seluruh suite) dicatat di `.git-blame-ignore-revs`
  supaya `git blame` tidak menyalahkan whitespace. Aktifkan di mesin:
  `git config blame.ignoreRevsFile .git-blame-ignore-revs`.

## Commit messages

Jenis wajib (conventional): `feat`, `fix`, `docs`, `refactor`, `test`,
`chore`, `ci`, `build`, `security`, `style`. Contoh valid ada di
`CONTRIBUTING.md`; commit-msg hook memaksakan. Untuk pekerjaan plan,
referensikan nomor/fase di deskripsi (mis. `fase b`).

## Quality gates (wajib sebelum tiap push/PR/merge)

```bash
composer verify        # test suite + phpstan (level 7, baseline) + pint --test
composer test:parallel # whole suite paralel (lebih cepat di dev)
npm run build          # frontend prod build (public/build digitignore)
```

- `composer verify` MEMERLUKAN `public/build/manifest.json` (blade `@vite`) —
  jalankan `npm run build` setelah branch switch / checkout bersih, atau semua
  test HTTP publik akan 500 (ViteException).
- `composer test:coverage` butuh driver pcov/xdebug; tanpa driver ia abort
  dengan pesan jelas — bukan tanda pengujian rusak.
- Jangan commit saat `composer verify` merah.

## CI (.github/workflows/ci.yml)

- Trigger: push/PR ke `master`.
- Jobs: `verify` (composer verify di PHP 8.3 + build asset + .env contoh),
  `coverage` (pcov, hanya push master), `composer-audit` (`composer audit
  --locked`).
- `verify` & `coverage` build asset dulu karena test render blade `@vite`.
- CI checkout TIDAK punya `.env` (digitignore) → langkah `cp .env.example .env
  && php artisan key:generate` wajib ada sebelum test; jangan dihapus.

## Dependabot

- Ekosistem: `composer`, `npm`, `github-actions` — mingguan, Senin 08:00
  Asia/Jakarta, grouped updates (lihat `.github/dependabot.yml`), label
  `dependencies`.
- Kebijakan merge: review manual; jangan auto-merge dependency. Verifikasi
  `composer verify` hijau di PR dependabot sebelum merge.

## Release & tagging

- Versi mengikuti semver (`MAJOR.MINOR.PATCH`), history di `CHANGELOG.md`.
- Prosedur:
  1. Tambah/perbarui seksi `[Unreleased]` di atas rilis terakhir pada
     `CHANGELOG.md`, lalu pindahkan isinya ke `[X.Y.Z] - YYYY-MM-DD`.
  2. Commit docs: `chore(repo): release vX.Y.Z — CHANGELOG`.
  3. Buat tag annotated: `git tag -a vX.Y.Z -m "vX.Y.Z"` (tag menunjuk ke
     commit CHANGELOG).
  4. Push: `git push origin master --tags` (bila remote ada) dan perbarui
     mirror (lihat §Backup).
- Konvensi peningkatan/penurunan besar (breaking) — lihat `CHANGELOG.md`.

## Backup: bare mirror

- Mirror lokal: bare repo `../fsbmm-website.git.bare` di mesin pengembang.
- Sinkronkan setiap perubahan master:
  `git push ../fsbmm-website.git.bare master:master` (+ `--tags`).
- Verifikasi sesekali: `git --git-dir=../fsbmm-website.git.bare fsck`.
- **Belum ada remote GitHub/CGit** (git remote -v kosong). Saat remote resmi
  ada, ganti `origin` dan baris sinkronisasi di atas.

## Credentials & secret hygiene

- Password di repo hanya melalui env seeder (`FSBMM_ADMIN_EMAIL`,
  `FSBMM_ADMIN_PASSWORD`, `FSBMM_SBA_PASSWORD`) — ambil dari lingkungan, tidak
  pernah hardcode di file yang dikomit. Lihat `SECURITY.md`.
- `.env.example` memakai placeholder `FSBMM_ADMIN_PASSWORD`/`FSBMM_SBA_PASSWORD`
  = `password`. **Wajib diganti** sebelum deploy ke selain lokal/testing —
  jangan pernah membawa nilai placeholder ke produksi.
- `APP_KEY` di `.env` lokal; jangan gunakan kunci yang sama di
  produksi/per-dev.
- `.env`, `public/build`, dan `knowledge.md` ada di `.gitignore` — jangan
  force-add.

## Keamanan

- Laporan vuln: lihat `SECURITY.md`. Sebelum deploy, ganti placeholder
  alamat kontak keamanan.
- PII: federation hanya melihat agregat (`members`/`dues`/`attendances`/
  `complaints`). Audit trail wajib bebas PII di deskripsinya.
- Boundary `/admin` (staff) vs `/panel-sba` (SBA) wajib dipertahankan.

## Kapan menambah atau mengedit konvensi di atas

- Setiap perubahan pengaturan di file kunci (`.github/*`, `pint.json`,
  `phpstan.neon`, `.githooks`, `composer.json` scripts, `.gitignore`) wajib
  lewat plan + review — tidak ada pengeditan sepihak.
- Update `AGENTS.md` bersamaan; di situ aturan yang dipakai
  agent/developer.