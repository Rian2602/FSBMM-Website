# FSBMM Website — Spec Desain SP4 (Authoring & Progres E-Learning)

Tanggal: 2026-09-04 · Status: Draft untuk review · Proyek: fsbmm-website · Induk:
`docs/superpowers/specs/2026-09-03-fsbmm-website-sp1-design.md` (§7 model
`courses`, §9 rujukan SP4), `docs/superpowers/specs/2026-09-03-fsbmm-website-sp2-sba-accounts-dashboard.md`
(§10 rujukan SP4), dan `docs/superpowers/specs/2026-09-04-fsbmm-website-sp3-member-data.md`
(§10: SP4 tidak bergantung pada SP3). SP1 + SP2 + SP3 sudah selesai dan hijau;
dokumen ini merinci fase berikutnya.

## 1. Konteks & Tujuan

SP1 membangun situs publik + CMS federasi, termasuk **katalog kursus e-learning**
(model `Course`, halaman `/e-learning`) — tapi hanya katalog: daftar judul,
deskripsi, tingkat (`dasar|menengah|lanjut`), tanpa isi pelajaran, kuis, atau
progres. SP1 spec §7 mencatat `(lessons/quizzes = SP4.)`.

SP4 mengisi itu: **authoring kursus penuh** (pelajaran + kuis) oleh federasi dan
**area belajar + pelacakan progres** untuk staf federasi dan pengurus SBA. Kursus
yang tadinya katalog statis menjadi bagian dari program pengembangan kapasitas
internal federasi (rujukan SPMKB sejak SP1 §1).

Tidak seperti SP3, SP4 **tidak menyentuh PII anggota** — progres belajar adalah
data ringan (siapa, kursus mana, lesson/kuis apa yang sudah dilewati), bukan data
anggota SPMKB. Karena itu kebijakan PII SP3 (§8) **tidak** mengekang pelaporan
progres di federasi (lihat §7).

## 2. Ruang Lingkup SP4 (In / Out)

**Masuk:**
- Enam tabel baru: `course_lessons`, `course_quizzes`, `quiz_questions`,
  `quiz_options`, `course_progress`, `course_attempts` (detail §4) + satu kolom
  baru `courses.pass_threshold`.
- **Authoring kursus oleh federasi** (`/admin`, `super_admin`/`editor`): buat
  kursus, pelajaran (konten RichEditor), kuis per pelajaran + kuis akhir, soal
  pilihan ganda + kunci jawaban.
- **Area belajar** di **kedua panel** (`/admin` untuk super_admin/editor,
  `/panel-sba` untuk sba_admin): daftar "Kursus Saya" dengan progres, pembaca
  pelajaran, pengerjaan kuis (MCQ, auto-graded), penandaan lesson selesai.
- **Progres per user**: lesson (manual/hybrid) + kuis (skor, lulus/gagal),
  kursus "selesai" dihitung dari seluruh lesson selesai + kuis akhir lulus.
- **Pelaporan federasi** (super_admin, `/admin`): ringkasan kursus × user
  (siapa yang sudah / belum / sedang mengerjakan kursus mana).
- Demo kursus di seeder, dokumentasi README.

**Keluar (di luar SP4, tidak dijadwalkan):**
- Pendaftaran publik / login pembelajar independen — non-goal SP1/SP2/SP3 tetap
  berlaku; pembelajar terbatas pada akun `users` yang sudah ada.
- Tipe soal selain pilihan ganda (isian, benar/salah, essay / ulasan manual guru).
- Sertifikat / capaian tercetak, peringkat (leaderboard), gamification.
- Forum diskusi / komentar per pelajaran.
- Enkripsi isi kursus, DRM, atau pembatasan akses per kursus melewati
  `is_published` + keanggotaan panel.
- Impor massal materi kursus (SCORM/PDF/lain) — entri manual via authoring UI.
- Authoring oleh SBA (kursus milik organisasi SBA) — kursus tetap milik federasi.
- Rating/ulasan kursus oleh pembelajar.
- Notifikasi email/SMS saat kursus tersedia/kuis lulus.

## 3. Arsitektur

- Tetap satu aplikasi Laravel 12, satu DB, dua panel Filament (`admin`, `sba`) —
  **tidak ada panel ketiga**, tidak ada login baru. Semua akses lewat akun
  `users` yang sudah ada (konsisten SP1–SP3).
- **Authoring** (`CourseResource` + resource anak) hanya di `/admin`. `Course`
  tetap dikontrol federasi; tidak ada dimensi tenant di konten kursus (SBA hanya
  belajar, tidak mengarang).
- **Belajar** diledakkan sebagai **halaman Filament kustom** di masing-masing
  panel (`app/Filament/Admin/...` dan `app/Filament/Sba/...`), terpisah dari
  resource authoring — pola "dua area kerja" yang jelas. Situs publik `/e-learning`
  tetap katalog statis (landing); klik kursus di sana mengarahkan ke login panel
  (tidak membuat rute belajar publik).
- **Tenant scoping** hanya relevan untuk penegakan role (panel gate, pola SP2),
  bukan untuk konten kursus (kursus global). `sba_admin` dapat membuka kursus
  yang sama seperti editor — perbedaan hanya di panel tempat mereka login.
- Progress disimpan per `user_id`, bukan per organisasi — setiap orang punya
  progres pribadi, baik staf federasi maupun pengurus SBA.

### Struktur direktori (rencana)
```
database/migrations/2026_09_04_0000{14..19}_create_{course_lessons,course_quizzes,
                    quiz_questions,quiz_options,course_progress,course_attempts}_table.php
database/migrations/2026_09_04_000020_add_pass_threshold_to_courses_table.php
app/Models/{CourseLesson, CourseQuiz, QuizQuestion, QuizOption, CourseProgress, CourseAttempt}.php
app/Models/Course.php                     (+ lessons(), quizzes(), pass_threshold)
app/Models/User.php                       (+ progress(), attempts())
app/Filament/Resources/CourseResource.php (+ pass_threshold + LessonsRelationManager + FinalQuizRelationManager)
app/Filament/Resources/CourseLessonResource.php + Pages/{List,Create,Edit}CourseLesson
app/Filament/Resources/CourseLessonResource/RelationManagers/QuizzesRelationManager.php
app/Filament/Resources/CourseQuizResource.php + Pages/{List,Create,Edit}CourseQuiz
app/Filament/Resources/CourseQuizResource/RelationManagers/QuestionsRelationManager.php
app/Filament/Resources/QuizQuestionResource.php + Pages/{List,Create,Edit}QuizQuestion
app/Filament/Resources/QuizQuestionResource/RelationManagers/OptionsRelationManager.php
app/Filament/Resources/FinalQuizResource.php (kuis akhir, lesson_id null) + Pages
app/Filament/Resources/FinalQuizResource/RelationManagers/QuestionsRelationManager.php
app/Filament/Admin/Pages/MyCoursesPage.php + /CourseDetailPage, /LessonViewPage, /QuizViewPage
app/Filament/Sba/Pages/MyCoursesPage.php  + /CourseDetailPage, /LessonViewPage, /QuizViewPage
app/Filament/Widgets/LearningReportWidget.php   (admin, super_admin only)
database/factories/{CourseLesson,CourseQuiz,QuizQuestion,QuizOption}Factory.php
database/seeders/CourseContentSeeder.php  + wiring di DatabaseSeeder
tests/Feature/{CourseAuthoringTest, QuizEngineTest, CourseProgressTest,
               LearnerAccessTest, LearningReportTest}.php
```

## 4. Model Data (tabel SP4)

Semua FK ke `courses`/`users` memakai `cascadeOnDelete` (konten kursus dibuang
bersama kursusnya; progres dibuang bersama usernya). Tidak ada `organization_id`
di tabel konten kursus (konten global milik federasi); `course_progress`/
`course_attempts` memakai `user_id` (bukan organisasi) karena progres bersifat
pribadi per orang.

- **course_lessons** — id, `course_id` (FK `courses`, `cascadeOnDelete`), `title`
  (string, wajib), `content` (longText, wajib — **trusted HTML** dari
  RichEditor staff federasi, sama seperti artikel/isi halaman SP1; hanya boleh
  dirender raw karena ditulis oleh staff yang sudah terautentikasi), `sort_order`
  (unsignedInteger default 0), timestamps.
- **course_quizzes** — id, `course_id` (FK `courses`, `cascadeOnDelete`),
  `lesson_id` (nullable FK `course_lessons`, `cascadeOnDelete` — **null = kuis
  akhir kursus**, dibuat langsung di level kursus tanpa lesson),
  `title` (string, wajib), `pass_threshold` (unsignedTinyInteger default 70 —
  ambang lulus per kuis, bisa diubah pengarang), timestamps.
- **quiz_questions** — id, `course_quiz_id` (FK `course_quizzes`,
  `cascadeOnDelete`), `question` (text, wajib), `sort_order` (default 0),
  timestamps.
- **quiz_options** — id, `quiz_question_id` (FK `quiz_questions`,
  `cascadeOnDelete`), `option` (text, wajib), `is_correct` (bool default false —
  tepat satu `is_correct` per pertanyaan), `sort_order` (default 0), timestamps.
- **course_progress** — id, `course_id` (FK), `user_id` (FK `users`,
  `cascadeOnDelete`), `lesson_id` (nullable FK `course_lessons` —
  `course_lessons` dihapus → `cascadeOnDelete`), `is_completed` (bool default
  false), `completed_at` (nullable timestamp), timestamps. **Unique
  `(user_id, course_id, lesson_id)`** — satu baris per (user, course, lesson).
- **course_attempts** — id, `course_quiz_id` (FK `course_quizzes`,
  `cascadeOnDelete`), `user_id` (FK `users`, `cascadeOnDelete`), `score`
  (unsignedTinyInteger 0–100), `passed` (bool), `attempt_date` (timestamp),
  timestamps. Riwayat percobaan kuis per user — mendukung "bisa diulang sampai
  lulus".

**Modifikasi `courses`:** tambah `pass_threshold` (unsignedTinyInteger, default
70) — ambang default kuis kursus; nilai per-kuis menimpa bila diisi. Tidak
mengubah kolom SP1 yang ada (hanya aditif).

**Standar relasi:** `Course::lessons()` (hasMany, ordered by sort_order),
`Course::quizzes()` (hasMany, termasuk kuis akhir `lesson_id null`),
`CourseQuiz::lesson()` (belongsTo nullable), `Quiz::questions()`, `Question::options()`,
`Lesson::quizzes()`, `CourseProgress::course()`, `CourseProgress::lesson()`,
`CourseProgress::user()`, `CourseAttempt::quiz()`, `CourseAttempt::user()`,
`User::progress()`, `User::attempts()`.

## 5. Autentikasi & Role (SP4)

Tidak ada role baru. Matriks akses (konsisten dengan tabel SP2 §5 / SP3 §5):

| Siapa | Authoring kursus | Belajar | Lihat progres orang lain |
|---|---|---|---|
| `super_admin` | ✓ (`/admin`) | ✓ (`/admin`) | ✓ (laporan `/admin`) |
| `editor` | ✓ (`/admin`) | ✓ (`/admin`) | ✗ |
| `sba_admin` | ✗ | ✓ (`/panel-sba`) | ✗ |

- Penegakan tetap `canAccessPanel()` per panel (SP2) — tidak berubah.
- **Authoring** (`CourseResource` dan resource/relation-manager anak) hanya
  muncul di `/admin` untuk `super_admin`/`editor`. `sba_admin` tidak melihat
  resource authoring sama sekali.
- **Belajar** muncul di kedua panel. `sba_admin` melihat "Kursus Saya" dan isi
  kursus yang publik (`is_published = true` saja) di `/panel-sba`.
- **Pelaporan lintas-user** dibatasi `super_admin` saja (via `canView()` seperti
  `SbaAccountsOverviewWidget` SP2), karena mengekspos data progres semua
  pengguna. `editor` dan `sba_admin` hanya melihat progres pribadi mereka.

## 6. Panel & Fitur Baru

### 6a. Authoring (federasi, `/admin`)
- **`CourseResource` diperluas**: form kursus + `pass_threshold` (Select
  50/60/70/80/90, default 70). Table kursus + kolom jumlah lesson, jumlah kuis,
  `pass_threshold`.
- **Authoring konten via relasi resource + RelationManager bertingkat**
  (mengadopsi pola `AttendancesRelationManager` SP3, bukan nested-repeater agar
  stabil), dengan setiap level sebagai resource sendiri yang di-link dari
  resource induknya:
  - Edit `Course` → RelationManager **`LessonsRelationManager`** (buat lesson:
    title + RichEditor content, `orderColumn('sort_order')`).
  - Edit `Course` → RelationManager **`FinalQuizRelationManager`** (kuis akhir
    dengan `lesson_id null`).
  - Edit Lesson (`CourseLessonResource`) → RelationManager
    **`QuizzesRelationManager`** (kuis milik lesson itu).
  - Edit Kuis (`CourseQuizResource` / `FinalQuizResource`) → RelationManager
    **`QuestionsRelationManager`** (soal).
  - Edit Soal (`QuizQuestionResource`) → RelationManager
    **`OptionsRelationManager`** (opsi + toggle `is_correct`).
- Kuis akhir di-entri lewat `FinalQuizRelationManager` di level `Course`
  (`lesson_id = null`); kuis per lesson lewat level `CourseLesson`. Keduanya
  satu model `course_quizzes`, dibedakan oleh `lesson_id` nullable.

### 6b. Belajar (kedua panel)
- **"Kursus Saya"** (halaman Filament di tiap panel): daftar kursus **published**
  (`Course::published()`), masing-masing dengan progres ringkas (X/Y lesson
  selesai, status kuis, badge "Selesai" bila seluruh lesson + kuis akhir lulus).
  Untuk `sba_admin` hanya kursus published yang tampil.
- **Halaman kursus** (`CourseDetailPage`): daftar lesson urut + status tiap
  lesson (Belum / Selesai / ada kuis belum lulus) + tombol. Tombol:
  - Lesson tanpa kuis → "Tandai Selesai" / "Batal Selesai".
  - Lesson berkuis → "Kerjakan Kuis".
  - Akses kuis akhir dari halaman ini.
- **Halaman lesson** (`LessonViewPage`): render `content` (trusted HTML staff).
- **Halaman kuis** (`QuizViewPage`): render semua soal MCQ sekaligus + tombol
  submit → nilai skor & lulus/gagal vs `pass_threshold` → simpan `course_attempts`
  → update `course_progress` (lesson berkuis otomatis selesai saat lulus) →
  tampilkan hasil & tawarkan "Ulangi". Bisa diulang bebas.

### 6c. Pelaporan federasi (`/admin`, super_admin)
- Widget/halaman **"Laporan Pembelajaran"** (`canView()` = super_admin):
  - Ringkasan per kursus: jumlah user yang Sudah Selesai / Sedang / Belum Mulai.
  - Tabel kursus × user: status progres tiap (course, user).
- Ini **bukan** PII anggota SPMKB (progres belajar, bukan data NIK/upah), jadi
  diizinkan tampil di federasi — beda sadar dari kebijakan PII SP3 yang hanya
  mengikat tabel data anggota.

## 7. Keamanan & Kualitas

- **Konten lesson = trusted HTML** dari RichEditor authoring federasi
  (`super_admin`/`editor`), dirender raw — sama seperti artikel/isi halaman SP1.
  Hanya staff yang bisa menulis konten kursus; tidak ada jalur penulisan oleh
  non-staff, sehingga tidak perlu sanitasi ekstra (konsisten dengan kebijakan
  trusted-HTML AGENTS.md: "Escape all non-staff input; only these fields may
  bypass escaping").
- **Perhitungan kuis** ada di server (`QuizViewPage` membandingkan jawaban
  dengan `quiz_options.is_correct` di DB; skor dihitung server-side), bukan di
  klien — pembelajar tidak bisa "melihat kunci" lewat inspeksi browser, dan
  attempt direkam otomatis tanpa dipercayai jawaban klien.
- **Batasan akses**: authoring `/admin` only; belajar published-only untuk
  `sba_admin`; pelaporan lintas-user super_admin only; semua lewat
  `canAccessPanel()` + `canView()` (pola SP2 `SbaAccountsOverviewWidget`).
- **Integritas**: tepat satu `is_correct` per soal dijaga di UI authoring (+
  query skor memakai `->where('is_correct', true)->value('id')`; jika ambiguitas,
  hanya satu diambil — tambahkan validasi authoring bahwa tiap soal punya
  minimal satu opsi benar).
- **Testing (PHPUnit feature, wajib)**, mengikuti pola SP1–3:
  - `CourseAuthoringTest` — CRUD course/lesson/quiz/question/option di `/admin`,
    role isolation (super_admin/editor boleh, `sba_admin` ditolak dari
    authoring), relation-manager create.
  - `QuizEngineTest` — skor dihitung benar (benar/salah), `passed` vs
    `pass_threshold`, retake menyimpan banyak attempt, threshold per-kuis.
  - `CourseProgressTest` — lesson berkuis auto-selesai saat lulus, lesson tanpa
    kuis manual toggle, kursus "selesai" hanya bila semua lesson + kuis akhir
    lulus, peyorasi (undo lesson / nilai turun) mencerminkan status derived.
  - `LearnerAccessTest` — sba_admin hanya melihat published, editor diizinkan,
    akses authoring ditolak utk sba_admin, 403/redirect anonim.
  - `LearningReportTest` — widget super_admin only; editor/sba tidak melihatnya.
- Lint `vendor/bin/pint` sebelum commit; seluruh suite SP1+SP2+SP3 harus tetap
  hijau.

## 8. Non-Goal & Keputusan yang Ditunda

- Pendaftaran/login publik pembelajar — non-goal SP1/SP2/SP3 tetap berlaku.
  Kursus hanya untuk akun internal yang sudah ada.
- Tipe soal non-MCQ, sertifikat, leaderboard, forum, rating — lihat §2.
- Authoring kursus oleh SBA (dimensi tenant konten kursus) — kursus global milik
  federasi; jika dibutuhkan, itu perubahan kebijakan + spec baru.
- Enkripsi/DRM isi kursus, impor SCORM.
- Pelaporan progres yang lebih dalam (waktu belajar, sesi, lulus-total per
  bidang) — cukup ringkasan kursus × user di SP4 untuk memenuhi "progres";
  detail bisa jadi SP lanjutan bila diprioritaskan.

## 9. Rujukan Sub-Proyek

- SP5 dan seterusnya (belum dijadwalkan): modul SPMKB lain yang dikeluarkan SP3
  (kartu anggota/cetak, buku kas, surat-menyurat, sanksi, pesangon, laporan
  tunggakan detail), penambahan artikel oleh SBA (`articles.organization_id`),
  atau perluasan e-learning (tipe soal, sertifikat, authoring SBA). Masing-masing
  butuh spec baru yang merujuk balik ke dokumen induknya — tidak boleh ditambahkan
  diam-diam ke SP4.
