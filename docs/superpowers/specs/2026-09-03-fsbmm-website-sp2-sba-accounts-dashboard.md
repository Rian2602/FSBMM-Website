# FSBMM Website — Spec Desain SP2 (Akun SBA, Panel SBA, Tenant Scoping, Overview Federasi)

Tanggal: 2026-09-03 · Status: Draft untuk review · Proyek: fsbmm-website · Induk:
`docs/superpowers/specs/2026-09-03-fsbmm-website-sp1-design.md` (§9 merujuk SP2).
Implementasi SP1 sudah selesai; dokumen ini merinci fase berikutnya.

## 1. Konteks & Tujuan

SP1 menghasilkan situs publik data-driven + CMS federasi (panel `/admin` dengan role
`super_admin`/`editor`) dan tabel `organizations` sebagai direktori publik SBA
sekaligus **fondasi multi-tenant** (shared-schema, row-level) yang belum diaktifkan.

SP2 mengaktifkan lapisan akses organisasi:

1. **Akun SBA** — federasi (super admin) membuatkan akun untuk pengurus tiap SBA.
   Tidak ada self-registration (hasil wawancara SP1 tetap berlaku).
2. **Panel SBA** — area login sendiri bagi pengurus SBA untuk melihat ringkasan dan
   **mengelola profil publik organisasinya** (nama, logo, deskripsi, kontak).
3. **Tenant scoping wajib** — pengurus SBA hanya bisa mengakses data organisasinya
   sendiri; staf federasi tidak bisa masuk panel SBA dan sebaliknya.
4. **Overview federasi** — super admin melihat rekap akun SBA lintas organisasi.

Keputusan lingkup (konfirmasi 2026-09-03): SP2 = dashboard + **edit profil SBA
sendiri** (bukan sekadar baca-saja, dan tanpa authoring artikel oleh SBA).

## 2. Ruang Lingkup SP2 (In / Out)

**Masuk:**
- Migrasi `users.organization_id` (nullable FK → `organizations`, `nullOnDelete`)
  + role baru `sba_admin` (kolom string tetap, tanpa paket permission).
- Pembuatan akun SBA oleh **super admin federasi** via `UserResource` di panel
  `/admin` (role `sba_admin` + pilih organisasi; validasi wajib organisasi).
- Panel Filament kedua **`/panel-sba`** (id `sba`): login sendiri, dashboard +
  widget ringkasan organisasi, halaman **Profil Organisasi** (edit profil publik
  organisasi miliknya), halaman profil/password bawaan Filament (`->profile()`).
- Tenant scoping: gerbang `canAccessPanel()` per panel + query/resource panel SBA
  yang hanya menjangkau organisasi milik user (edit organisasi lain → 404).
- Overview federasi: widget dashboard admin yang menampilkan jumlah akun SBA,
  jumlah organisasi, dan daftar akun SBA (nama, email, organisasi, dibuat).
- Kontrol federasi: `slug`, `is_published`, `member_count` tetap hanya di panel
  `/admin` (slug = URL publik stabil; publikasi = keputusan federasi;
  `member_count` agregat publik, angka riil dari SP3).
- Seeder demo akun pengurus SBA (per organisasi demo SP1) + dokumentasi README.
- Pengaman penghapusan: organisasi yang masih punya akun SBA tidak bisa dihapus
  dari panel admin (akun harus dipindah/dihapus dulu); DB `nullOnDelete` sebagai
  jaring pengaman.

**Keluar (fase berikutnya):**
- SP3: data anggota per-SBA (roster, upah/iuran, absensi/event/komplain, PII).
- SP4: authoring kursus & progres.
- Di luar SP2: artikel oleh SBA (`articles.organization_id` belum ada), alur
  persetujuan publikasi/notifikasi, email/undangan, self-registration, login
  publik, fitur perpesanan, kolom `organization_id` pada tabel konten lain.

## 3. Arsitektur

- Satu aplikasi Laravel 12, satu DB (MySQL prod / SQLite dev+test), **dua panel
  Filament** berbagi guard `web` dan tabel `users`:
  - `/admin` (id `admin`) — staf federasi (`super_admin`, `editor`), tidak berubah.
  - `/panel-sba` (id `sba`) — pengurus SBA (`sba_admin`).
- **Alasan path `panel-sba`** (bukan `sba`): rute publik `/sba` (indeks direktori)
  dan `/sba/{organization:slug}` sudah dipakai situs publik; panel kedua yang
  memakai path `sba` akan bertabrakan dengan direktori publik.
- Role tetap (string enum) + `FilamentUser::canAccessPanel(Panel $panel)` yang
  **membran per id panel**:
  - `admin` → hanya `super_admin`/`editor`;
  - `sba` → hanya `sba_admin` **dan** `organization_id` terisi.
- Tenant scoping SP2 diterapkan **di lapisan panel SBA** (query resource + derivasi
  record dari user login), **bukan global scope** — scope global akan merusak situs
  publik yang tetap menampilkan semua organisasi terbit. Pola
  `getEloquentQuery()` ter-scope inilah yang akan ditiru SP3 untuk tabel anggota.
- Tidak ada tabel konten tenant baru di SP2: satu-satunya data tenant adalah baris
  `organizations` itu sendiri (profil) + `users.organization_id`.

### Struktur direktori (rencana)
```
app/Providers/Filament/
  AdminPanelProvider.php        (tidak berubah)
  SbaPanelProvider.php          (BARU — panel id sba, path panel-sba)
app/Filament/Sba/
  Resources/OrganizationResource.php + Pages/   (list + edit, profil org milik sendiri)
  Widgets/OrganizationSummaryWidget.php         (ringkasan org di dashboard SBA)
app/Filament/Widgets/
  SbaAccountsOverviewWidget.php                 (overview federasi di dashboard admin)
app/Models/User.php, Organization.php           (relasi + role + canAccessPanel)
database/migrations/2026_09_03_000008_add_organization_id_to_users_table.php
database/seeders/SbaAccountSeeder.php
tests/Feature/SbaTenantTest.php, SbaAuthTest.php, SbaOrganizationTest.php,
              SbaAccountManagementTest.php, FederationOverviewTest.php
```

## 4. Model Data

- **users** (perubahan) — `organization_id` (nullable FK `organizations`,
  `nullOnDelete`, ber-index). `role` kini tiga nilai:
  `super_admin` | `editor` | `sba_admin` (konstanta baru
  `User::ROLE_SBA_ADMIN = 'sba_admin'`). `organization_id` **wajib** bagi
  `sba_admin` (ditegakkan di UserResource + gerbang panel).
- Relasi baru: `User::organization()` (belongsTo), `Organization::users()`
  (hasMany). Helper `Organization` untuk cek keberadaan akun SBA
  (guard hapus organisasi).
- Tidak ada tabel/migrasi lain; schema SP1 tidak diubah.

## 5. Autentikasi & Role (SP2)

| Siapa | Bisa login di | Lihat | Catatan |
|---|---|---|---|
| `super_admin` | `/admin` | semua resource + overview SBA | kelola user termasuk akun SBA |
| `editor` | `/admin` | konten (tanpa kelola user) | tidak berubah dari SP1 |
| `sba_admin` | `/panel-sba` | dashboard + profil organisasinya | tidak bisa ke `/admin` |

- Pembuatan akun SBA: super admin di `UserResource` memilih role `sba_admin`,
  form menampilkan Select **Organisasi SBA** (wajib). Kata sandi awal dibuat
  federasi lalu dibagikan di luar sistem (belum ada infrastruktur email);
  pengurus mengganti sendiri lewat halaman profil (`->profile()`, termasuk kolom
  password bawaan Filament).
- Penegakan: `canAccessPanel()` per panel (403 untuk user terautentikasi yang
  tidak berhak; redirect ke login panel untuk anonim) + validasi form + query
  resource panel SBA. SBA tanpa `organization_id` tidak bisa login/masuk panel
  sampai super admin menautkannya.
- Pengaman anti-lockout SP1 (self-edit/self-delete super admin, super admin
  terakhir) tetap berlaku dan diperluas prinsipnya: super admin tidak boleh
  menghapus akun super admin terakhir; akun `sba_admin` tidak dilindungi
  (bisa diedit/dihapus super admin kapan saja).

## 6. Panel SBA (`/panel-sba`)

- **Login** terpisah (`/panel-sba/login`), logout via profil.
- **Dashboard**: widget ringkasan organisasi milik user (nama, status terbit,
  lokasi, tahun berdiri, jumlah anggota — agregat publik read-only).
- **Profil Organisasi** (resource `Organization` khusus panel SBA):
  - hanya record organisasi milik user (query ter-scope; URL edit organisasi lain
    → 404);
  - field yang bisa diedit: `name`, `company`, `logo_path`, `description`,
    `website`, `location`, `founded_year`;
  - field federasi (`slug`, `is_published`, `member_count`) **tidak** tampil;
  - tanpa create/delete (organisasi dibuat & dihapus federasi).
- **Profil/password**: `->profile()` bawaan Filament (nama, email, ganti
  password) — wajib karena sandi awal diketahui federasi.
- Perubahan profil langsung tercermin di direktori publik `/sba` begitu
  federasi menetapkan `is_published = true` (alur publikasi tetap milik federasi;
  tidak ada status "menunggu persetujuan" di SP2).

## 7. Panel Admin (`/admin`) — Perubahan

- **UserResource**: opsi role `sba_admin` ("Pengurus SBA"); Select
  `organization_id` (relasi, cari) muncul **hanya** saat role `sba_admin`, wajib,
  dan dinolkan otomatis bila role diubah bukan `sba_admin`; kolom tabel
  "Organisasi" + filter role/status menyusul; akses tetap super admin saja.
- **OrganizationResource**: tombol hapus (tunggal & massal) diblokir bila
  organisasi masih punya akun `sba_admin` (pesan: pindahkan/hapus akun dulu).
- **Dashboard admin**: widget baru **Overview SBA** — kartu statistik (jumlah
  akun SBA, organisasi terdaftar, organisasi terbit) + daftar akun SBA
  (nama, email, organisasi, tanggal dibuat) untuk super admin.

## 8. Keamanan & Kualitas

- Tanpa data PII baru di SP2; tetap semua konten publik/staf.
- Matriks akses wajib diuji: 403 anonim-terautentikasi-salah-role, 404 lintas
  tenant, form validation `organization_id` wajib untuk `sba_admin`.
- `nullOnDelete` pada FK: menghapus organisasi (lewat luar UI) men-detas akun
  (org → null) dan akun kehilangan akses panel sampai ditautkan ulang — bukan
  menghapus user.
- Testing PHPUnit (feature): relasi/migrasi, matriks `canAccessPanel`, auth panel
  SBA, CRUD profil SBA ter-scope + refleksi publik, manajemen akun SBA oleh
  super admin (Livewire), guard hapus organisasi, widget overview, dan smoke
  render halaman panel SBA.
- Lint `vendor/bin/pint` sebelum commit; seluruh suite SP1 harus tetap hijau.

## 9. Keputusan & Non-Goal (SP2)

- Path panel SBA `panel-sba` demi menghindari tabrakan rute publik `/sba`
  (keputusan arsitektur, lihat §3).
- Tidak ada email/undangan/notifikasi — kata sandi awal dibagikan manual.
- Tidak ada alur persetujuan publikasi profil (federasi men-toggle
  `is_published`); tidak ada kolom `is_pending`.
- Tidak ada self-registration, tidak ada login publik/anggota.
- `member_count` tetap input federasi sampai SP3 menyediakan angka riil.
- Admin tidak mendapat `->profile()` di SP2 (konsisten SP1; bisa menyusul).

## 10. Rujukan Sub-Proyek

- SP3 — modul anggota per-SBA (tabel tenant ber-`organization_id`, pola
  `getEloquentQuery()` ter-scope dari panel SBA SP2 ditiru), kebijakan PII.
- SP4 — authoring kursus & progres (learner = staf federasi & SBA).
- Artikel oleh SBA (opsional) — perlu `articles.organization_id` + kebijakan
  authoring; tidak dijadwalkan di SP2.
