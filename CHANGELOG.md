# Changelog

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/) dan
versi mengikuti [Semantic Versioning](https://semver.org/). Tanggal
`YYYY-MM-DD`.

Rilis di-tag dengan `git tag vX.Y.Z`. Perubahan besar dipecah per kategori:
`Added`, `Changed`, `Fixed`, `Removed`, `Security`.

## [1.0.0] - 2026-09-11

Rilis pertama — seluruh peta jalan SP1–SP5 selesai + enhancement fase 1–4.

### Added
- **SP1 — landasan public site**: page-builder (blok konten, structural slugs
  `home`/`tentang`/`kontak`, rich editor staff-only), berita & penjadwalan
  publish, direktori SBA publik, e-resource library (download bertanda tangan),
  beranda data-driven, sitemap/robots.
- **SP2 — dua panel, tiga peran**: `/admin` (staf: `super_admin`/`editor`) dan
  `/panel-sba` (admins SBA per organisasi), tenant scoping di lapisan panel,
  `Organization.member_count` ter-sinkron lewat observer.
- **SP3 — data anggota per SBA**: `members`/`dues`/`events`/`attendances`/
  `complaints` + kontrol resource, kebijakan minimalisasi PII di sisi federasi
  (federation hanya melihat agregat).
- **SP4 — e-learning federation-global**: authoring kursus/lessons/quiz/final
  quiz di `/admin`, "Kursus Saya" di kedua panel, grading server-side
  (`QuizEngine`), progres & kelulusan turunan (`LearningProgress`), laporan
  belajar super-admin.
- **SP5 — pelaporan operasional / ekspor aman / kartu anggota**: 4 halaman
  laporan tenant-scoped SBA (`MemberReportPage`, `DuesReportPage`,
  `AttendanceReportPage`, `ComplaintReportPage`), ekspor CSV/XLSX via
  `app/Exports/*` + `ReportExport`, laporan federasi super-admin,
  `MemberCardService` + cetak kartu + verifikasi publik
  `/verifikasi/kartu/{token}`, `ReportingTest` suite.
- **Enhancement fase 1–4**: chart block + dark mode + drawer mobile,
  tombol ekspor SBA + `GlobalSearchWidget` + `NotificationAlertWidget` +
  `FederationReportGenerator`, `MemberCardPage` & verifikasi kartu,
  **sertifikat e-learning** (`CourseCertificate`/`CertificateService` + cetak +
  `/verifikasi/sertifikat/{token}`), **audit trail append-only PII-free**
  (`AuditLog`/`AuditLogger` + halaman admin/SBA), **bulk actions** SBA, dan
  fallback ekspor PDF ke HTML print-ready.

### Security
- Halaman verifikasi kartu & sertifikat dideklarasikan di atas catch-all
  `{page:slug}` (spesifikasi §9.10).
- Download e-resource & ekspor memakai URL bertanda tangan (`signed`) +
  pengecekan organisasi + realpath containment; artefak kedaluwarsa dalam satu
  jam.
- Kebijakan PII: federasi aggregate-only; deskripsi audit trail bebas PII.
- `Organization.description` disanitasi (non-staf); konten rich editor & lesson
  hanya ditulis staf (raw trusted HTML).

[1.0.0]: https://github.com/fsbmm/fsbmm-website/releases/tag/v1.0.0