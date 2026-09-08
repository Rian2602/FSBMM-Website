# AGENTS.md

Laravel 12 + Filament 3 + Tailwind v4 public site for a trade-union federation
(FSBMM). Fully admin data-driven (two Filament panels: `/admin` for staff,
`/panel-sba` for SBA admins); public pages have no hardcoded content. Indonesian
locale/timezone (`APP_LOCALE=id`, `Asia/Jakarta`).

See `README.md` for setup, stack, and structure that stays canonical.
`knowledge.md` (repo root) is a longer module walkthrough — treat it as
supplementary; its checklists/counts may trail the committed suite.

## Multi-phase roadmap

**All of SP1–SP4 are complete**; design + implementation plans live in
`docs/superpowers/specs/` and `docs/superpowers/plans/`. SP3 added per-SBA
member data (`members`/`dues`/`events`/`attendances`/`complaints`) with a
federation PII-minimization policy. SP4 added federation-**global** e-learning:
authoring in `/admin` (staff only), a "Kursus Saya" learner surface in both
panels, and a super-admin-only learning report. Skipping a spec/plan is how
this repo breaks — read the relevant one before touching an area.

**SP5 (operational reporting / secure export / kartu anggota) is not started.**
Its draft spec + plan live untracked in `docs/superpowers/`
(`...2026-09-08-fsbmm-website-sp5-operational-reporting.{md}`) — read them
before touching SBA reporting, exports, or member-cards. Federation reporting
is `super_admin`-only and `/verifikasi/kartu/{token}` must be declared ABOVE
the `{page:slug}` catch-all (spec §9.10).

Every plan records every deviation from spec/snippets as inline
`(** executed: ... **)` annotations. Preserve/append these when you change
behavior — never rewrite history.

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
`QuizViewPage`) live in per-panel dirs `app/Filament/Admin/Pages/` and
`app/Filament/Sba/Pages/` — **not** the panel-discovered `app/Filament/Pages`
(which doesn't exist / is empty). Each `*PanelProvider` registers them
explicitly via `->pages([...])`, and their pretty URLs
(`/courses/{record}`, `/courses/{record}/lessons/{lesson}`, `/quizzes/{record}`)
come from `Panel::authenticatedRoutes()` with implicit Livewire route-model
binding into `mount(?Course $record)`. Page-level `getRoutes()` does **not**
exist in Filament 3.3.55. To add a learning page, register it in BOTH
`->pages()` and `->authenticatedRoutes()` — never `routes/web.php`.

## Commands

- Test whole suite: `composer test` (runs `config:clear` then `php artisan test`).
  Single: `php artisan test --filter <Name>`.
- Lint: `vendor/bin/pint` (default Laravel Pint config — no `pint.json`, don't add
  one). NOT part of `composer test`; generated Filament `Create*/Edit*` pages can
  drift (unused imports) — run before committing.
- Frontend: `npm run dev` (dev) / `npm run build` (prod).
- Fresh install + seed + build: `composer setup`.

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

## Style

The public design pass (multi-color `--color-vivid-*` palette, gradients, blobs,
`resources/js/site.js` interactivity, redesigned widget/public blades) is
**committed** — don't revert or re-theme it. Only the `--color-brand-*` token
VALUES (Swiss-Brutalist Green family, `resources/css/app.css:3`) are still
placeholder: swap them when official brand assets arrive, nothing else.

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

## Verify before completion

Run `php artisan test` after non-trivial changes. Feature suite covers auth,
articles/scheduled publishing, e-resource library, organizations, SBA panel
tenant scoping + member data, e-learning authoring + learner progress + quiz
engine, federation overview, seeder, pages, and SEO.
