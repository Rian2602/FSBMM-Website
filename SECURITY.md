# Security Policy

## Supported Versions

Proyek ini mengikuti Semantic Versioning. Patch keamanan dirilis untuk versi
yang masih didukung (latest minor dalam major terbaru).

| Version | Supported          |
|---------|--------------------|
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

Kami wajib menjaga kerahasiaan laporan keamanan. JANGAN laporkan kerentanan
lewat isu publik, media sosial, atau forum umum — kerentanan aktif yang
diekspos berisiko eksploitasi sebelum patch siap.

Kirim detail lengkap sebagai berikut:

- Email: **ganti dengan alamat keamanan resmi saat deploy** (contoh placeholder
  saja — alamat nyata ditetapkan pemilik repo di `.env.example`, jangan kirim
  ke alamat `*.test` yang non-aktif). Untuk laporan pra-deploy gunakan jalur
  langsung ke pemelihara (kontak di profil git/README).
- Subjek: `[SECURITY] <ringkasan singkat>`
- Lampirkan: versi/commit yang terpengaruh, langkah reproduksi, dampak, dan —
  bila ada — saran perbaikan (tanpa mengekspos data produksi/PII).

Tim akan merespons dalam **5 hari kerja** dengan rencana perbaikan. Bug
dikoordinasikan sebagai *embargo release*: fix dirilis di commit + tag + rilis
prioritas, lalu baru didisklosurkan setelah pengguna punya kesempatan upgrade.

## Domain Keamanan Repo Ini

- **Kebijakan PII**: federasi hanya melihat agregat — anggota/iuran/absensi/
  pengaduan di sisi `/admin` hanya lewat widget agregat. Jangan menambah resource
  atau kolom PII individu di sisi federasi, dan jangan menghapus no-PII test.
- **Audit trail**: append-only dan wajib bebas PII (nama/NIK/alamat/gaji/teks
  pengaduan dilarang).
- **Panel boundary**: role tidak boleh menyeberang panel (`/admin` vs
  `/panel-sba`); `User::canAccessPanel()` berpatokan pada id panel.
- **Trusted HTML** hanya untuk konten staf (artikel/pages/lesson); semua input
  non-staf di-escape. `Organization.description` tersanitasi di model layer.
- **Unduhan/ekspor**: signed URL + pengecekan organisasi + realpath containment;
  artefak kedaluwarsa. Jangan matikan proteksi ini.
- **Kartu & sertifikat**: token verifikasi hanya memaparkan data minimal;
  rute verifikasi dideklarasikan di atas catch-all `{page:slug}`.
- **Kredensial**: akun admin berasal dari `FSBMM_ADMIN_*`/`FSBMM_SBA_*` env.
  Ganti password pra-produksi; jangan commit password asli.

Lampirkan konteks plan/spec pada laporan bila relevan — repo ini memakai
`docs/superpowers/specs|plans` sebagai kanon pengembangan.