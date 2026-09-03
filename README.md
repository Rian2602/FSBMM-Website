# FSBMM — Website Federasi Serikat Buruh Makanan dan Minuman

Situs resmi (SP1) Federasi Serikat Buruh Makanan dan Minuman: halaman publik
berbasis konten plus panel admin staf federasi. Dibangun dengan **Laravel 12**,
**Filament 3**, dan **Tailwind CSS v4** — sepenuhnya *data-driven*: semua
halaman, berita, direktori SBA, e-resource, dan kursus dikelola dari panel
admin, tanpa konten hardcoded.

> Rujukan desain/domain: [SPMKB](https://github.com/Rian2602/UnionDatabase)
> (database anggota Serikat Pekerja/Buruh tingkat perusahaan). Proyek ini
> adalah federasi yang menaungi banyak SBA; SP1 menyiapkan fondasinya.

## Cakupan (SP1)

| Area | Rute | Kelola di admin |
|---|---|---|
| Halaman beranda/tentang/kontak (page-builder: hero, teks kaya, gambar, statistik, CTA, kutipan) | `/`, `/tentang`, `/kontak` | Halaman |
| Berita + kategori (terbit terjadwal: draf → terjadwal → tayang) | `/berita`, `/berita/{slug}` | Artikel, Kategori |
| Direktori SBA (organisasi) | `/sba`, `/sba/{slug}` | Organisasi SBA |
| Pustaka e-resource (unduhan PDF bertanda tangan + penghitung) | `/e-resource` | E-Resource |
| Katalog e-learning (kursus; materi SP4) | `/e-learning` | Kursus E-Learning |
| Panel admin staf (super admin + editor konten) | `/admin` | — |

Di luar SP1 (lihat spec): login SBA/member, data anggota (PII), tenant scoping,
authoring materi kursus — direncanakan di **SP2–SP4**.

## Stack

- Laravel 12 (PHP 8.3+), MySQL untuk produksi, SQLite untuk pengembangan lokal
- Filament 3 (panel admin + editor konten + page-builder)
- Tailwind CSS v4 + Vite (tanpa tema admin; tampilan publik diracik kustom)
- Tidak ada paket auth/permission tambahan: role tetap (`super_admin`, `editor`)
  dengan middleware/policy sederhana

## Menjalankan di lokal

```bash
# 1. Prasyarat: PHP 8.3+, Composer, Node 20+
cp .env.example .env
composer install
npm install

# 2. Siapkan database SQLite
touch database/database.sqlite

# 3. Kunci + migrasi + data demo
php artisan key:generate
php artisan migrate --seed
php artisan storage:link        # agar logo/sampul/PDF demo bisa diakses

# 4. Aset frontend (mode dev: npm run dev; produksi:)
npm run build

# 5. Jalankan
php artisan serve               # http://127.0.0.1:8000
```

### Akun admin

Seeder membuat akun super admin dari variabel env (lihat `.env`):

```env
FSBMM_ADMIN_EMAIL=admin@fsbmm.test
FSBMM_ADMIN_PASSWORD=password
```

> **Wajib ganti `FSBMM_ADMIN_PASSWORD` di produksi** (mis. lewat cPanel).

## Menjalankan test

```bash
php artisan test          # seluruh suite feature (PHPUnit)
```

## Struktur penting

- `app/Models/` — `User`, `Organization`, `Category`, `Article`, `Page`,
  `PageBlock`, `Eresource`, `Course`
- `app/Support/PageBlockRenderer.php` — merender blok halaman menjadi HTML
  (lihat `resources/views/blocks/*.blade.php`)
- `app/Filament/Resources/` — CRUD admin per entitas; `PageResource` memakai
  Builder Filament untuk menyusun blok halaman
- `resources/views/layouts/public.blade.php` — kerangka situs publik (token
  warna placeholder Swiss-Brutalist-Green; ganti saat aset brand resmi tiba)
- `database/seeders/` — data demo (admin, SBA, artikel, halaman, e-resource,
  kursus)

## Catatan keamanan

- Konten RichEditor (artikel/halaman) dianggap **HTML tepercaya dari staf
  federasi** — dirender mentah hanya karena penulis adalah pengguna
  terautentikasi; semua input non-staf tetap di-escape.
- Unduhan e-resource memakai URL **bertanda tangan** (`middleware('signed')`);
  file yang dihapus dari storage menghasilkan 404 yang bersih.
- Halaman struktural (`home`, `tentang`, `kontak`) tidak dapat dihapus/diubah
  slug-nya (dilindungi di level model dan UI).

## Roadmap

- **SP2** — akun & dashboard SBA (organisasi → tenant scoping, login SBA)
- **SP3** — modul data anggota per SBA (roster, upah & iuran, absensi,
  pengaduan) dengan aturan PII
- **SP4** — authoring e-learning (pelajaran + kuis untuk peserta staf)

Lihat `docs/superpowers/specs/2026-09-03-fsbmm-website-sp1-design.md` dan
`docs/superpowers/plans/2026-09-03-sp1-foundation-public-site.md` untuk detail.
