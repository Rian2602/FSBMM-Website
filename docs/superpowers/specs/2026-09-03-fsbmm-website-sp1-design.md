# FSBMM Website — Spec Desain SP1 (Foundation + Situs Publik + CMS Federasi)

Tanggal: 2026-09-03 · Status: Draft untuk review · Proyek: fsbmm-website (repo baru)

## 1. Konteks & Tujuan

Federasi Serikat Buruh Makanan dan Minuman (nama resmi & logo: menyusul dari pengurus)
membutuhkan situs web data-driven untuk:

1. **Publik** — halaman muka, tentang kami, berita/artikel, direktori SBA, e-resource,
   e-learning, kontak.
2. **Staf Federasi** — mengelola seluruh konten publik lewat CMS (artikel, halaman
   page-builder, direktori SBA, katalog resource & kursus) dan (SP2) akun SBA.
3. **SBA** (SP2+) — dashboard tiap Serikat Buruh Anggota; (SP3) database anggota
   per-SBA; (SP4) authoring kursus.

Repo ini **independen** dari SPMKB (`spm-kecap-bango`), yang hanya menjadi **referensi
desain/domain** untuk bagian database anggota (SP3). Teknologi: **Laravel 12 (PHP) +
MySQL** (produksi cPanel) — hasil wawancara 2026-09-03.

UI **Bahasa Indonesia**. Bahasa visual: keluarga desain SPMKB (Swiss Brutalist Green)
dengan identitas resmi federasi (logo/warna menyusul; placeholder dulu).

## 2. Ruang Lingkup SP1 (In / Out)

**Masuk:**
- Fondasi Laravel: auth staf federasi, role `super_admin`/`editor`, layout, Tailwind,
  migrasi, seeder.
- **Situs publik**: Home, Tentang Kami, Berita (+detail), Direktori SBA (+detail),
  E-resource, E-learning (katalog), Kontak. Semua koleksi dari DB (data-driven).
- **Federasi CMS (Filament)**: kelola artikel+kategori, halaman page-builder, organisasi
  (profil publik SBA), resource, katalog kursus; kelola pengguna staf.
- **Page-builder penuh**: `pages` + `page_blocks` (hero/rich_text/image/stats/cta/quote).
- Struktur multi-tenant `organizations` sebagai **fondasi data** (belum ada penegakan
  scope tenant; SBA = konten direktori publik).

**Keluar (sub-proyek berikutnya):**
- SP2: akun & dashboard SBA, pembuatan akun oleh federasi, overview lintas-SBA,
  penegakan scope tenant.
- SP3: database anggota per-SBA (roster, upah/iuran, absensi/event/komplain) + PII.
- SP4: authoring kursus (pelajaran, kuis, progres).
- Tidak ada login publik/anggota; tidak ada self-registration (hasil wawancara).

## 3. Arsitektur

- **Satu aplikasi Laravel 12**, satu basis data MySQL (produksi) / SQLite (dev lokal).
- Multi-tenancy **shared-schema, row-level** (`organization_id` pada tabel tenant) —
  diaktifkan penuh di SP2; skema SP1 sudah menyiapkan kolomnya.
- **Panel publik**: Blade + Tailwind CSS, desain custom mengikuti brand (bukan tema umum).
- **Panel admin**: Filament (`/admin`) — hybrid: publik custom, admin Filament.
- Role tetap (enum) + middleware — tanpa package permission.

### Struktur direktori (rencana)
```
app/
  Models/            User, Organization, Article, Category, Page, PageBlock, Resource, Course
  Http/Controllers/  Public\ (HomeController, ArticleController, OrganizationController, …)
  Filament/          Admin\Resources\{Article,Organization,Page,Resource,Course,User}Resource
  Policies/
database/migrations + seeders
resources/views/     layouts/public.blade.php, pages/home.blade.php, …
public/              (build Vite: css/app.css, js/app.js; uploads)
```

## 4. Model Data (tabel SP1)

- **users** — id, name, email (unique), password, role (`super_admin`|`editor`),
  timestamps. `organization_id` (nullable) **ditambahkan SP2**.
- **organizations** (SBA — direktori publik, fondasi tenant) —
  id, name, slug (unique), company, logo_path (nullable), description, website,
  location, founded_year, member_count (int, agregat publik; angka riil dari SP3),
  is_published (bool), timestamps. Soft-delete opsional (ditunda).
- **categories** — id, name, slug, timestamps. Dipakai artikel (SP1); nanti resource.
- **articles** — id, title, slug (unique), excerpt, body (rich text), cover_image_path,
  category_id (FK), author_id (FK users), published_at (nullable → draft),
  is_featured (bool), timestamps.
- **pages** — id, title, slug (unique, mis. `home`, `tentang`, `kontak`), meta_title,
  meta_description, is_published, timestamps.
- **page_blocks** — id, page_id (FK), type (enum: `hero|rich_text|image|stats|cta|quote`),
  payload (JSON — judul/teks/gambar/statistik/link per tipe), sort_order, timestamps.
- **resources** (e-resource) — id, title, slug, description, file_path (PDF),
  category_id (nullable), is_published, downloads_count (int default 0), timestamps.
- **courses** (katalog SP1) — id, title, slug, description, level
  (`dasar|menengah|lanjut`), is_published, timestamps. (lessons/quizzes = SP4.)

Relasi: articles→users (author), articles→categories; pages→page_blocks (1:N, urut);
resource→categories (nullable). Semua koleksi publik wajib filter `is_published`.

## 5. Autentikasi & Role (SP1)

- Login staf federasi via panel Filament (halaman `/admin/login`). Tidak ada login
  publik di SP1.
- `super_admin`: akses penuh (termasuk kelola pengguna staf).
- `editor`: CRUD konten (article/page/resource/course/organization), tanpa kelola user.
- Penegakan: `canAccessPanel()` + kebijakan per resource (Filament authorization).
- Role disimpan sebagai kolom enum; middleware `role:` untuk rute admin.

## 6. Situs Publik — Rute & Konten

| Rute | Halaman | Sumber |
|---|---|---|
| `/` | Home | `pages` slug=home (page-builder) |
| `/tentang` | Tentang Kami | `pages` slug=tentang |
| `/berita` `/berita/{slug}` | Indeks + detail artikel | `articles` (published, terbaru dulu; kategori) |
| `/sba` `/sba/{slug}` | Direktori + profil SBA | `organizations` (is_published) |
| `/e-resource` | Pustaka unduhan | `resources` (published) |
| `/e-learning` | Katalog kursus | `courses` (published) |
| `/kontak` | Kontak sekretariat | `pages` slug=kontak + config |

Layout publik: header (nav + logo federasi), footer (kontak, tautan); desain brand
Swiss Brutalist Green versi web (token warna diadaptasi dari SPMKB, diganti warna
identitas resmi federasi saat aset tiba).

Renderer page-blocks: satu Blade partial per tipe block (`resources/views/blocks/*.blade.php`)
+ komponen kecil; payload divalidasi skema per tipe.

## 7. Keamanan & Kualitas

- SP1 **tanpa PII**: semua konten publik. Upload hanya oleh staf login.
- Validasi: aturan Form Request / Filament; slug unik; file resource hanya PDF; ukuran
  dibatasi; jalur penyimpanan `storage/app/public` + symlink.
- `published_at` masa depan = draft (artikel); `is_published` untuk lainnya.
- Meta SEO: title/description per halaman; `sitemap`/`robots` dasar.
- Testing (PHPUnit): rute publik 200 dengan seeder; rute admin 302/403 anonim & role
  salah; CRUD + aturan publish tiap resource; renderer tiap tipe block; slug unik.

## 8. Non-Goal & Keputusan yang Ditunda

- Nama/logo resmi federasi + warna (placeholder sampai pengurus kirim aset).
- Nama domain final; nama repo GitHub final (`fsbmm-website` sementara); visibilitas
  repo (SPMKB publik; repo baru bisa private — keputusan di scaffolding).
- Migrasi data SPM Kecap Bango: entri manual lewat UI (keputusan wawancara), di SP2/SP3.
- Deployment cPanel (docroot `public/`), `.env.example` siap MySQL.
- PHP/Composer lokal belum terpasang (menunggu instalasi di mesin dev).

## 9. Rujukan Sub-Proyek

- SP2 — akun SBA & dashboard: `users.organization_id`, role `sba_admin`, panel SBA,
  scope tenant wajib, overview federasi.
- SP3 — modul anggota per-SBA mengacu SPMKB (roster/profile, upah+iuran, absensi/event/
  komplain), kebijakan PII.
- SP4 — kursus: lessons, kuis, progres (learner = staf federasi & SBA).
