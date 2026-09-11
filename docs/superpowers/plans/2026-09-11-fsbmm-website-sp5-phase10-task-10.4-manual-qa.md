# SP5 Phase 10 — Task 10.4 Manual QA Checklist

Verifikasi di browser lokal (`php artisan serve` / `composer dev`, URL `http://localhost:8000`).

Prep CLI sudah divalidasi (environment dinormalisasi): `composer test` **407 passed / 1398 assertions**, `pint --test` clean, `npm run build` clean, `migrate:fresh --seed` clean (3 SBA / 15 members / 2 kartu seeder), `storage:link` tersedia, 1 kartu aktif dibuat untuk QA.

Live smoke (server `php artisan serve --port=8091`): `/`, `/tentang`, `/kontak`, `/berita`, `/sba`, `/e-resource`, `/e-learning`, `/robots.txt`, `/sitemap.xml` → semua **200**. `/verifikasi/kartu/{token}` (kartu QA) → **200**, menampilkan status Aktif + nomor kartu, tanpa NIK; token salah → 200 dengan pesan generik (tanpa enumerasi). Item panel/login/klik tetap butuh verifikasi manual di bawah.

## Kredensial (dari seeder, fallback local)

| Area | Login | Password |
|---|---|---|
| `/admin` | `admin@fsbmm.test` (super_admin) | `password` (dari `FSBMM_ADMIN_PASSWORD`) |
| `/panel-sba` | `pengurus@spm-kecap-bango.fsbmm.test` (SBA org 1) | `password` (fallback bila `FSBMM_SBA_PASSWORD` tak di-set) |

## 1. Federasi — `/admin` → Ringkasan Operasional Widget

- [ ] Login super_admin di `/admin`, dashboard tampil.
- [ ] Widget "Ringkasan Operasional Federasi" menampilkan 8 metrik: Jumlah SBA, Total anggota aktif, Iuran bulan berjalan, Jumlah kegiatan, Peserta hadir, Pengaduan terbuka, Kartu aktif, Kartu dicabut.
- [ ] Tablo per-SBA (Distribusi Anggota per SBA) + tombol "Unduh Laporan PDF" `/admin/generate-report`.
- [ ] Tidak ada akses ke data member individual/PII di sisi federasi.

## 2. SBA — `/panel-sba` → Laporan (4 report)

- [ ] Login `pengurus@spm-kecap-bango.fsbmm.test` di `/panel-sba`.
- [ ] Nav "Laporan" berisi 4 page: Anggota, Iuran, Kehadiran, Pengaduan.
- [ ] Laporan Anggota: stats total/aktif/nonaktif benar, filter status/departemen/jabatan bekerja server-side, tabel NIK/Nama/Departemen/Jabatan/Status/Tgl Bergabung.
- [ ] Laporan Iuran: agregat jumlah/total/rata-rata + filter periode, daftar anggota belum bayar.
- [ ] Laporan Kehadiran: rekap per-kegiatan, filter event/tanggal.
- [ ] Laporan Pengaduan: status baru/diproses/selesai + filter.
- [ ] Data LAPORAN hanya milik org sendiri (tidak ada data SBA lain).

## 3. SBA — Export + Kartu Anggota

- [ ] Tiap laporn export CSV dan XLSX (member/dues/attendance/complaint), file terunduh & isi benar (CSV/XLSX konsisten).
- [ ] Nav "Kartu Anggota": stats total/aktif/dicabut/tanpa kartu; filter anggota & status kartu (date 2026-09-11).
- [ ] Aksi "Terbitkan Kartu Baru" untuk anggota aktif → kartu muncul di tabel; "Cetak" membuka kartu (PDF bila wkhtmltopdf tersedia, lalu HTML fallback) dengan QR `data:image/svg+xml;base64`; tanpa NIK/foto/berlaku-s.d.
- [ ] "Cabut" kartu → status DICABUT, print/issue tidak tersedia lagi untuk kartu itu; "Ganti Kartu" menerbitkan kartu baru.
- [ ] `Audit Trail` SBA mencatat issue/revoke/export (tanpa PII).

## 4. Publik — `/verifikasi/kartu/{token}`

Kartu aktif QA: `FSBMM-2026-EYX4NWOP`
`http://localhost:8000/verifikasi/kartu/6ab0093e8eea945106be3d5c878cee99cfa2273337a5d6271ba52b2b75886995`

- [ ] Halaman menampilkan data minimal: nama anggota, org/SBA, nomor kartu, status — TANPA NIK/alamat/gaji/iuran/anekdot pengaduan.
- [ ] Token salah/asi → pesan generik (tanpa enumerasi).
- [ ] Kartu dicabut → status tidak aktif.
- [ ] Route berada ABOVE `{page:slug}` (tidak tertelan catch-all).

## 5. Publik — Site utama

- [ ] `/` (home), `/tentang`, `/kontak` render dengan header/isi.
- [ ] `/berita` + detail artikel; `/sba` direktori organisasi terbit; `/e-resource` daftar + unduh; `/e-learning` katalog publik.
- [ ] Dark mode toggle & mobile drawer bekerja; `data-reveal` animasi halus.
- [ ] `/robots.txt` & `/sitemap.xml` 200.
- [ ] Login link header mengarah ke panel (Filament named routes) dan `/logout` berfungsi.

## Status

- [ ] Semua item di atas terverifikasi (diisi reviewer saat QA manual)