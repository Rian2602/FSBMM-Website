# SP5 Phase 10 — Evaluation Plan: Tasks 10.1–10.5

Status: draft (menunggu approval sebelum dijalankan sebagai gate).

Berlaku atas plan kanonik `2026-09-08-fsbmm-website-sp5-operational-reporting.md`
(Phase 10: Final Security + Regression) dan checklist Task 10.4
(`2026-09-11-fsbmm-website-sp5-phase10-task-10.4-manual-qa.md`). Mengikuti
format gate Phase 6/7/8/9: evaluasi berbasis bukti atas artifact committed,
direview subagent independent, verdict, dan anotasi di plan kanonik.

---

## 1. Tujuan

Menilai kepatuhan hasil pengerjaan Task 10.1–10.5 terhadap definisi selesai
DoD SP5 (P0/P1/Quality), sehingga Phase 10 — dan SP5 secara keseluruhan —
memenuhi syarat ditandai selesai di plan kanonik dan knowledge.md.

Output gate:
- Verdict **READY** / **NOT READY** dengan daftar Critical / Important / Minor.
- Confirmasi (atau penolakan) status "SP5 ✅" yang baru saya tulis di README +
  knowledge pada Task 10.5.
- Baris DoD diplan kanonik di-tick + anotasi gate `(** evaluated @2026-09-11 ... **)`.

---

## 2. Lingkup evaluasi

Artifact per task (commit):

- **10.1** Security Regression Matrix — `bc5c3bf`
  `tests/Feature/Sp5SecurityRegressionTest.php` (matriks 12 baris) +
  fix pass-by-construction di `Sp5SecurityTest:182`.
- **10.2** Input Tampering — `713ebfc`
  `tests/Feature/Sp5InputTamperingTest.php` (7 test, 24 assertions) +
  anotasi deviasi di plan kanonik.
- **10.3** Full Regression Gate — `f797977`
  Bukti: composer test x2, pint, npm build, migrate:fresh --seed, kartu QA
  re-issued. 5 checklist ditick + anotasi.
- **10.4** Manual QA — `79ee924` (prep + checklist + live smoke)
  Checklist `2026-09-11-...task-10.4-manual-qa.md`: prep CLI selesai, **verifikasi
  browser belum dikonfirmasi** (item baris 61 belum ditick).
- **10.5** Documentation — `bbbb0ce`
  README scope SP5 + tick DoD Task 10.5 + anotasi. `knowledge.md` gitignored
  (artifact tooling) — edit lokal valid, tidak masuk commit.

Di luar lingkup (tidak dievaluasi di gate ini):
- Artifact Phase-4 uncommitted (certificates, audit trail, bulk action, PDF
  fallback) yang masih di working tree — punya gate tersendiri.
- Hal jaringan/akun eksternal yang tak tersedia di env dev (verifikasi visual
  rendering SVG data-URI di Qt WebKit/wkhtmltopdf — deferral yang sudah
  tercatat di Task 9.1).

---

## 3. Kriteria evaluasi (mapping ke DoD)

| DoD | Cara cek | Sumber bukti |
|-----|----------|--------------|
| P0 Reporting (7 item) | Baca page + widget; cek server-side filter & tenant isolation di kode/test | Phase 3–4 + ReportingTest (16) |
| P0 Export (5 item) | Whitelist kolom, storage privat, signed URL, org guard | ExportTest (28) + Sp5InputTamperingTest |
| P1 Card (7 item) | Lifecycle, history, collision, print, QR, verifikasi publik | MemberCardTest/PageTest/PrintTest + CardVerificationTest |
| P1 Security (6 item) | Matriks 12 baris + 7 test tampering | Sp5SecurityRegressionTest + Sp5InputTamperingTest |
| Quality | Suite penuh, pint, npm, seed | composer test x2 + pint + npm build + migrate:fresh --seed |
| Manual QA (Task 10.4) | Checklist baris 61 belum tick → **gate bergantung user** | file checklist |

---

## 4. Prosedur eksekusi

1. **Prepare**: catat starting commit; pastikan tak ada drift baru di working
   tree selama evaluasi (48 file uncommitted = Phase-4 + AGENTS.md — jangan
   disentuh/dicampur).
2. **Evidence run**:
   - `composer test` (suite penuh, diharapkan 407 passed / ~1398 assertions);
   - `vendor/bin/pint --test`;
   - `npm run build`;
   - `php artisan migrate:fresh --seed` + `composer test` lagi (regresi pasca-seed).
   - Catat angka persis + kartu QA yang di-reissue untuk tautan QA 10.4.
3. **Kode review terfokus** (oleh subagent general, terpisah dari pengerja):
   - 10.1 matriks: setiap baris assert terhadap perilaku sebenarnya; tak ada
     assert pass-by-construction yang tersisa (full-panel test API benar).
   - 10.2 tampering: tiap test benar menguji "pihak luar digagalkan", bukan
     sekadar zero-row; guard server-side (bukan hanya UI/query scope).
   - 10.3 bukti gate masih representatif (angka assertion final).
   - 10.5 docs: README tak bertentangan kode; klaim "SP5 ✅" perlu `checklist`
     10.4 pending — evaluasi apakah penandaan tersebut sah.
4. **Manually gate Task 10.4**: cek status checklist. Kalau belum diverifikasi
   user, verdict gate **GB (gate blocked at 10.4)** — DoD tetap tak di-tick
   penuh; SP5 bukan sepenuhnya selesai sampai verifikasi browser selesai.
5. **Verdict + anotasi**: tick DoD P0/P1/Quality yang lulus; anotasi
   `(** evaluated @2026-09-11: PHASE 10 GATE ... **)` di bawah Task 10.5;
   perbaikan Critical (kalau ada) langsung diperbaiki + dikomit terpisah juga
   dicatat.
6. **Komit**: gate doc + anotasi plan kanonik dalam satu commit `docs(sp5):`.

---

## 5. Anak-anak risiko / hal yang divalidasi silang

- Nomor suite "407/1398" di gate Phase 9 berbeda dgn Task 10.3 (407) — pastikan
  angka konsisten di anotasi gate akhir.
- Working tree bermuatan Phase-4 uncommitted; submission commit hanya boleh
  menampilkan file gate + plan kanonik — jangan sekali pun stage file Phase-4.
- `knowledge.md` gitignored: claim di README tentang "SP5 ✅" tidak boleh
  bertentangan dengan belum-lulusnya manual QA.
- Task 10.4 checklist: kredensial/token kartu diregenerasi saat fresh seed —
  file checklist harus update token sebelum QA manual.

---

## 6. Output

Satu dokumen gate (file baru
`docs/superpowers/plans/2026-09-11-fsbmm-website-sp5-phase10-evaluation-gate.md`):
- Ringkasan bukti (per task: commit + status).
- Hasil evidence run (angka).
- Hasil review subagent (per task checklist → OK/Deviasi, daftar temuan).
- Verdict RESOLUSI AKHIR (READY / NOT READY / BLOCKED dgn alasan 10.4).
- Anotasi di plan kanonik + tick DoD yang layak.

---

## 7. Ekspektasi status akhir

Jika semua test + review lulus kecuali Task 10.4 (manual browser QA):
- Verdict: **READY untuk semua artefak backend; BLOCKED pada Manual QA**
  (satu-satunya item DoD tak ter-tick).
- Roadmap SP5 diknowledge/README ditulis "✅ backend, Manual QA 10.4 pending"
  dan dibiarkan sampai user konfirmasi checklist.
- Commit: gate + anotasi kanonik: `docs(sp5): phase 10 evaluation gate`.