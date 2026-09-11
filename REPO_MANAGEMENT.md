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
| Backup          | GitHub `origin` (source of truth) + bare mirror `../fsbmm-website.git.bare` (lihat §Backup & remote) |
| SOP ini         | `REPO_MANAGEMENT.md` (di-link dari `AGENTS.md`)                                |

## Branch & riwayat

- `master` adalah default dan production; **tidak ada force-push ke master**
  (dijaga juga oleh GitHub ruleset — branch protection aktif penuh).
- Semua perubahan masuk via PR dari `feature/*` / `hotfix/*` ke `master`.
  Owner boleh self-merge PR kecil (docs/chore) tanpa reviewer setelah CI hijau;
  PR kontributor lain wajib review. **Push langsung ke master diblokir**
  (ruleset, termasuk admin).
- Riwayat linier tidak dipaksakan — gunakan merge commit `--no-ff`.
- Reformat besar (mis. Pint seluruh suite) dicatat di `.git-blame-ignore-revs`
  supaya `git blame` tidak menyalahkan whitespace. Aktifkan di mesin:
  `git config blame.ignoreRevsFile .git-blame-ignore-revs`.

## Commit messages

Jenis wajib (conventional): `feat`, `fix`, `docs`, `style`, `refactor`,
`perf`, `test`, `chore`, `ci`, `build`, `revert` (identik dengan regex
`.githooks/commit-msg`; jangan menambah `security` dll. yang tidak ada di
hook). Contoh valid ada di
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
  4. Push: `git push origin master --tags` lalu perbarui mirror lokal
     (`git push ../fsbmm-website.git.bare master:master --tags`).
  5. Buat GitHub Release dari tag `vX.Y.Z` (`gh release create vX.Y.Z`).
- Konvensi peningkatan/penurunan besar (breaking) — lihat `CHANGELOG.md`.

## Backup & remote

- **GitHub = source of truth**: `https://github.com/Rian2602/FSBMM-Website`
  (remote `origin`), branch `master` protected (ruleset: require PR + checks
  `verify`/`composer-audit`, non-fast-forward/no force-push, no-delete;
  merge `--no-ff` tetap sah — lineran history tidak dipaksakan).
- Mirror lokal (bare `../fsbmm-website.git.bare`) = backup sekunder di mesin
  pengembang; setara 1:1 dengan master saat larut malam dev.
- Sinkronisasi: `git push origin master --tags` untuk publikasi; bila bekerja
  offline, dorong juga ke mirror lokal:
  `git push ../fsbmm-website.git.bare master:master` (+ `--tags`).
- Verifikasi sesekali: `git --git-dir=../fsbmm-website.git.bare fsck`.

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