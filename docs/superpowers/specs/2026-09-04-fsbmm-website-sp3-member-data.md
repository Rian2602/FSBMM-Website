# FSBMM Website — Spec Desain SP3 (Data Anggota per-SBA + Kebijakan PII)

Tanggal: 2026-09-04 · Status: Draft untuk review · Proyek: fsbmm-website · Induk:
`docs/superpowers/specs/2026-09-03-fsbmm-website-sp1-design.md` (§9) dan
`docs/superpowers/specs/2026-09-03-fsbmm-website-sp2-sba-accounts-dashboard.md` (§10)
merujuk SP3. SP1 + SP2 sudah selesai dan hijau; dokumen ini merinci fase berikutnya.

## 1. Konteks & Tujuan

SP1 membangun situs publik + CMS federasi. SP2 mengaktifkan akun & panel SBA
(`/panel-sba`) dengan tenant scoping wajib, tapi organisasi masih berupa "kerangka
profil publik" — belum ada data anggota sungguhan. `Organization.member_count`
masih **angka input manual federasi** (catatan SP2 §9: "member_count tetap input
federasi sampai SP3 menyediakan angka riil").

SP3 mengisi kerangka itu: setiap SBA mendapat **modul data anggota sendiri** di
`/panel-sba` — roster/profil anggota, iuran bulanan, kegiatan & absensi, dan
pengaduan — mengacu pada domain SPMKB (`spm-kecap-bango`, rujukan desain resmi
sejak SP1 §1) tapi **disederhanakan** untuk cakupan SP3 (lihat §2 Keluar).

Ini adalah SP pertama yang menyimpan **PII sungguhan** (NIK, nama, alamat,
tanggal lahir, upah anggota) di `fsbmm-website`. Kebijakan PII (§8) karena itu
adalah bagian inti dari spec ini, bukan tambahan.

## 2. Ruang Lingkup SP3 (In / Out)

**Masuk:**
- Empat tabel tenant baru — `members` (roster/profil), `dues` (iuran bulanan),
  `events` (agenda/kegiatan) + `attendances` (absensi per kegiatan),
  `complaints` (pengaduan) — semua ber-`organization_id` langsung (pola
  shared-schema row-level yang sudah disiapkan sejak SP1 §3, ditegaskan lagi SP2 §10).
- Resource Filament baru di `/panel-sba`: **Anggota** (`MemberResource`),
  **Iuran** (`DuesResource`), **Kegiatan** (`EventResource` + relation manager
  Absensi), **Pengaduan** (`ComplaintResource`) — semua ter-scope ke organisasi
  milik `sba_admin` yang login, meniru pola `getEloquentQuery()` ter-scope dari
  `Sba/Resources/OrganizationResource` (SP2).
- **`member_count` jadi angka riil**: begitu sebuah SBA punya baris `members`,
  `Organization.member_count` dihitung otomatis dari jumlah anggota berstatus
  aktif (observer, lihat §4) dan field itu terkunci (disabled, bukan dihapus)
  di form admin/SBA. SBA yang belum onboarding SP3 (nol anggota) tetap memakai
  angka manual federasi seperti sebelumnya — tidak ada migrasi paksa.
- Widget agregat: ringkasan di dashboard SBA (jumlah anggota aktif, iuran
  terkumpul bulan berjalan, pengaduan terbuka — organisasi sendiri) dan widget
  agregat **lintas-SBA** di dashboard admin federasi (total anggota aktif,
  total iuran terkumpul bulan berjalan, total pengaduan terbuka) — **tanpa**
  menampilkan baris data individu (lihat §8, kebijakan minimalisasi PII).
- Pengaman hapus organisasi diperluas: organisasi dengan anggota tidak bisa
  dihapus (mengikuti pola guard `hasSbaAccounts()` SP2, ditambah
  `hasMembers()`).
- Seeder data demo (anggota, iuran, kegiatan, absensi, pengaduan) untuk
  organisasi demo SP1, dokumentasi README.

**Keluar (di luar SP3, tidak dijadwalkan):**
- Kartu anggota (PDF/cetak), foto profil anggota, skenario negosiasi upah
  multi-tahap (`Pertemuan1..5` ala SPMKB) — SP3 hanya menyimpan satu field
  referensi `basic_salary` per anggota, bukan sistem negosiasi.
- Buku kas (ledger), surat-menyurat, sanksi, pesangon — modul SPMKB lain yang
  **tidak disebut** dalam cakupan SP3 di spec SP1/SP2; jika diprioritaskan,
  jadi SP baru (lihat §10).
- Impor massal CSV/Excel dari roster SPMKB lama — entri manual lewat UI
  (keputusan yang sama seperti migrasi data SPMKB di SP1 §8).
- Akses federasi ke baris data anggota/iuran/pengaduan individual — federasi
  hanya melihat **agregat** (§8). Ini keputusan sadar, bukan keterbatasan teknis.
- Notifikasi email/SMS untuk perubahan status pengaduan.
- Self-registration anggota, login publik/anggota — non-goal SP1/SP2 tetap berlaku.
- Enkripsi kolom PII di level database (lihat §8 untuk alasan).

## 3. Arsitektur

- Tetap satu aplikasi Laravel 12, satu DB, dua panel Filament (tidak ada panel
  ketiga). Semua resource baru masuk **panel `sba`** yang sudah ada (SP2), di
  namespace `app/Filament/Sba/Resources/`.
- Tenant scoping mengikuti pola SP2 yang sudah terbukti: **bukan** global
  scope (akan merusak apa pun yang federasi query lintas-organisasi), melainkan
  `getEloquentQuery()` ter-scope per resource ke
  `Filament::auth()->user()->organization_id`, ditambah setiap tabel baru
  membawa kolom `organization_id` sendiri (bukan hanya diturunkan lewat relasi
  `member_id`/`event_id`) — konsisten dengan keputusan "shared-schema,
  row-level" yang sudah ditetapkan sejak SP1 §3 dan ditegaskan SP2 §3.
  Redundansi ini disengaja: query tenant-scope jadi satu kolom `where`, bukan
  `whereHas` berlapis, dan jadi lapisan pertahanan tambahan (index terpisah,
  gampang diaudit).
- Federasi (`/admin`) **tidak** mendapat resource baru untuk `members`/`dues`/
  `attendances`/`complaints` — hanya widget agregat (angka, bukan tabel baris).
  Ini prinsip minimalisasi akses PII (§8), bukan keterbatasan Filament.
- `Organization.member_count` sinkron otomatis lewat **model observer** pada
  `Member` (create/update/delete/restore yang mengubah status aktif), bukan
  command terjadwal — perubahan roster harus langsung tercermin di direktori
  publik begitu federasi men-toggle `is_published` (situs publik tidak berubah
  sama sekali; tetap membaca kolom `member_count` seperti SP1/SP2).

### Struktur direktori (rencana)
```
database/migrations/2026_09_04_0000{09..13}_create_{members,dues,events,attendances,complaints}_table.php
app/Models/{Member,Due,Event,Attendance,Complaint}.php
app/Models/Organization.php               (+ members(), hasMembers(), syncMemberCount())
app/Observers/MemberObserver.php
app/Providers/AppServiceProvider.php      (register MemberObserver)
app/Filament/Sba/Resources/MemberResource.php + Pages/{List,Create,Edit}Member.php
app/Filament/Sba/Resources/DuesResource.php + Pages/{List,Create,Edit}Dues.php
app/Filament/Sba/Resources/EventResource.php + Pages/{List,Create,Edit}Event.php
app/Filament/Sba/Resources/EventResource/RelationManagers/AttendancesRelationManager.php
app/Filament/Sba/Resources/ComplaintResource.php + Pages/{List,Create,Edit}Complaint.php
app/Filament/Sba/Widgets/OrganizationSummaryWidget.php   (extend, SP2 file)
app/Filament/Widgets/MemberDataOverviewWidget.php         (BARU — agregat admin)
resources/views/filament/widgets/member-data-overview.blade.php
app/Filament/Resources/OrganizationResource.php           (+ delete guard hasMembers())
database/factories/{Member,Due,Event,Attendance,Complaint}Factory.php
database/seeders/MemberDataSeeder.php
tests/Feature/{SbaMemberTest,SbaDuesTest,SbaEventAttendanceTest,SbaComplaintTest,
              MemberCountSyncTest,MemberDataOverviewTest}.php
```

## 4. Model Data (tabel SP3)

- **members** — id, `organization_id` (FK `organizations`, `restrictOnDelete` —
  DB menolak hapus organisasi yang masih punya anggota, jaring pengaman di
  bawah guard UI), `nik` (string, wajib), `name`, `gender`
  (`L`|`P`, nullable), `birthplace`, `birthdate` (nullable date), `address`
  (nullable text), `department`, `position` (nullable string), `basic_salary`
  (nullable decimal — referensi upah, **bukan** sistem negosiasi bertahap),
  `join_date` (nullable date), `education` (nullable string), `status`
  (`aktif`|`nonaktif`, default `aktif`), `deleted_at` (SoftDeletes — riwayat
  iuran/absensi/pengaduan anggota yang keluar tetap utuh), timestamps. Unique
  komposit `(organization_id, nik)` — NIK unik **per organisasi**, bukan global
  (setiap SBA adalah perusahaan berbeda).
- **dues** (iuran) — id, `organization_id` (FK, `restrictOnDelete`), `member_id`
  (FK `members`, `cascadeOnDelete`), `period` (string `YYYY-MM`), `amount`
  (decimal), `paid_at` (date), `recorded_by` (nullable FK `users`, siapa yang
  mencatat), timestamps. Unique `(member_id, period)` — satu baris = satu bulan
  lunas (baris tidak ada = belum lunas, dihitung, bukan disimpan — pola yang
  sama seperti SPMKB `dues.id = nik-bulan`).
- **events** (kegiatan/agenda) — id, `organization_id` (FK, `restrictOnDelete`),
  `title`, `event_date` (date), `description` (nullable text), timestamps.
- **attendances** (absensi) — id, `organization_id` (FK, `restrictOnDelete`,
  redundan dengan `event`/`member` demi scoping satu-kolom), `event_id` (FK
  `events`, `cascadeOnDelete`), `member_id` (FK `members`, `cascadeOnDelete`),
  `status` (`hadir`|`izin`|`tidak_hadir`), `note` (nullable text), timestamps.
  Unique `(event_id, member_id)`.
- **complaints** (pengaduan) — id, `organization_id` (FK, `restrictOnDelete`),
  `member_id` (nullable FK `members`, `nullOnDelete` — pengaduan boleh dicatat
  tanpa terkait baris anggota tertentu), `reporter_name` (string, wajib —
  nama pelapor, teks bebas agar tetap bisa dicatat walau `member_id` kosong),
  `title`, `description` (text), `status`
  (`baru`|`diproses`|`selesai`, default `baru`), `submitted_at` (date),
  `resolved_at` (nullable date), `handled_by` (nullable FK `users`), timestamps.

Relasi: `Organization::members()`/`dues()`/`events()`/`attendances()`/
`complaints()` (hasMany); `Member::organization()`, `Member::dues()`,
`Member::attendances()`, `Member::complaints()`; `Event::attendances()`;
`Due::member()`; `Attendance::event()`, `Attendance::member()`. Semua kolom
`organization_id` di-index.

**Sinkronisasi `member_count`:** `MemberObserver` (created/updated/deleted/
restored) memanggil `$member->organization->syncMemberCount()` →
`update(['member_count' => $this->members()->where('status', 'aktif')->count()])`
via `saveQuietly()`. `Organization::hasMembers(): bool` dipakai guard hapus
(§6). Field `member_count` di form `OrganizationResource` (admin) jadi
`disabled()` (dengan helper text) **hanya jika** `$organization->hasMembers()`
— organisasi yang belum punya anggota tetap bisa diisi manual seperti SP1/SP2.

## 5. Autentikasi & Role (SP3)

Tidak ada role baru. Matriks akses (perluasan tabel SP2 §5):

| Siapa | Bisa login di | Lihat data anggota | Catatan |
|---|---|---|---|
| `super_admin` | `/admin` | **agregat saja** (widget) | tidak ada resource `members`/`dues`/dst. di `/admin` |
| `editor` | `/admin` | tidak sama sekali | tidak berubah dari SP1/SP2 |
| `sba_admin` | `/panel-sba` | penuh, **hanya organisasinya sendiri** | CRUD Anggota/Iuran/Kegiatan/Absensi/Pengaduan |

- Penegakan tetap `canAccessPanel()` per panel (tidak berubah dari SP2) +
  `getEloquentQuery()` ter-scope per resource baru (pola identik
  `Sba/Resources/OrganizationResource` SP2) — URL edit/lihat data anggota
  organisasi lain → 404, diuji per resource (§8).

## 6. Panel SBA (`/panel-sba`) — Fitur Baru

- **Anggota** (`MemberResource`): tabel roster (nama, NIK, departemen, jabatan,
  status) + form penuh (semua field §4 kecuali yang federasi-kontrol — tidak
  ada field federasi di sini, roster murni milik SBA). Filter status
  aktif/nonaktif. Tanpa foto/kartu anggota (non-goal §2).
- **Iuran** (`DuesResource`): tabel pembayaran per bulan (anggota, periode,
  jumlah, tanggal bayar) + form catat pembayaran (select anggota ter-scope ke
  organisasi sendiri, periode `YYYY-MM`, jumlah). Tidak ada baris "belum
  lunas" tersimpan — status belum-bayar dihitung dari absennya baris pada
  periode berjalan, ditampilkan lewat widget ringkasan (§7), bukan resource
  terpisah (YAGNI — SP3 tidak mereplikasi laporan tunggakan detail ala SPMKB;
  bisa jadi SP lanjutan bila dibutuhkan).
- **Kegiatan** (`EventResource` + relation manager **Absensi**): buat agenda
  kegiatan, lalu catat kehadiran per anggota langsung dari halaman detail
  kegiatan (hadir/izin/tidak hadir + catatan).
- **Pengaduan** (`ComplaintResource`): catat pengaduan (nama pelapor, judul,
  keterangan, opsional terkait anggota), ubah status baru → diproses →
  selesai. Tidak ada alur eskalasi ke federasi (non-goal §2, konsisten dengan
  keputusan "tanpa alur persetujuan" SP2 §9).
- **Dashboard SBA** (perluasan `OrganizationSummaryWidget` SP2): tambah kartu
  jumlah anggota aktif, iuran terkumpul bulan berjalan, jumlah pengaduan
  berstatus buka (`baru`+`diproses`) — organisasi sendiri saja.

## 7. Panel Admin (`/admin`) — Perubahan

- **Dashboard**: widget baru `MemberDataOverviewWidget` — kartu statistik
  **agregat lintas-SBA saja**: total anggota aktif (Σ `organizations.member_count`
  — tidak query tabel `members` sama sekali, cukup kolom yang sudah
  tersinkron), total iuran terkumpul bulan berjalan (`SUM(dues.amount)` untuk
  `period` = bulan ini, agregat SQL — tidak menampilkan baris per anggota),
  total pengaduan terbuka lintas-SBA (`COUNT` `status != 'selesai'`). **Tidak
  ada** tabel/daftar baris individu di widget ini — beda sengaja dari
  `SbaAccountsOverviewWidget` SP2 (yang menampilkan daftar akun, karena akun
  bukan PII sensitif seperti data anggota).
- **OrganizationResource**: guard hapus (tunggal & massal, pola SP2 §7) diperluas
  — organisasi dengan `hasSbaAccounts()` **atau** `hasMembers()` tidak bisa
  dihapus. Field `member_count` jadi `disabled()` begitu `hasMembers()` true
  (§4), dengan helper text "Dihitung otomatis dari data anggota SBA (SP3)".

## 8. Keamanan, Kebijakan PII & Kualitas

Ini adalah SP pertama yang menyimpan PII sungguhan — kebijakan berikut mengikat
semua pekerjaan SP3 dan sub-SP berikutnya yang menyentuh tabel ini:

- **Minimalisasi akses federasi**: staf federasi (`super_admin`/`editor`)
  **tidak pernah** melihat baris `members`/`dues`/`attendances`/`complaints`
  individual — hanya angka agregat (§7). Ini keputusan desain sadar (bukan
  bug untuk diperbaiki nanti) karena federasi tidak punya kepentingan
  operasional harian atas data anggota internal tiap SBA; kalau kebutuhan itu
  muncul, itu perubahan kebijakan yang butuh spec baru, bukan penambahan diam-diam.
- **Tanpa eksposur publik**: tidak ada rute publik yang menyentuh tabel SP3
  sama sekali (beda dari `organizations`, yang memang punya direktori publik).
  Situs publik SP1/SP2 tidak berubah.
- **Tanpa enkripsi kolom**: SP3 sengaja **tidak** menambah enkripsi
  field-level (mis. `encrypted` cast Laravel) untuk NIK/alamat/tanggal lahir —
  konsisten dengan preseden SPMKB (kontrol akses + auth, bukan enkripsi
  kolom) dan menghindari kompleksitas migrasi/pencarian terenkripsi yang tidak
  diminta wawancara manapun. Kontrol akses (tenant scoping + auth panel) adalah
  lapisan pertahanan utama. Dicatat di sini secara eksplisit supaya ini
  keputusan, bukan kelalaian.
- **Soft delete anggota**: `Member` pakai `SoftDeletes` — riwayat iuran/absensi/
  pengaduan anggota yang keluar tetap koheren; anggota yang dihapus tidak lagi
  dihitung `member_count` (query sync memakai `whereNull('deleted_at')`
  implisit lewat Eloquent) tapi baris historisnya tidak hilang.
- **Validasi**: `nik` wajib + unik per organisasi; `period` iuran format
  `YYYY-MM` (regex); `amount` numerik ≥ 0; `birthdate` tidak boleh di masa
  depan; slug/format lain mengikuti konvensi Form Request/Filament yang sudah
  ada.
- **Guard hapus organisasi**: diperluas (§7) — DB `restrictOnDelete` pada FK
  `organization_id` di kelima tabel baru adalah jaring pengaman kedua di
  belakang guard UI (pola yang sama seperti `nullOnDelete` SP2 untuk
  `users.organization_id`, tapi `restrict` bukan `null` karena PII tidak boleh
  "tergantung" tanpa organisasi pemilik).
- **Testing (PHPUnit feature, wajib)**: setiap resource baru diuji dengan pola
  identik SP2 (`SbaOrganizationTest`) — 403/redirect anonim, akses ter-scope
  ke organisasi sendiri, **404 lintas-tenant** untuk record organisasi lain,
  validasi field wajib. Tambahan khusus SP3: `MemberCountSyncTest` (observer
  menghitung ulang dengan benar pada create/update-status/soft-delete/restore),
  `MemberDataOverviewTest` (widget admin **tidak** melakukan query ke tabel
  `members`/`complaints` mentah — assert lewat query log atau assert isi
  respons hanya berisi angka, tidak ada nama/NIK), guard hapus organisasi
  dengan anggota.
- Lint `vendor/bin/pint` sebelum commit; seluruh suite SP1+SP2 harus tetap hijau.

## 9. Non-Goal & Keputusan yang Ditunda

- Kartu anggota, foto profil, skenario negosiasi upah bertahap, buku kas,
  surat, sanksi, pesangon — lihat §2.
- Impor massal roster lama — entri manual (§2, konsisten SP1 §8).
- Akses federasi ke baris PII individual — agregat saja, keputusan sadar (§8).
- Enkripsi kolom PII — tidak ditambahkan, kontrol akses jadi lapisan utama (§8).
- Laporan tunggakan iuran detail / notifikasi keterlambatan — dihitung
  sekilas di widget ringkasan saja, bukan modul laporan terpisah.
- Alur eskalasi pengaduan ke federasi, notifikasi email/SMS status pengaduan.
- Self-registration/login publik anggota — non-goal SP1/SP2 tetap berlaku.

## 10. Rujukan Sub-Proyek

- SP4 — authoring e-learning (pelajaran, kuis, progres) — **tidak bergantung**
  pada SP3, bisa dikerjakan sebelum atau sesudah tanpa urutan wajib.
- SP-lanjutan (belum dijadwalkan, opsional bila diprioritaskan pengurus
  federasi): kartu anggota/cetak, buku kas per-SBA, surat-menyurat, sanksi,
  pesangon, laporan tunggakan iuran detail — semua modul SPMKB yang sengaja
  dikeluarkan dari SP3 (§2). Kalau salah satu diprioritaskan, buat spec baru
  yang merujuk balik ke dokumen ini, jangan diam-diam ditambahkan sebagai
  "perbaikan kecil" ke SP3.
