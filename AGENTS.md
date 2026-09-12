# AGENTS.md

Laravel 12 + Filament 3 + Tailwind v4 public site for a trade-union federation
(FSBMM). Fully admin data-driven (two Filament panels: `/admin` for staff,
`/panel-sba` for SBA admins); public pages have no hardcoded content. Indonesian
locale/timezone (`APP_LOCALE=id`, `Asia/Jakarta`).

See `README.md` for setup, stack, and structure that stays canonical.
`knowledge.md` (repo root) is an optional local agent scratch doc — it is
gitignored (`# Agent tooling artifacts`) and not tracked, so don't reference it
as canonical.

## Multi-phase roadmap

**All of SP1–SP4 are complete**; design + implementation plans live in
`docs/superpowers/specs/` and `docs/superpowers/plans/`. SP3 added per-SBA
member data (`members`/`dues`/`events`/`attendances`/`complaints`) with a
federation PII-minimization policy. SP4 added federation-**global** e-learning:
authoring in `/admin` (staff only), a "Kursus Saya" learner surface in both
panels, and a super-admin-only learning report. Skipping a spec/plan is how
this repo breaks — read the relevant one before touching an area.

**SP5 (operational reporting / secure export / kartu anggota) is complete through
Phase 10** (all phases committed, gates PASS — incl. Phase 9 card PDF/QR print and
the Phase 10 security + regression gate). 4 tenant-scoped SBA report pages
(`MemberReportPage`, `DuesReportPage`, `AttendanceReportPage`,
`ComplaintReportPage`, nav group `Laporan`, registered explicitly in
`SbaPanelProvider->pages([...])`) + a `ReportingTest` suite + member-card
lifecycle (`MemberCardService`), the SBA `MemberCardPage`, and public
`/verifikasi/kartu/{token}`. Federation reporting is `super_admin`-only and
`/verifikasi/kartu/{token}`/`/verifikasi/sertifikat/{token}` must be declared
ABOVE the `{page:slug}` catch-all (spec §9.10). Card printing lives in
`MemberCardPdfRenderer` + `QrCodeRenderer` (Snappy with HTML fallback when the
wkhtmltopdf binary is absent). Read the SP5 spec/plan before touching SBA
reporting, exports, or member-cards.

Every plan records every deviation from spec/snippets as inline
`(** executed: ... **)` annotations. Preserve/append these when you change
behavior — never rewrite history.

**Public-enhancement phases 1–4** (separate from SP1–SP5, planned in
`docs/superpowers/plans/2026-09-10-fsbmm-website-phase4-capacity-accountability.md`
and the phase 1–3 equivalents) added: chart page-builder block + dark mode +
mobile drawer (1); SBA export buttons + `GlobalSearchWidget` +
`NotificationAlertWidget` + `FederationReportGenerator` (2); SBA `MemberCardPage`
+ `/verifikasi/kartu/{token}` (3); **e-learning certificates**, **audit trail**,
**bulk actions**, and a PDF-export fallback (4). Two things to know before
touching these:

- **Certificates** (`course_certificates`, `CertificateService`): issued only for
  a *published + completed* course, idempotent per learner/course, printed via
  the shared `CertificatePrintController` registered on BOTH panels as
  `/certificates/{record}/print`, verified publicly at
  `/verifikasi/sertifikat/{token}` (also declared ABOVE the `{page:slug}`
  catch-all). Revocation is schema-only — there is no revocation UI yet.
- **Audit trail** (`audit_logs`, `AuditLogger`): append-only, written from
  observers/services, read by `app/Filament/Admin/Pages/AuditTrailPage`
  (`super_admin` only, PII-free) and `app/Filament/Sba/Pages/AuditTrailPage`
  (tenant-scoped). Descriptions MUST stay PII-free — only changed column names,
  statuses, card/certificate numbers, and dataset names. Never pass member
  names/NIK/address/salary or raw complaint text.

**Phase-4 enhancement code is COMMITTED as a single batch** (commit `dba516e`,
`feat(phase4): e-learning certificates, audit trail, bulk actions, PDF export
fallback`): `CertificateService`, `AuditLogger`,
`CertificatePrintController`, `AuditTrailPage*`, their migrations, and
`CertificateTest`/`AuditTrailTest`/`SbaBatchActionTest`/`FederationReportExportTest`.
No Phase-4 file is uncommitted in a clean worktree.

## Two panels, one app, three roles

SP2 added a second Filament panel sharing the `web` guard + `users` table. Keep
this boundary intact — a role must never cross into the wrong panel:

- `/admin` (id `admin`) — staff: `super_admin`, `editor`. Owns all content/admin
  resources plus the federation `SbaAccountsOverviewWidget`, the aggregate-only
  `MemberDataOverviewWidget`, the SP4 authoring chain (`CourseResource`,
  `CourseLessonResource`, `CourseQuizResource`, `QuizQuestionResource`,
  `FinalQuizResource`) and the `LearningReportWidget`.
- `/panel-sba` (id `sba`) — SBA admins: `sba_admin` with a linked
  `organization_id`. Owns a tenant-scoped `OrganizationResource` profile plus the
  SP3 member-data resources (`MemberResource`, `DuesResource`, `EventResource`
  + `AttendancesRelationManager`, `ComplaintResource`).

**SP4 e-learning is NOT tenant-scoped.** Courses/lessons/quizzes/final quizzes
are federation-global content, unlike SP3 member data — don't reach for
`getEloquentQuery()` scoping on the authoring resources. Every authenticated
panel user (incl. `sba_admin`) is a learner via "Kursus Saya"; authoring is
staff-only by panel access (`sba_admin` gets 403 on `/admin` resources); the
learning report is `super_admin`-only via `LearningReportWidget::canView()`.
Learner pages list published courses only (`Course::published()`).

`User::canAccessPanel()` is keyed on the Filament panel id. Tenant scoping for
the SBA panel is enforced **at the panel layer only**
(`OrganizationResource::getEloquentQuery()` → `whereKey(auth()->user()?->organization_id)`);
do NOT add a global scope — it would break the public `/sba` directory, which
must still list every published organization. Route binding resolves through the
scoped query, so editing another org's slug 404s.

`Organization.member_count` is kept in sync by `MemberObserver`
(`created`/`updated`/`deleted`/`restored` → `syncMemberCount()`, which counts
status-`aktif` members via `saveQuietly()`). The public site and the
federation-side aggregate widget read only this column — neither queries the
`members` table. Don't add a live count or query `members` on the federation
side (PII policy, below).

### Learning pages are registered per-panel, not discovered

The "Kursus Saya" pages (`MyCoursesPage`, `CourseDetailPage`, `LessonViewPage`,
`QuizViewPage`) have per-panel concrete classes in `app/Filament/Admin/Pages/`
and `app/Filament/Sba/Pages/` — **not** a single panel-specific discovery dir.
Their logic is deduplicated into abstract bases in `app/Filament/Pages/` (see
below). Each `*PanelProvider` registers them explicitly via `->pages([...])`,
and their pretty URLs
(`/courses/{record}`, `/courses/{record}/lessons/{lesson}`, `/quizzes/{record}`)
come from `Panel::authenticatedRoutes()` with implicit Livewire route-model
binding into `mount(?Course $record)`. Page-level `getRoutes()` does **not**
exist in Filament 3.3.55. To add a learning page, register it in BOTH
`->pages()` and `->authenticatedRoutes()` — never `routes/web.php`.

The 4 learning pages are **deduplicated**: each panel's concrete class is a thin
`extends` of the shared abstract base in `app/Filament/Pages/` (`MyCoursesBase`,
`CourseDetailBase`, `LessonViewBase`, `QuizViewBase`), and the 4 views live once
in `resources/views/filament/pages/` (not per panel). Filament 3.3.55 page
discovery skips abstract classes, so the bases never register routes/navigation —
they must stay abstract. To reference a panel-specific named route from a shared
blade use the `panelRoute()` helper (`App\Support\ResolvesPanelRoutes`:
`route('filament.'.($panel = Filament::getCurrentPanel() ?? Filament::getDefaultPanel())->getId().'.'.$name, $params)`),
never a hardcoded
`filament.admin.*`/`filament.sba.*` prefix or `\App\Filament\...\MyCoursesPage::getUrl()`.
When the panelled behavioral twins drift, fix the shared base once — the Admin and
Sba `CourseDetailPage` diverged (quiz-less-toggle guard) and were merged here.

## Commands

- Test whole suite: `composer test` (runs `config:clear` then `php artisan test`).
  Single: `php artisan test --filter <Name>`.
- Lint: `vendor/bin/pint` (config in repo `pint.json` — stricter than the default
  Laravel preset: alpha-order imports, short arrays, no unused imports).
  Frontend/static gate: `composer verify` (runs `composer test` + PHPStan + Pint).
  Generated Filament `Create*/Edit*` pages can drift (unused imports) — run Pint
  before committing.
- Static analysis: `composer analyse` (PHPStan level 7 via Larastan; pre-existing
  findings live in `phpstan-baseline.neon` — regenerate with
  `vendor/bin/phpstan analyse --generate-baseline` after deleting a suppression or
  upgrading PHPStan). `composer verify` fails on NEW findings only.
- Parallel test run: `composer test:parallel` (uses `brianium/paratest`).
- Coverage: `composer test:coverage` (needs Xdebug or pcov; missing driver makes
  PHPUnit abort with a clear message).
- Frontend: `npm run dev` (dev) / `npm run build` (prod).
- Fresh install + seed + build: `composer setup`.
- Full dev with hot reload + queue + logs: `composer dev` (concurrently runs
  `php artisan serve`, `queue:listen`, `pail`, `npm run dev`).

## Git workflow

Git workflow (branch `feature/*`/`hotfix/*`, conventional commits, merge
`--no-ff`, quality gates wajib) didokumentasikan di `CONTRIBUTING.md`; sisi
maintainer (CI, dependabot, release/tag, mirror) di `REPO_MANAGEMENT.md`.
Repo memakai `core.hooksPath = .githooks` (pre-commit Pint + `php -l`, commit-msg
conventional) — diaktifkan otomatis oleh `composer install`/`update` via
`post-install-cmd`/`post-update-cmd`. Jangan nonaktifkan hooks; jalankan
`git config core.hooksPath .githooks` bila lingkungan Anda tak punya `.git`
(CI). `.git-blame-ignore-revs` memigrasi commit reformat besar (Pint whole
suite) — aktifkan lokal dengan `git config blame.ignoreRevsFile
.git-blame-ignore-revs`. Menambah/menghapus file ini wajib lewat plan seperti
perubahan konvensi lainnya.

## Deployment (branch `deploy/vercel`)

Production ships off the `deploy/vercel` branch (currently checked out) — **not**
`master`: Vercel builds a FrankenPHP container from `Dockerfile.vercel`
(declared in `vercel.json`, container runtime) served via `Caddyfile`, and every
push to `deploy/vercel` triggers `.github/workflows/deploy-migrate.yml`
(secrets-driven `php artisan migrate --force` against the production MySQL/TiDB
Cloud DB). `master` runs CI only (`ci.yml`); master pushes don't deploy.

- Container filesystem is effectively read-only/ephemeral: filament assets,
  `package:discover`, and the `storage/framework/*` dirs are baked into the
  image at build time. `config:cache`/`route:cache` are intentionally NOT run
  (env differs between build and runtime — and web.php's Closure routes can't
  be route:cache'd anyway).
- Production storage is an S3-compatible bucket (R2/Spaces):
  `config/filesystems.php` swaps **both** the `local` and `public` disks to S3
  when `FILESYSTEM_LOCAL_DRIVER`/`FILESYSTEM_PUBLIC_DRIVER=s3` (pinned in the
  image ENV). The swap is **guarded by `$s3Ready`**: it only engages when
  `league/flysystem-aws-s3-v3` is installed AND the full `AWS_*` config
  (bucket/keys/region) is present, otherwise disks fall back to
  `local` — a misconfigured AWS_* env can never take the site down. Keep that
  guard and swap shape if you touch filesystems config; tests and local dev keep
  the local disks. `AWS_ENDPOINT` is optional (required only for S3-compatible
  providers like R2/Spaces; classic AWS S3 resolves the endpoint from
  `AWS_DEFAULT_REGION`).
- Never let a dev `bootstrap/cache/packages.php`/`services.php` reach the image:
  `package:discover` in the `--no-dev` build would then load dev-only providers
  (e.g. laravel/pail) and crash. This is enforced by `.dockerignore` — don't
  remove it.
- Runtime ENV pins `QUEUE_CONNECTION=sync`; uploads/report exports in prod land
  in the S3 bucket, not container storage. `.env`/SQLite/storage never enter the
  image (`.vercelignore`/`.dockerignore`).
- `Caddyfile` serves static assets directly (`@static` matcher). `robots.txt`
  MUST NOT be in that matcher and has no `public/robots.txt` file — it is served
  by the Laravel route (`routes/web.php:18`) so the Sitemap directive stays
  env-aware; listing it under `@static` makes it 404 (no file to serve).

## Env gotchas

- Admin account comes from `FSBMM_ADMIN_EMAIL` / `FSBMM_ADMIN_PASSWORD` env in the
  seeder; demo SBA accounts come from `FSBMM_SBA_PASSWORD`. Never commit a real
  password or forget to change them in production.
- Local DB is SQLite (`database/database.sqlite`); production is MySQL via the
  commented block in `.env.example` — switching means `DB_CONNECTION=mysql` + fill
  the block. Don't leave SQLite config in prod.
- Queue, cache, and session drivers are `database`-based.
- Filament public assets are regenerated during `composer install`
  (`post-autoload-dump`) and not committed; run `php artisan filament:assets`
  manually if they go missing.
- Public pages load `@vite(['resources/css/app.css', 'resources/js/site.js'])`.
  A stale `public/build/manifest.json` missing the `site.js` entry makes EVERY
  public page throw `ViteException` (mass 500s across tests). Fix: `npm run build`
  (`public/build` is gitignored — rebuild after branch switches too).
- `php artisan storage:link` is needed locally for uploaded logos/covers/PDFs.
- SP5 deps are already installed for later phases: `openspout` (CSV/XLSX
  export), `laravel-snappy` (print/PDF), `bacon-qr-code` (member-card QR).

## Routing

- `routes/web.php`: collections (`/berita`, `/sba`, `/e-resource`, `/e-learning`),
  sitemap/robots, and named home route MUST be declared ABOVE the page-builder
  catch-all `Route::get('/{page:slug}', ...)`. Any new public collection route
  that lands below it is shadowed by the catch-all.
- Structural slugs `['home','tentang','kontak']` come from ONE constant,
  `Page::STRUCTURAL_SLUGS` — reused by the model guards and the sitemap
  (`routes/web.php:33`). Never hardcode a second copy.
- `php artisan route:cache` is INCOMPATIBLE: `/`, `/tentang`, `/kontak`,
  `/robots.txt`, `/sitemap.xml` are closures by spec design, so it throws
  `LogicException`. Don't add it to a deploy script or "fix" the closures
  without the plan's blessing.
- `layouts/public.blade.php` header is auth-aware (`@auth`/`@guest` work because
  both panels share the `web` guard). Panel links MUST use Filament's named
  routes (`filament.sba.auth.login`, `filament.admin.auth.login`,
  `filament.sba.pages.dashboard`, `filament.admin.pages.dashboard`) — never
  hardcode `/admin` or `/panel-sba` paths in blades.

## Style

The public design pass — refined-modern, merged from `v0/refine-visual-design`
(commit `1934943`, adapted in merge `996ca32`): **Plus Jakarta Sans** (display)
+ **Inter** (body) as **self-hosted variable fonts** — `@font-face` rules in
`public/css/fonts.css` (woff2 in `public/fonts/`), linked from
`layouts/public.blade.php` and injected into both Filament panels via the
`STYLES_AFTER` render hook in `AppServiceProvider`; `NullFontProvider`
suppresses Filament's external font `<link>`. No Google Fonts/Bunny requests —
an intentional privacy/offline hardening (Phase C), keep it that way:
soft layered brand-tinted elevation (`card-pop`), a cohesive brand-green
gradient for primary CTAs / `text-gradient` / `reading-progress`, toned
`eyebrow-chip`/`search-input`, `--radius: 1rem`, and the `--color-vivid-*`
palette kept for decorative accents (blobs, rainbow-bar, accent arrays) — is
**committed** — don't revert or re-theme it. Both Filament panels share the
brand look: `primary = Color::hex('#12806a')` (matches public
`--color-brand-600`), `font('Plus Jakarta Sans')`, brand names
(`App\Providers\Filament\AdminPanelProvider` /
`App\Providers\Filament\SbaPanelProvider`; SBA panel keeps `->profile()`). Use
existing tokens/utilities from `resources/css/app.css` for any new public UI.

## Public-site interactivity (`resources/js/site.js`)

Dependency-free, Vite-bundled, **progressive enhancement** — content must render
fine without JS; site.js only arms it. Available hooks: `data-reveal`
(scroll-reveal via IntersectionObserver), `data-counter` (+ `data-counter-target`/
`data-counter-suffix`), `data-live-search` (+ `data-live-search-input`/
`data-live-search-item`/`data-live-search-empty`), `data-copy-link`,
`data-download-feedback`, `#reading-progress`, `window.FSBMM.toast(msg, type)`.
Back-to-top lives inline in `layouts/public.blade.php`, **not** in site.js — don't
duplicate. Accent arrays in index views hold FULL Tailwind class strings — use
them directly, never concatenate a `border-`/`text-` prefix onto them.

## Security conventions

- RichEditor content (articles/pages) is **trusted HTML** — authored only by
  authenticated staff, rendered raw. Escape all non-staff input; only these
  fields may bypass escaping.
- `Organization.description` renders raw on the public page but is editable by
  non-staff `sba_admin`, so it is **sanitized at the model layer**
  (`Organization::setDescriptionAttribute` → `Str::sanitizeHtml`) on every write
  path. Never add a no-sanitize bypass for it; articles/pages remain staff-raw.
- E-resource downloads use signed URLs (`middleware('signed')`) + per-download
  counting; deleted files return a clean 404.
- **Def-site blocking (Phase B/T2)** — `SecurityHeadersMiddleware`
  (`X-Frame-Options: DENY`, `Content-Security-Policy:
  frame-ancestors 'none'; form-action 'self'`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy`) is mounted BOTH on the `web` route
  group (`bootstrap/app.php`) AND in each `PanelProvider->middleware([...])` —
  Filament panels do NOT inherit the `web` group, so a panel without its own
  mount is unprotected. Do NOT add a full `default-src` CSP: Livewire/Filament
  inline bootstrap and staff-authored trusted HTML that embeds third-party
  frames (e.g. YouTube) depend on the permissive default —
  `frame-ancestors` already closes the embedding vector.
- **Upload ext/MIME lock (Phase B/T2)** — `UploadedImageOptimizer::store()`
  THROWS `RuntimeException` for anything it cannot decode as a real
  JPEG/PNG/WEBP (the old `storeOriginal()` fallback persisted raw attacker
  bytes to `public/storage`, where Caddy's `php_server` could execute `.php`
  files). E-resource PDFs are byte-sniffed server-side by
  `App\Rules\RealPdfFile` (finfo + `%PDF-` fallback) — `acceptedFileTypes` is
  client-side only. Filament form-level `rules()` receives the pending upload
  as a `TemporaryUploadedFile` OBJECT (not a path string), so the rule must
  sniff through `getRealPath()` — Laravel's built-in image/mimes rules silently
  skip non-UploadedFile values. Never reintroduce a raw-store fallback.
- **E-resource fingerprint (Phase B/T2)** — `e_resources.sha256` is computed
  automatically on every `file_path` change (model `saving` hook,
  `Eresource::fingerprintPath()`); `hasDuplicateFile()` detects same-file
  re-uploads. The hook uses the SAME traversal guard as the download route
  (`..`/absolute paths → no fingerprint): corrupt paths must never be read
  from disk (Local adapter rejects them → save would 500).
- Structural pages (`home`, `tentang`, `kontak`) are protected from delete and
  slug-change at model + UI level. Don't bypass these guards.
- An organization that still has `sba_admin` accounts or member rows can't be
  deleted from `/admin`: single-delete gated by `canDelete()` (checks
  `hasSbaAccounts()` **or** `hasMembers()`), bulk-delete by
  `DeleteBulkAction->using()` (batch-abort). Do NOT gate visibility with a
  `canDeleteAny()` based on the account guard — it would hide bulk-delete for
  all orgs. (SBA panel's own `OrganizationResource` has create/delete disabled.)
  The `member_count` form field is `disabled()` only when `hasMembers()` is true
  (orgs with zero members stay federation-typed manually).
- **Member data is PII; federation sees aggregates only.** `super_admin`/`editor`
  in `/admin` must never see or query individual `members`/`dues`/`attendances`/
  `complaints` rows — only the aggregate `MemberDataOverviewWidget` (Σ
  `member_count`, Σ current-month dues, COUNT open complaints), gated by
  `canView()` + a no-PII test. Never add a `/admin` resource or PII column for
  these tables.
- E-learning lesson `content` is **trusted staff HTML** (like articles/pages),
  rendered raw; only staff author it in `/admin`.
- Quiz grading is **server-side only**: `QuizEngine::submit()` scores against
  `quiz_options.is_correct`, and the correctness flag never leaves the server.
  Lesson completion is set on quiz pass; course completion is **derived** in
  `LearningProgress` (all lessons complete + final quiz passed), not stored.
  The final quiz (`lesson_id` null) never completes a course on its own.

## Testing quirks (hard-won from SP3/SP4)

- In full-panel `Livewire::test()` feature tests, pass the **raw primary key**,
  not the model instance: `Livewire::test(EditMember::class, ['record' => $member->id])`.
  Livewire doesn't resolve route-model binding from a model instance.
- `Filament::auth()` is **null in Livewire test context** (Livewire::actingAs
  doesn't propagate through Filament's auth resolver) — use `auth()->user()`
  inside widget/resource logic and in tests.
- A `Select::relationship()` `modifyQueryUsing` only scopes the dropdown UI,
  **not** server-side. To reject a cross-tenant `member_id`, put a `->rules()`
  closure on the Select field itself (the relationship resolver strips the value
  before `mutateFormDataBeforeCreate` runs).
- `DatabaseSeeder` uses `WithoutModelEvents`, so the `Member` observer is silent
  during seeding — `MemberDataSeeder` calls `$org->syncMemberCount()` explicitly
  at the end. Don't remove that call; `MemberDataSeedTest` guards it.
- `Course::finalQuiz()` is a **query method, not a relation** — eager-loading
  `'finalQuiz'` (e.g. `->with(['lessons.quizzes', 'finalQuiz'])`) crashes. Call
  `$course->finalQuiz()` directly (`LearningProgress` annotates this).
- Filament 3.3.55 widgets default to lazy (`CanBeLazy::$isLazy = true`), so
  their content is absent from the initial HTTP response. Widgets whose content
  tests assert on (learning report, member-data overview) opt out with
  `protected static bool $isLazy = false;` — keep it that way.
- `LearningProgress::forUser()`/`report()` return an
  `Illuminate\Support\Collection` of maps, not an Eloquent collection — don't
  type-hint for `Collection` from Eloquent and keep the field-light access.
- `Course::lessons()` already orders by `sort_order` (relation) — don't add your
  own `orderByDesc('sort_order')` (double ORDER BY → nondeterministic pick;
  prefer `whereDoesntHave('quizzes')->first()` for a no-quiz lesson).
- Factory footgun: the final quiz MUST use `['lesson_id' => null]`; per-lesson
  quizzes need `->for($course, 'course')->for($lesson, 'lesson')`, `QuizQuestion`
  → `->for($quiz, 'quiz')`, `QuizOption` → `->for($q, 'question')`.
- Filament panels auto-discover only their own `app/Filament/{Admin,Sba}/**`, so
  widgets in `app/Filament/Widgets` (e.g. `LearningReportWidget`) structurally
  cannot reach `/panel-sba` — no extra guard needed.
- `Sp5SecurityTest` historically carried **expected failures** for SP5 routes
  built in later phases (anonymous export redirect, `/verifikasi/kartu/{token}`).
  Both are now real + green: the export guard lives in Sp5SecurityTest and card
  verification coverage lives in `CardVerificationTest` (spec §11.5). Don't
  reintroduce placeholder expected-failure tests for built routes.
- `tests/TestCase.php` normalizes `APP_ENV=testing` before the app boots — a
  host-shell `APP_ENV` export would otherwise shadow `phpunit.xml`. Keep that
  guard.
- Filament 3.3.55 traps: `Table::orderColumn()` doesn't exist (use
  `->reorderable('sort_order')`); `CreateAction::mutateDataUsing()` doesn't
  exist (use `mutateFormDataUsing()`); custom pages get a single slug route, so
  record URLs need `Panel::authenticatedRoutes()` + page `mount(?Model $record)`;
  page `$view` must be `protected static string`.
- `Factory::for()` infers the relation from the class name — relations here are
  non-conventional (`lesson()`, `quiz()`, `question()`), so PASS the explicit
  name: `->for($lesson, 'lesson')`. Same for non-conventional FKs
  (`course_quiz_id`, `quiz_question_id`).

## Verify before completion

Run `php artisan test` after non-trivial changes. Feature suite covers auth,
articles/scheduled publishing, e-resource library, organizations, SBA panel
tenant scoping + member data, e-learning authoring + learner progress + quiz
engine, federation overview, seeder, pages, and SEO.
