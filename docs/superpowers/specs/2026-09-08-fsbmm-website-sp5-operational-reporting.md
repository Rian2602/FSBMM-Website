# FSBMM Website — Spec Desain SP5 (Operational Reporting, Data Export & Kartu Anggota)

Tanggal: 2026-09-08 · Status: Final Draft · Proyek: fsbmm-website · Induk:
`docs/superpowers/specs/2026-09-04-fsbmm-website-sp3-member-data.md` (§9) dan
`docs/superpowers/specs/2026-09-04-fsbmm-website-sp4-elearning-authoring.md` (§10)
merujuk SP5. SP1–SP4 sudah selesai dan hijau; dokumen ini merinci fase berikutnya.

## 1. Konteks & Tujuan

SP1 membangun situs publik + CMS federasi. SP2 mengaktifkan akun & panel SBA
(`/panel-sba`) dengan tenant scoping. SP3 menambah modul data anggota per-SBA
(roster, iuran, kegiatan/absensi, pengaduan) dengan kebijakan PII. SP4
menambah authoring e-learning dan progres pembelajaran.

Data SP3 dan SP4 sudah tersedia, tapi masih berfungsi terutama sebagai data
transaksi dan tampilan CRUD. SP5 menambahkan tiga kemampuan operasional utama:

1. **Reporting** — laporan terfilter untuk SBA + dashboard aggregate federasi
2. **Secure Data Export** — CSV/XLSX dengan column whitelist + tenant isolation
3. **Kartu Anggota** — kartu digital dengan QR verification

Tujuan akhir: membuat sistem mampu mengubah data operasional menjadi informasi
manajerial, laporan administratif, file kerja, kartu anggota, dan verifikasi kartu.

**SP5 tidak** memperluas sistem menjadi modul keuangan, surat-menyarat, hubungan
industrial, atau e-learning lanjutan.

## 2. Ruang Lingkup SP5 (In / Out)

**Masuk:**
- P0 — SBA reporting (4 laporan: anggota, iuran, kegiatan/absensi, pengaduan)
- P0 — Federation aggregate dashboard widget (diperluas dari `MemberDataOverviewWidget`)
- P0 — Secure export (CSV/XLSX) untuk semua laporan SBA
- P1 — Member card system (`member_cards` table, issue/revoke/reissue, card number, QR)
- P1 — Public card verification endpoint (`/verifikasi/kartu/{token}`)
- P1 — Print/PDF card generation
- Seeder data demo untuk member_cards

**Keluar (di luar SP5):**
- Foto anggota (PII tambahan, file storage, authorization, lifecycle — deferred)
- Buku kas, payroll, surat-menyarat, sanksi, pesangon
- Public member directory, public member login, public member registration
- Notifikasi email/SMS
- Generic audit log framework
- SCORM, sertifikat e-learning, advanced analytics

## 3. Baseline yang Tidak Boleh Diubah

SP5 bekerja di atas SP1–SP4. Tidak boleh mengubah:
- Struktur role (`super_admin`, `editor`, `sba_admin`)
- Panel architecture (`/admin`, `/panel-sba`)
- Tenant architecture (SBA panel scope, bukan global Eloquent scope)
- Model `Member`, `Due`, `Event`, `Attendance`, `Complaint`
- E-learning system
- Public site dan catalog

SP2 menetapkan tenant scoping pada layer SBA (bukan global scope). SP3
menetapkan federation staff tidak mendapatkan akses individual terhadap PII.
Kedua prinsip ini tetap berlaku.

## 4. Arsitektur

Tetap:
- Laravel 12, PHP 8.3+, Filament 3
- MySQL production, SQLite development/test
- PHPUnit, Pint, Vite/Tailwind

Tetap hanya dua panel: `/admin` dan `/panel-sba`. Tidak ada panel baru.

### Library yang Direkomendasikan

| Kebutuhan | Library | Alasan |
|---|---|---|
| XLSX export | `openspout/openspout` | Streaming, low memory, LGPL |
| PDF card | `barryvdh/laravel-snappy` (wkhtmltopdf) | HTML-to-PDF, QR render reliable |
| QR code | `bacon/bacon-qr-code` | Battle-tested, SVG/PNG output |

### Database Changes

#### Migration: `create_member_cards_table`

```sql
member_cards
  id                  bigIncrements
  organization_id     foreignId → organizations  (index)
  member_id           foreignId → members         (index)
  card_number         string    unique
  verification_token  string    unique
  status              enum('aktif','dicabut')     (index)
  issued_at           timestamp
  revoked_at          timestamp nullable
  revocation_reason   string nullable
  created_by          foreignId → users
  created_at          timestamp
  updated_at          timestamp
```

**`organization_id` wajib ada** — diperlukan untuk:
- Tenant scoping query (SBA admin hanya melihat kartu organisasinya)
- Federation aggregate card count (tanpa join ke members)
- Consistency dengan semua tabel SP3 yang ber-`organization_id` langsung

**Indexes:**
- `unique(card_number)`
- `unique(verification_token)`
- `index(member_id)`
- `index(organization_id)`
- `index(status)`

**Constraint:** Satu anggota hanya boleh memiliki satu kartu aktif. Ini di-enforce
di service layer (business rule, bukan DB constraint — karena histori kartu
tetap dipertahankan).

### Struktur Direktori (Rencana)

```
database/migrations/2026_09_08_000001_create_member_cards_table.php
app/Models/MemberCard.php
app/Support/MemberCardService.php
app/Support/MemberCardVerificationService.php
app/Filament/Sba/Pages/MemberReportPage.php
app/Filament/Sba/Pages/DuesReportPage.php
app/Filament/Sba/Pages/AttendanceReportPage.php
app/Filament/Sba/Pages/ComplaintReportPage.php
app/Filament/Sba/Pages/MemberCardPage.php
app/Filament/Widgets/FederationOperationsWidget.php
app/Exports/MemberExport.php
app/Exports/DuesExport.php
app/Exports/AttendanceExport.php
app/Exports/ComplaintExport.php
app/Http/Controllers/CardVerificationController.php
routes/web.php  (+ /verifikasi/kartu/{token} SEBELUM catch-all)
database/seeders/MemberCardSeeder.php
```

## 5. Role Policy

Role tetap: `super_admin`, `editor`, `sba_admin`.

### `sba_admin`

Boleh:
- Melihat data anggota SBA sendiri
- Membuat/mengubah/mencabut kartu anggota SBA sendiri
- Melihat laporan SBA sendiri
- Export data SBA sendiri

Tidak boleh:
- Melihat SBA lain
- Export SBA lain
- Mengakses laporan federasi
- Melihat PII organisasi lain

### `super_admin`

Boleh:
- Melihat aggregate federation reporting
- Melihat Ringkasan Operasional Federasi (widget baru)
- Mengakses kartu sesuai kebijakan federasi
- Operasi lintas organisasi untuk administrasi federasi

### `editor`

Default:
- Aggregate federation reporting: **tidak** (widget federasi hanya `super_admin` — §7.4)
- Individual member PII: **tidak**
- Export seluruh anggota: **tidak**
- Administrative card operation lintas SBA: **tidak**

**Klarifikasi:** `MemberDataOverviewWidget::canView()` saat ini hanya
`isSuperAdmin()`. Widget baru "Ringkasan Operasional Federasi" (§11) juga
hanya untuk `super_admin`. Akun ber-role tunggal (const `ROLE_*` pada `User`);
`sba_admin` adalah role terpisah, bukan akses tambahan bagi `editor` — `editor`
tidak dapat mengakses `/panel-sba`.

Jika kebutuhan operasional di masa depan memerlukan akses editor terhadap PII,
itu harus menjadi perubahan policy tersendiri.

## 6. P0 — SBA Reporting

### 6.1 Navigasi

Tambahkan menu **Laporan** di `/panel-sba`. Implementasi sebagai Filament
custom pages (bukan resource) — pola yang sama dengan `MyCoursesPage` di SP4.
Setiap laporan adalah satu Livewire page dengan filter form + data table + summary cards.

### 6.2 Laporan Anggota

**Filter:**
- status (aktif/nonaktif/all)
- departemen
- jabatan
- pendidikan
- jenis kelamin
- rentang tanggal bergabung

**Output:**
- Total anggota, anggota aktif, anggota tidak aktif (summary cards)
- Distribusi per departemen (breakdown)
- Distribusi per jabatan (breakdown)
- Daftar anggota (paginated table, tenant-scoped)

### 6.3 Laporan Iuran

**Filter:**
- periode bulan (single)
- rentang periode (start-end)

**Output:**
- Jumlah pembayaran
- Total nominal
- Rata-rata nominal
- Jumlah anggota aktif
- Jumlah anggota yang belum memiliki record iuran pada periode tersebut

"Belum membayar" adalah **derived state**:

```
active members (status=aktif)
  − members dengan record dues pada periode
  = belum tercatat membayar
```

SP5 tidak mengubah struktur tabel `dues` untuk menghasilkan laporan ini.

### 6.4 Laporan Kegiatan & Absensi

**Filter:**
- kegiatan (single event)
- rentang tanggal

**Output:**
- Jumlah kegiatan
- Total peserta
- Hadir / Izin / Tidak hadir
- Persentase kehadiran

### 6.5 Laporan Pengaduan

**Filter:**
- status (baru/diproses/selesai/all)
- rentang tanggal

**Output:**
- Total pengaduan
- Breakdown per status (baru, diproses, selesai)
- Jumlah unresolved/open

Detail pengaduan hanya ditampilkan kepada role yang memang memiliki akses.
Federation aggregate report tidak menampilkan isi pengaduan.

### 6.6 Query Pattern

Semua laporan menggunakan aggregate SQL:

```sql
COUNT, SUM, GROUP BY
```

Bukan:

```php
Member::all() → foreach → count di PHP
```

Filter berjalan di server-side (query scope). Pagination pada daftar detail.
Eager loading relasi yang diperlukan. Cegah N+1.

## 7. P0 — Federation Dashboard

Widget baru di `/admin`: **Ringkasan Operasional Federasi**
(`FederationOperationsWidget`).

### 7.1 Metrik

| Metrik | Source | Notes |
|---|---|---|
| Jumlah SBA | `organizations.count` | Sudah ada di `SbaAccountsOverviewWidget` |
| Total anggota aktif | `SUM(member_count)` | Sudah ada di `MemberDataOverviewWidget` |
| Total iuran periode berjalan | `dues.where(period, current)->sum(amount)` | Sudah ada |
| Jumlah kegiatan | `events.count` | **Baru** — COUNT, bukan individual rows |
| Jumlah peserta (hadir) | `attendances.where(status, 'hadir').count` | **Baru** — COUNT only |
| Pengaduan terbuka | `complaints.where(status, '!=', 'selesai').count` | Sudah ada |
| Kartu aktif | `member_cards.where(status, 'aktif').count` | **Baru (SP5)** |
| Kartu dicabut | `member_cards.where(status, 'dicabut').count` | **Baru (SP5)** |

### 7.2 PII Boundary — Klarifikasi

> **Catatan:** metrik "Total anggota tidak aktif" **tidak** disertakan. Tidak ada
> kolom `organizations.total` (`organizations` hanya menyimpan `member_count` yang
> menghitung anggota **aktif** saja via `MemberObserver::syncMemberCount`).
> Menghitung anggota nonaktif menuntut query `members.status` dari sisi federasi —
> dilarang PII policy (federation tidak boleh menyentuh tabel `members`).

`COUNT(events)` dan `COUNT(attendances)` **tidak melanggar PII policy** karena:
- Hanya mengembalikan angka aggregate (bukan baris individual)
- Tidak menampilkan nama anggota, NIK, atau data personal
- Konsisten dengan pola `SUM(member_count)` dan `COUNT(complaints)` yang sudah ada

Yang **tetap dilarang** di federation aggregate:
- NIK, alamat, tanggal lahir, gaji individual
- Isi pengaduan
- Nama anggota
- Nomor telepon

### 7.3 Per-SBA Breakdown

Widget menampilkan tabel aggregate per SBA:

| SBA | Anggota Aktif | Iuran | Kegiatan | Pengaduan Terbuka | Kartu Aktif |
|---|---|---|---|---|---|
| SBA A | 120 | Rp 6.000.000 | 5 | 3 | 115 |
| SBA B | 85 | Rp 4.250.000 | 2 | 1 | 80 |

Data per SBA hanya agregat. Tidak menampilkan nama anggota, NIK, atau data individual.

### 7.4 Implementation

Widget baru (`FederationOperationsWidget`) atau extend `MemberDataOverviewWidget`.
`canView()`: `isSuperAdmin()` — konsisten dengan widget existing.
`$isLazy = false` — agar konten ada di initial HTTP response (untuk testing).

## 8. P0 — Secure Export

### 8.1 Format

- CSV (native `fputcsv`)
- XLSX (`openspout/openspout` — streaming, low memory)

### 8.2 Column Whitelist

Setiap exporter menggunakan **explicit column whitelist**:

```php
// Contoh MemberExport
$columns = [
    'name'       => 'Nama',
    'nik'        => 'NIK',
    'gender'     => 'Jenis Kelamin',
    'birthplace' => 'Tempat Lahir',
    'birthdate'  => 'Tanggal Lahir',
    'address'    => 'Alamat',
    'department' => 'Departemen',
    'position'   => 'Jabatan',
    'basic_salary' => 'Upah Dasar',
    'join_date'  => 'Tanggal Bergabung',
    'education'  => 'Pendidikan',
    'status'     => 'Status',
];
```

Prinsip: **kolom database baru tidak otomatis masuk export.**

### 8.3 Dilarang Export

- Password, verification token, session data
- Internal authentication data
- Data organizasi milik SBA lain
- Internal system secrets

### 8.4 Flow

```
generate (queue job untuk dataset besar)
  ↓
temporary private storage (disk 'private', TTL 1 jam)
  ↓
authorized download (signed URL atau stream response)
  ↓
delete
```

- File export tidak menjadi public asset
- Gunakan private disk
- Jangan gunakan NIK sebagai filename
- Export besar menggunakan chunk/lazy processing via queue

### 8.5 Tenant Isolation

Export SBA A hanya berisi data SBA A. Test harus memastikan nama anggota SBA B
tidak terdapat dalam output.

### 8.6 Authorization

Export hanya tersedia pada laporan yang memiliki hak export. Authorization
dilakukan server-side (bukan hanya tombol UI). `sba_admin` hanya export
organisasinya sendiri.

## 9. P1 — Kartu Anggota

### 9.1 Nama Produk

**Kartu Anggota FSBMM**

Kartu bukan pengganti KTP atau identitas resmi negara.

### 9.2 Model Kartu

Tabel `member_cards` (§4). Model `MemberCard` dengan relasi:
- `member()` → belongsTo Member
- `organization()` → belongsTo Organization
- `creator()` → belongsTo User (created_by)

### 9.3 Card Number

Format: `FSBMM-YYYY-XXXXXXXX`

- `YYYY`: tahun penerbitan
- `XXXXXXXX`: 8 karakter random alphanumeric (huruf besar + digit)
- Unik (database unique constraint + service-level check)
- Bukan NIK, bukan auto-increment, tidak mengandung organization ID
- Tidak mudah ditebak

**Collision handling:** Generate ulang jika card_number sudah ada (max 3 attempts,
lalu throw exception).

### 9.4 Verification Token

- Random 32-byte hex string (`bin2hex(random_bytes(32))` atau `Str::random(64)`)
- Opaque — tidak berisi NIK, member ID, atau organization ID
- Unik (database unique constraint)
- Tidak dapat ditebak

### 9.5 QR Code

QR Code mengarah ke: `/verifikasi/kartu/{token}`

Library: `bacon/bacon-qr-code` — output SVG untuk web, PNG untuk PDF.

### 9.6 Card Lifecycle

```
Anggota aktif
  ↓
Terbitkan Kartu (issue)
  ↓
Kartu Aktif
  ↓
Dicabut (revoke) — dengan alasan
  ↓
Terbitkan Kartu Baru (reissue)
```

**Constraint:** Satu anggota hanya boleh memiliki satu kartu aktif.
Di-enforce di `MemberCardService::issue()` — sebelum issue, revoke kartu aktif
yang sudah ada (jika ada).

Histori kartu tetap dipertahankan. Card #1 yang revoked tidak dihapus.

### 9.7 Kapan Kartu Dapat Diterbitkan

- Anggota aktif → dapat diterbitkan
- Anggota inactive → tidak dapat diterbitkan kartu baru
- Kartu existing untuk anggota inactive → verification menjadi tidak aktif
  (status card mengikuti status member di runtime, bukan di DB)

Perubahan status anggota tidak menghapus histori kartu.

### 9.8 Service Layer

**`App\Support\MemberCardService`**

Tanggung jawab:
- `issue(Member $member, User $creator): MemberCard`
- `revoke(MemberCard $card, string $reason, User $actor): MemberCard`
- `reissue(MemberCard $oldCard, User $actor): MemberCard`
- `generateCardNumber(): string`
- `generateVerificationToken(): string`
- `resolveCurrentCard(Member $member): ?MemberCard`
- `validateToken(string $token): ?MemberCard`

Business logic tidak boleh tersebar di Livewire/Blade.

**`App\Support\MemberCardVerificationService`** (opsional, untuk controller tipis)

Tanggung jawab:
- Lookup token
- Validasi status
- Menghasilkan public-safe verification DTO

### 9.9 Print & PDF

**Front:**
- Logo FSBMM
- Nama federasi
- Nama SBA
- Nama anggota
- Nomor kartu
- QR Code

**Back:**
- Pernyataan kartu adalah kartu anggota organisasi
- Informasi verifikasi
- Tanggal penerbitan
- Kontak federasi/SBA

Foto anggota **tidak termasuk SP5** (§2 Keluar).

PDF on-demand (tidak disimpan permanen). Library: `barryvdh/laravel-snappy`.
Responsive card view di browser (desktop + mobile).

### 9.10 Public Card Verification

**Route:** `/verifikasi/kartu/{token}`

**PENTING:** Route ini **harus** dideclare **SEBELUM** catch-all
`Route::get('/{page:slug}', ...)` di `routes/web.php`. Jika diletakkan setelah
catch-all, route akan di-shadow dan mengembalikan 404.

```php
// routes/web.php — urutan yang benar:
Route::get('/verifikasi/kartu/{token}', [CardVerificationController::class, 'verify'])
    ->name('cards.verify');
// ... collection routes lainnya ...
Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');
```

**Response — kartu valid:**
```
KARTU ANGGOTA VALID
Nama: Budi Santoso
SBA: SPM Contoh
Status: Aktif
Nomor Kartu: FSBMM-2026-XXXXXXXX
```

**Response — token invalid:**
```
Kartu tidak ditemukan / tidak valid.
```

**Response — kartu revoked:**
```
Kartu tidak aktif.
```

**Tidak menampilkan:** NIK lengkap, alamat, tanggal lahir, gaji, iuran,
absensi, pengaduan. Tidak memberikan detail yang membantu enumeration.

## 10. PII Boundary

### Zona 1 — Public

Hanya:
- Public organization data
- Public content (articles, eresources, courses)
- Minimal card verification (§9.10)

> **Catatan:** verifikasi kartu via token adalah satu-satunya jalur disclosure nama
> anggota + status kartu ke publik (§9.10). Token 32-byte acak berperan sebagai
> capability — yang memegang token (dari QR di kartu fisik) boleh melihat nama,
> SBA, status, dan nomor kartu; tanpa token tidak ada akses.
> NIK, alamat, tanggal lahir, gaji, iuran, absensi, dan isi pengaduan tidak pernah
> keluar dari zona publik.

### Zona 2 — SBA

PII anggota milik organisasinya sendiri. Full CRUD + export + card management.

### Zona 3 — Federation

Aggregate member information. Individual member PII tidak otomatis naik ke `/admin`.

| Data | Public | SBA | Federation |
|---|---|---|---|
| Nama anggota | ✅ (hanya via token valid) | ✅ | ❌ |
| NIK | ❌ | ✅ | ❌ |
| Alamat | ❌ | ✅ | ❌ |
| Gaji | ❌ | ✅ | ❌ |
| Iuran detail | ❌ | ✅ | ❌ (aggregate saja) |
| Anggota count | ❌ | ✅ | ✅ (aggregate) |
| Pengaduan isi | ❌ | ✅ | ❌ |
| Pengaduan count | ❌ | ✅ | ✅ (aggregate) |
| Kartu status | ✅ (via token only) | ✅ | ✅ (aggregate count) |

## 11. Testing

### 11.1 `ReportingTest`

Wajib:
- Member count (total, active, inactive)
- Dues aggregate (sum, count, average)
- Attendance aggregate (hadir, izin, tidak hadir, percentage)
- Complaint aggregate (total, per status)
- Filter bekerja server-side
- Pagination
- Tenant isolation (SBA A tidak melihat data SBA B)

### 11.2 `FederationReportingTest`

Wajib:
- Aggregate seluruh SBA
- Tidak menampilkan NIK
- Tidak menampilkan alamat
- Tidak menampilkan birthdate
- Tidak menampilkan salary individual
- Role visibility (super_admin only)

### 11.3 `ExportTest`

Wajib:
- CSV member, XLSX member
- CSV dues, XLSX dues
- Attendance export
- Complaint export
- Filter export (export hanya data yang difilter)
- Tenant isolation (SBA A export tidak mengandung record SBA B)
- Authorization (sba_admin hanya export org sendiri)
- Explicit column whitelist (kolom baru tidak otomatis masuk)

Test wajib membuat SBA A + SBA B, memastikan export SBA A tidak mengandung
record SBA B.

### 11.4 `MemberCardTest`

Wajib:
- Issue kartu baru
- Unique card number
- Unique verification token
- Active card (hanya satu per member)
- Revoke kartu
- Reissue kartu (kartu lama revoked, kartu baru aktif)
- Histori kartu (revoked card tetap ada di DB)
- Duplicate active prevention (issue kedua auto-revoke yang pertama)
- Member authorization (sba_admin hanya untuk org sendiri)
- Cross-tenant denial (sba_admin A tidak bisa issue kartu untuk member SBA B)
- Inactive member tidak bisa issue kartu baru

### 11.5 `CardVerificationTest`

Wajib:
- Valid token → data minimal
- Invalid token → generic error
- Revoked card → "Kartu tidak aktif"
- Inactive member → card verification tidak aktif
- Minimal public response (tidak ada NIK, alamat, salary, iuran, pengaduan)
- Tidak ada member enumeration

### 11.6 Security Regression Matrix

| Actor | Target | Expected |
|---|---|---|
| SBA A | Member SBA A | Allow |
| SBA A | Member SBA B | 404/Forbidden |
| SBA A | Card SBA A | Allow |
| SBA A | Card SBA B | 404/Forbidden |
| SBA A | Export SBA A | Allow |
| SBA A | Export SBA B | Deny |
| Anonymous | Member | Deny |
| Anonymous | Export | Deny |
| Anonymous | Card verification | Allow (minimal data) |
| Editor | Individual member PII | Deny by default |
| Editor | Federation aggregate | Deny (widget hanya `super_admin` — §5/§7.4) |
| SBA | Federation report | Deny |

## 12. Public Route Policy

SP5 menambahkan hanya:

```
/verifikasi/kartu/{token}
```

Tidak menambahkan:
```
/member
/members
/sba/{slug}/members
/public-members
```

Tidak ada public member directory.

## 13. Navigasi

### SBA Panel

Tambahkan:
- **Laporan** (submenu: Anggota, Iuran, Kegiatan & Absensi, Pengaduan)
- **Kartu Anggota** (dekat modul Anggota)

### Federation Panel

Tambahkan:
- **Ringkasan Operasional** (widget di dashboard)

Tidak menambahkan resource federation untuk seluruh data anggota.

## 14. Performance & Scalability

**Reporting:**
- Aggregate SQL (`COUNT`, `SUM`, `GROUP BY`)
- Indexed filters
- Pagination
- Eager loading (anti N+1)

**Export:**
- Chunking via queue job untuk dataset > 1000 rows
- Lazy collections (`Cursor` / `chunkById`)
- Streaming response untuk CSV
- Openspout streaming writer untuk XLSX

**Card verification:**
- Query berdasarkan indexed `verification_token`
- Hanya select field yang dibutuhkan
- Tidak memuat seluruh member relation

## 15. Accessibility & UX

Semua fitur wajib:
- Bahasa Indonesia
- Keyboard accessible
- Mobile friendly
- Empty state
- Loading state
- Error state
- Confirmation untuk revoke card
- Feedback setelah export (toast, pola yang sudah ada di `site.js`)

## 16. Error Handling

Tidak expose:
- SQL error
- Internal IDs
- Stack trace
- Token
- Filesystem path

Pesan publik: generik. Pesan panel: informatif tapi tidak membocorkan data
tenant lain.

## 17. Audit

SP5 tidak membuat generic audit-log framework. Lifecycle kartu tersimpan di
`member_cards` (issue/revoke/reissue timestamps + `created_by`). Untuk export,
sistem mencatat event security minimum jika fondasi logging mendukungnya.

## 18. Kompatibilitas

SP5 tidak boleh merusak:

**SP1:** Public site, CMS, articles, organizations, resources, courses, SEO
**SP2:** `/admin`, `/panel-sba`, role access, tenant scoping, SBA account management
**SP3:** Member CRUD, dues, events, attendance, complaints, member_count, aggregate federation visibility, PII boundary
**SP4:** Course authoring, learner pages, quiz engine, progress, learning report

## 19. Definition of Done

### P0 — Reporting

- [ ] SBA member report
- [ ] SBA dues report
- [ ] SBA attendance report
- [ ] SBA complaint report
- [ ] Federation aggregate dashboard (`FederationOperationsWidget`)
- [ ] Filter bekerja server-side
- [ ] Tenant isolation teruji

### P0 — Export

- [ ] CSV + XLSX untuk member, dues, attendance, complaint
- [ ] Explicit column whitelist
- [ ] Private/temporary file handling
- [ ] Tenant isolation test
- [ ] Authorization test

### P1 — Card

- [ ] `member_cards` table + model
- [ ] Issue, revoke, reissue
- [ ] Card history
- [ ] Unique card number (collision handling)
- [ ] Unique verification token
- [ ] Print/PDF (snappy)
- [ ] QR verification (bacon-qr-code)
- [ ] Public-safe verification endpoint

### P1 — Security

- [ ] Cross-tenant denial
- [ ] Public PII protection
- [ ] Editor PII restriction
- [ ] Export authorization
- [ ] Card authorization
- [ ] Token protection

### Quality

- [ ] Seluruh SP1–SP4 tests tetap green
- [ ] Seluruh SP5 tests green
- [ ] Pint clean
- [ ] npm build clean
- [ ] `php artisan migrate:fresh --seed` clean (termasuk MemberCardSeeder)
- [ ] Manual smoke seluruh critical flow

## 20. Final Verification

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint
npm run build
```

Manual smoke:
```
/admin  →  Ringkasan Operasional widget muncul
/panel-sba/.../laporan  →  4 laporan bisa diakses
/panel-sba/.../kartu  →  kartu bisa dikelola
/verifikasi/kartu/{token}  →  verifikasi minimal
/  →  public site normal
/sba  →  directory normal
/e-learning  →  katalog normal
```

Test dengan minimal dua organisasi (SBA A, SBA B) dan minimal:
- 2 active members, 1 inactive member
- 1 dues period
- 1 event + attendance records
- 1 complaint
- 1 active card, 1 revoked card

## 21. Acceptance Scenarios

### Scenario A — Reporting
SBA A login → laporan hanya menghitung members.organization_id = SBA_A. SBA B tidak muncul.

### Scenario B — Export
SBA A export anggota → file hanya berisi SBA A. Automated test memastikan nama anggota SBA B tidak ada.

### Scenario C — Card
SBA A menerbitkan kartu untuk anggota A1 → card number dibuat, token dibuat, histori tersimpan, kartu ditampilkan, QR dihasilkan.

### Scenario D — Public Verification
QR dibuka tanpa login → "Valid, Nama, SBA, Status, Nomor kartu". Tidak menampilkan NIK, alamat, atau PII lain.

### Scenario E — Revoke
Admin SBA mencabut kartu → QR lama tidak lagi aktif. Histori tetap tersimpan.

### Scenario F — Reissue
Kartu baru diterbitkan → Card A revoked, Card B active. Tidak ada dua kartu aktif.

## 22. Roadmap Setelah SP5

- **SP6** — Keuangan & Buku Kas SBA
- **SP7** — Administrasi & Persuratan
- **SP8** — Hubungan Industrial & Perlindungan Anggota
- **SP9** — E-Learning Expansion

## 23. Prinsip Final SP5

SP5 bertujuan membuat data SP3 dan SP4 **berguna dalam pekerjaan nyata**:

```
DATA → REPORT → EXPORT / DOCUMENT → OPERATION
```

Dengan security boundary:

```
PUBLIC  → minimal verification
SBA     → PII milik sendiri
FEDERATION → aggregate intelligence
```

> **Tidak ada fitur reporting, export, kartu, atau QR yang boleh menjadi jalur
> baru untuk membocorkan PII atau menembus tenant isolation.**

> **SP5 harus diselesaikan sebagai fase operasional yang terukur sebelum sistem
> diperluas ke keuangan, persuratan, hubungan industrial, atau e-learning lanjutan.**
