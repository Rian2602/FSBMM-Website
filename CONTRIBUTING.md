# Contributing to FSBMM Website

Terima kasih sudah berkontribusi. Dokumen ini adalah aturan main yang berlaku di
repo ini — pelanggaran aturan tersebut akan ditolak di review.

## Stack & struktur

Laravel 12 + Filament 3 (dua panel: `/admin` staf, `/panel-sba` admins SBA) +
Tailwind v4. Sebelum menyentuh kode, baca `README.md` dan `AGENTS.md` sampai
paham, terutama konvensi keamanan (PII federation) dan struktur panel.

Area khusus yang memerlukan membaca plan dulu: laporan SBA/ekspor/kartu anggota
(SP5), e-learning (SP4), data anggota (SP3), dan enhancement fase 1–4. Plan ada
di `docs/superpowers/plans/`. Jangan pernah melewati ini — "skipping a
spec/plan is how this repo breaks".

## Git workflow

### Branch

| Jenis        | Nama            | Bersumber dari | Merge ke |
|--------------|-----------------|----------------|----------|
| Fitur        | `feature/*`     | `master`       | `master` |
| Perbaikan    | `hotfix/*`      | `master`       | `master` |

- Default branch adalah `master` dan jalan langsung di produksi.
- Buat branch bernama bermakna: `feature/sertifikat-qr`, `hotfix/export-expired`.
- **Merge wajib `--no-ff`** (tombol "Create a merge commit" di GitHub) supaya
  riwayat merubah penyebabnya, bukan "fast-forward" tanpa jejak.
- Push PR, review, baru merge. Jangan commit langsung ke `master` untuk pekerjaan
  non-trivial; admin dapat meng-commit perubahan kecil/docs langsung bila alasan
  jelas (konsisten dengan cara `docs`, `chore(repo)`, `fix(phase4)` masuk).
- Tidak ada force-push ke `master`. Amandemen hanya pada commit lokal sebelum di-push.

### Commit message (conventional commits)

Wajib `type(scope): description` — diperkuat oleh `.githooks/commit-msg`.
`scope` opsional, deskripsi bahasa Indonesia atau Inggris kata kerja imperatif.

```text
feat(course): add certificate printing          # fitur baru
fix(export): respect org scope in download      # perbaikan bug
docs(sp5): document reporting gates             # dokumentasi / plan
chore(repo): fase b — phpstan, paratest         # tooling repo, refactor
test(security): cover cross-tenant bulk action  # tes saja
style(php): apply pint.json strict rules        # format kode murni
```

Referensikan pekerjaan multi-langkah ke nomor plan di deskripsi commit bila ada.

### Hooks (wajib untuk semua contributor)

Repo memakai `core.hooksPath = .githooks`, diaktifkan otomatis oleh
`composer install`/`update` (`post-install-cmd`). Isinya:

- **pre-commit**: lint Pint `--test` + `php -l` untuk file PHP yang di-stage.
- **commit-msg**: memvalidasi format conventional commit.

Jika hooks nonaktif di lingkungan Anda (mis. CI tanpa `.git`), jalankan
`git config core.hooksPath .githooks`. Jangan hapus atau nonaktifkan hooks ini;
buang waktu dengan menegakkan format onar di review.

### Git blame

Berkas `.git-blame-ignore-revs` berisi commit reformat besar (mis. Pint seluruh
suite) supaya `git blame` tidak menuduh baris yang hanya berganti whitespace.
Aktifkan di lingkungan lokal:

```bash
git config blame.ignoreRevsFile .git-blame-ignore-revs
```

## Quality gates (jalankan SEMUA sebelum push/PR)

```bash
composer verify        # = composer test + composer analyse + pint --test
composer test:parallel # whole suite, paralel (lebih cepat di dev)
npm run build          # frontend prod build
```

`composer verify` TEKS penggerak utama — gagal pada: tes rusak, temuan PHPStan
baru (level 7, baseline lama milik `phpstan-baseline.neon`), atau file PHP tidak
sesuai `pint.json`. Jangan commit state yang `composer verify`-nya merah.

## Rekomendasi review

- Kecil: perubahan seperlunya, tanpa abstraksi spekulatif (rokok "ponytail" —
  kode paling pendek yang benar).
- Jangan menambah dependency untuk hal yang bisa 3 baris dengan stdlib/native.
- PII: federation never melihat row `members`/`dues`/`attendances`/`complaints`,
  hanya agregat. Deskripsi audit trail wajib bebas PII.
- Debrief pelanggaran konvensi ini akan ditolak pada review.

## Daftar periksa sebelum mengirim PR

- [ ] Baca spek/plan area yang disentuh di `docs/superpowers/plans/`
- [ ] Branch bernama `feature/*` / `hotfix/*` dari `master`
- [ ] `composer verify` hijau + `npm run build` bersih
- [ ] Commit message conventional (hooks enforcer)
- [ ] Merge `--no-ff` setelah review