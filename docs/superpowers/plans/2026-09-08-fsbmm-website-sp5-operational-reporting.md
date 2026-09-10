# FSBMM Website — SP5 Implementation Plan (Operational Reporting, Secure Export & Kartu Anggota)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship SP5: operational reporting for SBA + federation, secure CSV/XLSX export, and member card system with QR verification.

**Architecture:** One Laravel 12 app, two existing Filament panels (`/admin`, `/panel-sba`). SP5 adds reporting pages, export functionality, and member card management. No new panels, no new auth systems, no new architecture.

**Tech Stack:** PHP 8.3+, Composer, Laravel 12, MySQL (prod) / SQLite (dev+test), Blade, Tailwind CSS v4, Filament 3, PHPUnit (feature tests), Vite.

**Spec:** `docs/superpowers/specs/2026-09-08-fsbmm-website-sp5-operational-reporting.md` (same repo, parent of this plan's `docs/superpowers/plans/`). The plan argues from the spec; read both.

## Global Constraints

- Working directory: `/home/dienk/fsbmm-website` (git repo, SP1–SP4 committed and green).
- Requires PHP 8.3+ and Composer. If `php -v` fails, STOP and ask user.
- UI copy and admin labels in **Bahasa Indonesia**. Code identifiers, migrations, and commit messages in English (repo convention).
- Dev DB = SQLite (`database/database.sqlite`); test DB = SQLite `:memory:` (phpunit.xml). Production `.env.example` documents MySQL.
- Roles are a fixed set of string constants on `User` (`ROLE_SUPER_ADMIN`, `ROLE_EDITOR`, `ROLE_SBA_ADMIN`), not a PHP enum. No permission package.
- Tenant scoping follows SP2 pattern: `getEloquentQuery()` scoped by `auth()->user()->organization_id`. No global scope.
- Federation staff see aggregate only — no individual member PII (SP3 convention).
- **Do not change SP1–SP4 behavior** unless directly required by SP5.
- **Do not add new panels, auth systems, API layers, or architecture.**
- Commit after every task's green test run.

## New Dependencies

| Package | Purpose | Version |
|---|---|---|
| `openspout/openspout` | XLSX export (streaming, low memory) | `^4.0` |
| `barryvdh/laravel-snappy` | PDF generation (wkhtmltopdf) | `^1.0` |
| `bacon/bacon-qr-code` | QR code generation | `^2.0` |

Install after Phase 0 (Repository Audit) confirms no existing alternatives.

## File Structure (locked in here)

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
resources/views/public/cards/verify.blade.php
database/seeders/MemberCardSeeder.php
tests/Feature/ReportingTest.php
tests/Feature/FederationReportingTest.php
tests/Feature/ExportTest.php
tests/Feature/MemberCardTest.php
tests/Feature/CardVerificationTest.php
```

## Implementation Order

```
PHASE 0 — Repository Audit + Baseline
        ↓
PHASE 1 — Security Boundary + Authorization Tests
        ↓
PHASE 2 — SBA Operational Reporting
        ↓
PHASE 3 — Federation Aggregate Reporting
        ↓
PHASE 4 — Secure Export (CSV + XLSX)
        ↓
P0 VALIDATION GATE
        ↓
PHASE 5 — Member Card Data Model
        ↓
PHASE 6 — Card Lifecycle + Service
        ↓
PHASE 7 — Card Management UI
        ↓
PHASE 8 — Public Card Verification
        ↓
PHASE 9 — Print/PDF
        ↓
PHASE 10 — Final Security + Regression
```

---

## PHASE 0 — Repository Audit + Baseline

### Task 0.1: Repository Audit

- [x] Read all existing models: `User`, `Organization`, `Member`, `Due`, `Event`, `Attendance`, `Complaint`, `Course`
- [x] Read existing Filament resources in `app/Filament/Sba/Resources/`
- [x] Read existing widgets: `MemberDataOverviewWidget`, `SbaAccountsOverviewWidget`
- [x] Check `composer.json` for existing export/PDF libraries (none expected)
- [x] Check `routes/web.php` for route order pattern
- [x] Document findings in plan annotations

**(** executed @task-0.1-audit: all models confirmed on disk and match plan's file-structure lock. **)**

#### Findings — existing models

- **`User`** (`app/Models/User.php`): role constants `ROLE_SUPER_ADMIN`/`ROLE_EDITOR`/`ROLE_SBA_ADMIN`; `isSuperAdmin()`, `isSbaAdmin()`, `organization()` belongsTo, `progress()`/`attempts()` (SP4); `canAccessPanel(Panel $panel)` match on panel id — `admin` → super_admin/editor, `sba` → sba_admin + non-null `organization_id`.
- **`Organization`** (`app/Models/Organization.php`): fillable `name,slug,company,logo_path,description,website,location,founded_year,member_count,is_published`; relations `users, members, dues, events, attendances, complaints` all present; `hasMembers()`, `syncMemberCount()`, `setFoundedYearAttribute` (null normalize), `setDescriptionAttribute` (sanitize HTML), `getRouteKeyName() => slug`, `scopePublished`.
- **`Member`** (`app/Models/Member.php`): `SoftDeletes`, `STATUS_ACTIVE='aktif'`/`STATUS_INACTIVE='nonaktif'`, fillable + casts as planned, relations `organization, dues, attendances, complaints`. **No `organization_id` FK inference issue — `belongsTo(Organization::class)` matches `organization_id` column automatically.**
- **`Due`** (`app/Models/Due.php`): fillable `organization_id, member_id, period, amount, paid_at, recorded_by`; casts `amount=decimal:2, paid_at=date`; relations `organization, member, recordedBy`.
- **`Event`** (`app/Models/Event.php`): fillable `organization_id, title, event_date, description`; cast `event_date=date`; relations `organization, attendances`.
- **`Attendance`** (`app/Models/Attendance.php`): fillable `organization_id, event_id, member_id, status, note`; relations `organization, event, member`.
- **`Complaint`** (`app/Models/Complaint.php`): fillable `organization_id, member_id, reporter_name, title, description, status, submitted_at, resolved_at, handled_by`; casts `submitted_at/resolved_at=date`; relations `organization, member`.
- **`Course`** (`app/Models/Course.php`): SP1 model extended with SP4 `pass_threshold` (fillable + integer cast), `lessons()` (orderBy sort_order), `quizzes()`, `finalQuiz(): ?CourseQuiz`. **`finalQuiz()` is a query method, NOT a relation — confirmed. `Course::published()` scope exists.**

**(** executed @task-0.1-audit: critical — existing `Organization::member_count` is the ONLY federation-visible member aggregate, and the spec's §7.2 clarification is needed: **"Total anggota tidak aktif" is NOT available** because `organizations` has no `total` column; `member_count` only counts `status=aktif` via `MemberObserver`. Federation querying `members.status` directly is PII-prohibited. **)**

#### Findings — SBA Filament resources (`app/Filament/Sba/Resources/`)

All 5 resources confirmed: `MemberResource`, `DuesResource`, `EventResource`, `ComplaintResource`, `OrganizationResource`.

**Tenant-scoping pattern (uniform across all 5):**
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('organization_id', auth()->user()->organization_id);
}
```
`OrganizationResource` uses `->whereKey(auth()->user()?->organization_id)` (single-row scope). **This is the exact pattern SP5's new reporting pages must follow.** No global scope present — confirmed (global scope would break public `/sba` directory).

**Navigation:** all SP3 resources share `->navigationGroup = 'Data Anggota'`. SP5 reporting pages should join this group; card management can be a separate nav item per spec §13 ("Kartu Anggota" near Anggota).

**DuesResource form:** member Select uses `->relationship('member', 'name', modifyQueryUsing: ...)` scoped to org — but the critical guard is the field-level `->rules()` closure (not `modifyQueryUsing`, which only scopes the dropdown). SP5 dues export/reporting must respect this same tenant boundary.

**ComplaintResource:** `member_id` is nullable + has a field-level closure rule rejecting cross-tenant members. Status is a reactive Select with badge formatting. **Complaint detail is shown in the SBA panel** (this is expected — the PII restriction is only for federation aggregate).

**(** executed @task-0.1-audit: deviation note — `ComplaintResource` resolves `member` relation for reporter name display in the table (`member.name` column). The SP5 complaint export whitelist (spec §8.2: ID, judul, status, tanggal pengajuan, tanggal penyelesaian) deliberately EXCLUDES member name/description. The export must not join `members` to avoid leaking PII into export output even though the resource itself shows it to authorized SBA admins. **)**

#### Findings — existing widgets (`app/Filament/Widgets/`)

- **`MemberDataOverviewWidget`**: `$isLazy = false`, `canView() => isSuperAdmin()`, 3 aggregates: `getTotalActiveMembers()` = `Organization::sum('member_count')` (**never queries `members` table**), `getCurrentMonthDuesTotal()` = `Due::where('period', now()->format('Y-m'))->sum('amount')`, `getOpenComplaintsCount()` = `Complaint::where('status', '!=', 'selesai')->count()`. **This is the exact pattern SP5's `FederationOperationsWidget` extends.**
- **`SbaAccountsOverviewWidget`**: `canView() => isSuperAdmin()`, shows SBA account list. Used as reference for the per-SBA breakdown table in `FederationOperationsWidget`.
- **`LearningReportWidget`**: `$isLazy = false`, `canView() => isSuperAdmin()`, delegates to `LearningProgress::report()`. Confirms widget pattern for super-admin-only federation data.

**(** executed @task-0.1-audit: plan Task 3.1 says "extend `MemberDataOverviewWidget` OR new `FederationOperationsWidget`." Decision: **create a new `FederationOperationsWidget`** (do NOT extend). Reasons: (1) the existing widget's 3-metric view is stable and tested; (2) the new 8-metric widget with per-SBA breakdown table is a different beast; (3) cleaner to keep the existing aggregate-only member/dues/complaints widget separate from the broader operational widget that includes events/attendances/cards. **)**

#### Findings — composer.json (dependencies)

**No export/PDF/QR libraries present.** Current `require`:
```json
"php": "^8.3",
"filament/filament": "^3.2",
"laravel/framework": "^12.0",
"laravel/tinker": "^2.10.1"
```
No `openspout/openspout`, no `barryvdh/laravel-snappy`, no `bacon/bacon-qr-code`. **All 3 must be added in Task 0.3.** Native PHP `fputcsv` is available for CSV (no library needed).

#### Findings — routes/web.php (route order)

Confirmed catch-all at **bottom**:
```php
Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');
```
All collection routes (`/berita`, `/sba`, `/e-resource`, `/e-learning`), sitemap, robots.txt, and named home/tentang/kontak routes are declared ABOVE it. **SP5's `/verifikasi/kartu/{token}` MUST be inserted before this catch-all** (per spec §9.10) or it will 404.

**(** executed @task-0.1-audit: the route order is exactly as the SP5 spec requires. The new verification route goes after the collection routes but BEFORE `Route::get('/{page:slug}', ...)`. If placed after, `/verifikasi/kartu/{token}` matches the `{page:slug}` parameter and hits `PageController::show('verifikasi/kartu/{token}')` → 404. **)**

#### Findings — panel providers

- **`AdminPanelProvider`**: id `admin`, path `admin`, discovers `app/Filament/Resources`, `app/Filament/Pages` (current empty), registers 4 learning pages explicitly, `authenticatedRoutes()` for course/lesson/quiz URLs, discovers `app/Filament/Widgets`. **SP5's `FederationOperationsWidget` will be auto-discovered from `app/Filament/Widgets` (no explicit registration needed) — but `canView()` gates it to super_admin.**
- **`SbaPanelProvider`**: id `sba`, path `panel-sba`, discovers `app/Filament/Sba/Resources`, `app/Filament/Sba/Pages`, `app/Filament/Sba/Widgets`. **SP5's reporting pages (`MemberReportPage`, etc.) must be registered in `->pages([...])` explicitly (they're custom pages, not auto-discovered from a non-existent `app/Filament/Pages` dir). Same pattern as SP4's `MyCoursesPage`.**

**(** executed @task-0.1-audit: important — the SbaPanelProvider auto-discovers pages from `app/Filament/Sba/Pages`, so SP5's reporting pages in that directory would be auto-discovered. But the plan explicitly lists them in the file structure and the SP4 pattern registered learning pages both via discovery AND explicit `->pages([...])`. **Recommendation: register SP5 reporting pages explicitly in `->pages([...])`** to match the SP4 precedent and avoid ordering/navigation surprises. **)**

### Task 0.2: Baseline Test

- [x] Run `composer test` — record baseline (249 passed, 826 assertions)
- [x] Run `vendor/bin/pint --test` — clean (208 files)
- [x] Run `npm run build` — succeeds (app.css 65.20 kB / site.js 3.07 kB gzipped)
- [x] Baseline green — no pre-SP5 failures to document

**(** executed @2026-09-08 task-0.2-baseline: full suite green before any SP5 work. **)**

### Task 0.3: Install Dependencies

- [x] `composer require openspout/openspout:^4.0 barryvdh/laravel-snappy:^1.0 bacon/bacon-qr-code:^2.0`
- [x] Run `composer test` — ensure no regressions
- [x] Run `vendor/bin/pint --test` — ensure clean
- [x] Run `npm run build` — ensure clean

**(** executed @2026-09-08 task-0.3-dependencies: Installed openspout, laravel-snappy, and bacon-qr-code successfully. Post-install suite green (249 passing, 826 assertions), pint clean, vite build successful. Phase 0 is fully complete and ready for Phase 1. **)

---

## PHASE 1 — Security Boundary + Authorization Tests

### Task 1.1: Security Test Setup

Create `tests/Feature/Sp5SecurityTest.php`:

- [x] Test: SBA A cannot access SBA B members
- [x] Test: SBA A cannot access SBA B reports
- [x] Test: SBA A cannot export SBA B data
- [x] Test: SBA A cannot create/revoke/print card for SBA B member
- [x] Test: Anonymous cannot access SBA reports
- [x] Test: Anonymous cannot access exports
- [x] Test: Anonymous can access card verification (minimal data)
- [x] Test: Editor cannot access individual member PII
- [x] Test: Editor cannot access federation aggregate reporting
- [x] Test: SBA admin cannot access federation reports

### Task 1.2: Authorization Guards

- [x] Verify all new pages/resources have `canAccess()` checks
- [x] Verify tenant scoping on all new queries
- [x] Verify no `organization_id` from request input is trusted

**(** executed @2026-09-08 task-1.2-guards: Acknowledged as mandatory constraints for all upcoming implementation phases. Phase 1 complete. **)

---

## PHASE 2 — SBA Operational Reporting

### Task 2.1: Member Report Page

Create `app/Filament/Sba/Pages/MemberReportPage.php`:

- [x] Filament custom page with filter form (status, department, position, education, gender, join date range)
- [x] Summary cards: total, active, inactive
- [x] Breakdown: by department, by position
- [x] Detail table (paginated, tenant-scoped)
- [x] Register in `SbaPanelProvider` → `->pages([...])`
- [x] Add navigation item: "Laporan" → "Anggota"

### Task 2.2: Dues Report Page

Create `app/Filament/Sba/Pages/DuesReportPage.php`:

- [x] Filter: period (single month), date range
- [x] Summary: payment count, total amount, average, active members, members without dues
- [x] Derived "unpaid" calculation: `active members - members with dues record`
- [x] Tenant-scoped queries

(** executed @2026-09-09 task-2.2-dues-report: `DuesReportPage` + `dues-report-page.blade.php` + `SbaPanelProvider->pages([...])` registration (nav "Laporan" → "Iuran", slug `dues-report`). Filters: single `period` + `period_start`/`period_end` range (YYYY-MM, regex-validated, live form). Summary cards: payment count, total nominal, average nominal, active members, and "belum tercatat membayar" = `active members (status=aktif) − distinct member_id with a dues record in the filtered scope`, clamped with `max(0, ...)` because dues rows from non-active members can exceed the active count. Table: member, period, amount (Rp format), paid_at, recorded_by. All queries tenant-scoped via `auth()->user()->organization_id`; no `dues` schema change. **)

### Task 2.3: Attendance Report Page

Create `app/Filament/Sba/Pages/AttendanceReportPage.php`:

- [x] Filter: event, date range
- [x] Summary: event count, participant count, hadir/izin/tidak hadir, attendance rate
- [x] Tenant-scoped queries

(** executed @2026-09-09 task-2.3-attendance-report: `AttendanceReportPage` + `attendance-report-page.blade.php` + `SbaPanelProvider->pages([...])` registration (nav "Laporan" → "Kegiatan & Absensi", slug `attendance-report`). Filters: single `event_id` (searchable Select, own org's events only) + `event_date_start`/`event_date_end` (date range on `events.event_date`). Attendance query is scoped to the org AND to the filtered event ids via `whereIn('event_id', ...)` subquery, so a spoofed foreign `event_id` matches nothing (server-side safe); the WHERE columns are qualified (`attendances.organization_id`, `attendances.event_id`) because the per-event breakdown JOINs `events` (unqualified `organization_id` threw an ambiguous-column error — caught by smoke test). Summary: event count, total participant records, hadir/izin/tidak_hadir counts, attendance rate = `hadir / total records` (0 when none). Extra: per-event rekap breakdown (event date formatted via `Carbon::parse(...)->format('d M Y')` since the joined column lands uncast on `Attendance` models) + detail table (event, member, status badge, note). **)

(** executed @2026-09-09 follow-up (post-Phase-2 evaluation, rekap vs event-count consistency): `getBreakdownByEvent()` previously inner-JOINed `events` onto `attendances`, so a filtered event with ZERO attendance rows counted in the "Jumlah Kegiatan" card but vanished from the per-event rekap. It now drives from `getFilteredEventQuery()` with a tenant-scoped `leftJoin` on `attendances` (join keys qualified: `attendances.event_id`, `attendances.organization_id`), `count(attendances.id)` so empty events group to one NULL-status row that every status falls through to 0, and the event query's WHERE/date filters got qualified (`events.organization_id`, `events.event_date`) to avoid the ambiguous-column trap the join introduced. `getFilteredEventQuery()` itself also had its `where('organization_id', …)` qualified (same ambiguity). New regression test `test_attendance_report_rekap_includes_events_without_attendance` in `ReportingTest` covers the empty-event case; suite 16 passed / 76 assertions for the report suite. **)

### Task 2.4: Complaint Report Page

Create `app/Filament/Sba/Pages/ComplaintReportPage.php`:

- [x] Filter: status, date range
- [x] Summary: total, per status breakdown
- [x] No complaint detail on federation aggregate

(** executed @2026-09-09 task-2.4-complaint-report: `ComplaintReportPage` + `complaint-report-page.blade.php` + `SbaPanelProvider->pages([...])` registration (nav "Laporan" → "Pengaduan", slug `complaint-report`). Filters: `status` (baru/diproses/selesai, placeholder = all) + `submitted_start`/`submitted_end` (date range on `submitted_at`, the complaint intake date). Summary cards ARE the per-status breakdown: total, baru, diproses, selesai, plus "belum selesai (open)" = `status != selesai` within the filtered scope. Table shows reporter, title, status badge, submitted_at, resolved_at (placeholder for null) — SBA admins own ComplaintResource so details are allowed in this panel; the "no detail on federation aggregate" rule belongs to Phase 3's `FederationOperationsWidget`, not this tenant-scoped page. **)

### Task 2.5: Reporting Tests

Create `tests/Feature/ReportingTest.php`:

- [x] Test: SBA member report shows correct counts
- [x] Test: SBA dues report shows correct aggregates
- [x] Test: SBA attendance report shows correct aggregates
- [x] Test: SBA complaint report shows correct counts
- [x] Test: Filters work server-side
- [x] Test: Pagination works
- [x] Test: Tenant isolation (SBA A doesn't see SBA B data)

(** executed @2026-09-09 task-2.5-reporting-tests: `tests/Feature/ReportingTest.php` — 15 tests covering all 4 report pages (counts/aggregates, server-side filters incl. period/date/event/status, member breakdowns + attendance per-event rekap, Livewire pagination via `gotoPage` (default per page = 10, first pagination option — page 3 holds records 21-30), and tenant isolation for each report (stats + Livewire assertDontSee). All green; suite at 272 passed with only the 2 known forward-looking SP5 failures (export/verification routes). **)

---

## PHASE 3 — Federation Aggregate Reporting

### Task 3.1: Federation Operations Widget

Create `app/Filament/Widgets/FederationOperationsWidget.php`:

- [x] Metrics: jumlah SBA, active members, current dues, kegiatan, peserta, open complaints, active cards, revoked cards
- [x] Per-SBA breakdown table (aggregate only, no PII)
- [x] `canView()`: `isSuperAdmin()` only
- [x] `$isLazy = false` (for testing)
- [x] Register in `AdminPanelProvider`

(** executed @2026-09-09 task-3.1-federation-operations-widget: `FederationOperationsWidget` (+ `filament.widgets.federation-operations` blade). Registration = auto-discovery via `AdminPanelProvider->discoverWidgets(... app/Filament/Widgets)` — matches MemberDataOverviewWidget/LearningReportWidget precedent; no explicit `->widgets()` entry needed (deviation note). `canView()` = `isSuperAdmin()`; `$isLazy = false`. Metrics: jumlah SBA (`organizations.count`), total anggota aktif (`SUM(organizations.member_count)` — never queries `members`), iuran bulan berjalan (`dues` where `period = now()->format('Y-m')`, same as MemberDataOverviewWidget), jumlah kegiatan (`events.count`), peserta hadir (`attendances` status `hadir` count), pengaduan terbuka (`complaints` status != `selesai`). Kartu aktif/dicabut + breakdown "Kartu Aktif" column: kept now via `Schema::hasTable('member_cards')` guard (`DB::table('member_cards')`) returning 0, per user decision — replace with `MemberCard` queries and delete `hasMemberCards()` when Task 5.2 lands. Per-SBA breakdown keyed by int org id (`mapWithKeys`) so zero-record orgs default 0; all GROUP BY queries MySQL `ONLY_FULL_GROUP_BY`-safe. **)

### Task 3.2: Federation Reporting Tests

Create `tests/Feature/FederationReportingTest.php`:

- [x] Test: aggregate shows correct totals across all SBA
- [x] Test: no NIK in response
- [x] Test: no member name in response
- [x] Test: no address in response
- [x] Test: no birthdate in response
- [x] Test: no salary in response
- [x] Test: editor cannot access widget (super_admin only, per spec §5)
- [x] Test: anonymous cannot access widget

(** executed @2026-09-09 task-3.2-federation-reporting-tests: `tests/Feature/FederationReportingTest.php` — 7 tests: metric aggregates across orgs, per-SBA breakdown incl. zero-data org, canView gating (editor + unauthenticated) + `$isLazy=false` reflection, super admin /admin render asserts totals + heading, editor dashboard hides widget, anonymous /admin redirect, no-PII assertions (name/NIK/address/birthdate/salary not in /admin HTML). **)

(** executed @2026-09-09 follow-up: complaint-content leak guard — `FederationReportingTest` grown to 8 tests with `test_admin_dashboard_never_leaks_complaint_content` (seeds an open complaint with a distinctive token in reporter_name/title/description; asserts the token + full reporter string absent from /admin HTML while the widget still counts it, and `Ringkasan Operasional Federasi` still renders — guards the 5th PII class per spec §7.2). **)

(** executed @2026-09-09 post-evaluation (independent audit): 0 Critical / 0 Important / 3 Minor — fixes applied for F1 + F3: (F1) `Schema::hasTable('member_cards')` memoized once per render via `private ?bool $memberCardsTableExists` (was 3 schema introspections/render; method unchanged); (F3) `test_super_admin_dashboard_shows_operations_widget` now also asserts the rendered Alpha dues row `assertSee('100.000', false)` (was: only grand total `125.000`). F2 (member_cards positive-path coverage) stays deferred to Phase 5 Task 5.2. **)

---

## PHASE 4 — Secure Export

### Task 4.1: Member Export

Create `app/Exports/MemberExport.php`:

- [x] Explicit column whitelist (nama, NIK, jenis kelamin, tempat lahir, tanggal lahir, alamat, departemen, jabatan, upah dasar, tanggal bergabung, pendidikan, status)
- [x] Tenant-scoped query
- [x] CSV format (native `fputcsv`)
- [x] XLSX format (openspout streaming)

(** executed @2026-09-09: stream branch spec §8.4 (
`MemberExport::streamFor()` → `response()->download(...)->deleteFileAfterSend(true)`),
bukan private-disk + TTL — file temp auto-hapus, tanpa job cleanup; flow storage
Task 4.5 tetap untuk download kartu Phase 6. Tenancy murni dari
`auth()->user()->organization_id`; query param `organization_id` sengaja tidak
dibaca (spoof diabaikan) — `test_sba_a_cannot_export_sba_b_data` di
`Sp5SecurityTest` di-update sesuai perilaku riil (sebelumnya assert 404 "route
belum ada"). Route di `SbaPanelProvider::authenticatedRoutes()` + middleware auth
panel → `test_anonymous_cannot_access_exports` otomatis hijau. XLSX via openspout
`openToFile` (bukan `openToBrowser` — output tak terbaca test client Laravel).
`gender` diekspor sebagai label (`L`→Laki-laki, `P`→Perempuan); tanggal
`Y-m-d`; `basic_salary` string decimal. Filename `anggota-{orgId}-{Ymd}`
(tanpa NIK, §8.4). **)

### Task 4.2: Dues Export

Create `app/Exports/DuesExport.php`:

- [x] Column whitelist (nama anggota, periode, nominal, tanggal pembayaran, pencatat)
- [x] Tenant-scoped
- [x] CSV + XLSX

(** executed @2026-09-09: Task 4.2 complete — commits 5fe68b2 (test) +
169be3e (feat): `DuesExport` stream CSV/XLSX + 5 tests baru di `ExportTest`
(9 total lintas Task 4.1+4.2: 4 member + 5 dues). Meniru struktur
`MemberExport` (stream temp-file + `deleteFileAfterSend(true)`); skeleton
duplikat sengaja dibiarkan utuh — konsolidasi ke helper bersama dijadwalkan
saat Task 4.3 (exporter ke-3). Deviations dari spec: (1) `fputcsv` tidak
me-quote sel header pendek tanpa spasi — header CSV riil
`"Nama Anggota",Periode,Nominal,"Tanggal Pembayaran",Pencatat` (keluarga
quoting sama dgn Task 4.1), jadi test meng-assert bentuk itu; (2) fixture test
plan membuat user `Bendahara Beta` yang tak terpakai sebagai pencatat —
`recorded_by` riil adalah `sba_admin` yang mengekspor (`$user->id`), jadi
content assertion memakai `$user->name` dan fixture mati dibuang. `amount`
string decimal (`50000.00`), `paid_at` `Y-m-d`, filter
`period`/`period_start`/`period_end` identik `DuesReportPage` (string
`YYYY-MM`). Route di `SbaPanelProvider::authenticatedRoutes()`; query param
`organization_id` tak dibaca (spoof diabaikan). Anonim export iuran di-assert
via `ExportTest` (redirect `/panel-sba/login`). **)

### Task 4.3: Attendance Export

Create `app/Exports/AttendanceExport.php`:

- [x] Column whitelist (nama anggota, kegiatan, tanggal, status, catatan)
- [x] Tenant-scoped
- [x] CSV + XLSX

(** executed @2026-09-10: Task 4.3 complete — commits 8a6696b (test) +
cab5684 (refactor) + a3ed381 (feat) + docs (commit yang memuat anotasi
ini): `AttendanceExport` stream CSV/XLSX + 5 tests baru di `ExportTest`
(14 total lintas Task 4.1–4.3: 4 member + 5 dues + 5 attendance).
Konsolidasi skeleton terealisasi: `ReportExport` abstract base memuat
seluruh boilerplate (`streamFor`/`writeCsv`/`writeXlsx`; temp-file +
`deleteFileAfterSend(true)`); tiap subclass hanya mengimplementasi
`columns()`/`filenamePrefix()`/`scopedQuery()`/`row()`. `MemberExport` +
`DuesExport` dimigrasi ke base, output byte-identical (9 tes existing
hijau sebelum attendance ditambahkan). Whitelist attendance {nama anggota,
kegiatan, tanggal (`event.event_date` `Y-m-d`), status (label
Hadir/Izin/Tidak Hadir — sama dgn badge `AttendanceReportPage`), catatan};
filename `kehadiran-{org}-{Y-m-d}`; filter `event_id`/`event_date_start`/
`event_date_end` identik `AttendanceReportPage` (Event discope org + rows
discope via `whereIn attendances.event_id` subquery → spoof foreign
`event_id` kosong). Deviations dari spec: `fputcsv` tidak me-quote sel
header pendek tanpa spasi — header CSV riil `"Nama Anggota",Kegiatan,Tanggal,
Status,Catatan` (keluarga quoting sama dgn Task 4.1/4.2), test meng-assert
bentuk itu. Route di `SbaPanelProvider::authenticatedRoutes()`; query param
`organization_id` tak dibaca (spoof diabaikan). Anonim + isolasi tenant
di-assert via `ExportTest` (redirect `/panel-sba/login`). **)

### Task 4.4: Complaint Export

Create `app/Exports/ComplaintExport.php`:

- [ ] Column whitelist (ID, judul, status, tanggal pengajuan, tanggal penyelesaian)
- [ ] Description only with explicit permission
- [ ] Tenant-scoped
- [ ] CSV + XLSX

### Task 4.5: Export Storage

- [ ] Private storage: use existing default `local` disk (root `storage_path('app/private')`) — no new disk config needed
- [ ] Temporary file with TTL (1 hour)
- [ ] Authorized download (signed URL or stream)
- [ ] Auto-cleanup

### Task 4.6: Export Tests

Create `tests/Feature/ExportTest.php`:

- [x] Test: CSV member export generates correct file
- [x] Test: XLSX member export generates correct file
- [x] Test: CSV/XLSX dues export
- [x] Test: Attendance export
- [ ] Test: Complaint export
- [x] Test: Export contains correct columns (whitelist)
- [x] Test: Export SBA A doesn't contain SBA B data
- [x] Test: Anonymous cannot export
- [x] Test: SBA admin cannot export other SBA

---

## P0 VALIDATION GATE

**Before proceeding to P1, ALL of the following must pass:**

- [ ] SBA member report works
- [ ] SBA dues report works
- [ ] SBA attendance report works
- [ ] SBA complaint report works
- [ ] Federation aggregate widget works
- [ ] CSV export works for all entities
- [ ] XLSX export works for all entities
- [ ] Authorization tests pass
- [ ] Tenant isolation tests pass
- [ ] PII boundary tests pass
- [ ] Export whitelist tests pass
- [ ] `composer test` — all green
- [ ] `vendor/bin/pint --test` — clean
- [ ] `npm run build` — clean

**If P0 not complete, DO NOT proceed to P1.**

---

## PHASE 5 — Member Card Data Model

### Task 5.1: Migration

Create `database/migrations/2026_09_08_000001_create_member_cards_table.php`:

- [ ] Table: `member_cards`
- [ ] Columns: `id`, `organization_id` (FK, index), `member_id` (FK, index), `card_number` (unique), `verification_token` (unique), `status` (enum: aktif/dicabut, index), `issued_at`, `revoked_at` (nullable), `revocation_reason` (nullable), `created_by` (FK), timestamps
- [ ] **`organization_id` is mandatory** — required for tenant scoping, federation aggregate, and consistency with SP3 tables
- [ ] Indexes: unique(card_number), unique(verification_token), index(member_id), index(organization_id), index(status)

### Task 5.2: Model

Create `app/Models/MemberCard.php`:

- [ ] Fillable: organization_id, member_id, card_number, verification_token, status, issued_at, revoked_at, revocation_reason, created_by
- [ ] Casts: issued_at datetime, revoked_at datetime
- [ ] Relations: member(), organization(), creator()
- [ ] Status constants: STATUS_ACTIVE = 'aktif', STATUS_REVOKED = 'dicabut'

### Task 5.3: Seeder

Create `database/seeders/MemberCardSeeder.php`:

- [ ] Create 1 active card for demo member
- [ ] Create 1 revoked card for another demo member
- [ ] Register in `DatabaseSeeder`

### Task 5.4: Card Tests

Create `tests/Feature/MemberCardTest.php`:

- [ ] Test: issue card for active member
- [ ] Test: card number is unique
- [ ] Test: verification token is unique
- [ ] Test: only one active card per member
- [ ] Test: revoke card
- [ ] Test: reissue card (old revoked, new active)
- [ ] Test: card history retained
- [ ] Test: inactive member cannot receive new card
- [ ] Test: sba_admin cannot issue card for other SBA member

---

## PHASE 6 — Card Lifecycle + Service

### Task 6.1: MemberCardService

Create `app/Support/MemberCardService.php`:

- [ ] `issue(Member $member, User $creator): MemberCard`
- [ ] `revoke(MemberCard $card, string $reason, User $actor): MemberCard`
- [ ] `reissue(MemberCard $oldCard, User $actor): MemberCard`
- [ ] `generateCardNumber(): string` — format `FSBMM-YYYY-XXXXXXXX` (8 random alphanumeric)
- [ ] `generateVerificationToken(): string` — random 64-char hex
- [ ] `resolveCurrentCard(Member $member): ?MemberCard`
- [ ] `validateToken(string $token): ?MemberCard`
- [ ] Collision handling: max 3 retries for card_number uniqueness
- [ ] Business rule: one active card per member (revoke old before issue new)

### Task 6.2: Service Tests

- [ ] Test: generateCardNumber returns correct format
- [ ] Test: generateVerificationToken returns unique tokens
- [ ] Test: issue creates card with correct fields
- [ ] Test: issue revokes existing active card
- [ ] Test: revoke sets status, revoked_at, revocation_reason
- [ ] Test: reissue creates new card and revokes old
- [ ] Test: validateToken returns card for valid token
- [ ] Test: validateToken returns null for invalid token
- [ ] Test: cross-tenant issue is denied

---

## PHASE 7 — Card Management UI

### Task 7.1: Member Card Page

Create `app/Filament/Sba/Pages/MemberCardPage.php`:

- [ ] List cards for current SBA members
- [ ] Issue card action (per member)
- [ ] View card details
- [ ] Revoke card action (with confirmation)
- [ ] Reissue card action
- [ ] Print action (triggers PDF generation)
- [ ] Card history visible (show revoked + active)
- [ ] Verification token NOT displayed by default (security credential)
- [ ] Register in `SbaPanelProvider` → `->pages([...])`
- [ ] Add navigation item: "Kartu Anggota"

### Task 7.2: Card UI Tests

- [ ] Test: SBA admin can see own cards
- [ ] Test: SBA admin cannot see other SBA cards
- [ ] Test: issue action works
- [ ] Test: revoke action works
- [ ] Test: reissue action works
- [ ] Test: verification token not in default view

---

## PHASE 8 — Public Card Verification

### Task 8.1: Route

**CRITICAL: Route MUST be declared BEFORE catch-all in `routes/web.php`:**

```php
// routes/web.php — add BEFORE the catch-all Route::get('/{page:slug}', ...)
Route::get('/verifikasi/kartu/{token}', [CardVerificationController::class, 'verify'])
    ->name('cards.verify');
```

- [ ] Add route before catch-all
- [ ] Test: route is accessible

### Task 8.2: Controller

Create `app/Http/Controllers/CardVerificationController.php`:

- [ ] Lookup token via `MemberCardVerificationService`
- [ ] Return minimal public-safe data (nama, SBA, status, full card number)
- [ ] Invalid token: generic error message
- [ ] Revoked card: "Kartu tidak aktif"
- [ ] No NIK, no address, no salary, no complaint, no dues
- [ ] No member enumeration

### Task 8.3: Verification Service

Create `app/Support/MemberCardVerificationService.php`:

- [ ] `lookup(string $token): ?array` — returns public-safe DTO
- [ ] Validates card status + member status
- [ ] Returns only: nama, organization name, card status, full card number

### Task 8.4: View

Create `resources/views/public/cards/verify.blade.php`:

- [ ] Extends `layouts.public`
- [ ] Shows verification result (valid/invalid/revoked)
- [ ] Mobile-friendly
- [ ] Professional, simple design

### Task 8.5: Verification Tests

Create `tests/Feature/CardVerificationTest.php`:

- [ ] Test: valid token shows minimal data
- [ ] Test: invalid token shows generic error
- [ ] Test: revoked card shows "Kartu tidak aktif"
- [ ] Test: no NIK in response
- [ ] Test: no address in response
- [ ] Test: no salary in response
- [ ] Test: no complaint in response
- [ ] Test: no dues in response
- [ ] Test: no member enumeration possible

---

## PHASE 9 — Print/PDF

### Task 9.1: PDF Generation

- [ ] Create card PDF template (front + back)
- [ ] Front: logo FSBMM, nama federasi, nama SBA, nama anggota, card number, QR code
- [ ] Back: pernyataan, informasi verifikasi, tanggal penerbitan, kontak
- [ ] Use `barryvdh/laravel-snappy` (wkhtmltopdf)
- [ ] QR code via `bacon/bacon-qr-code` (SVG output)
- [ ] PDF on-demand (not stored permanently)

### Task 9.2: Print Action

- [ ] Add print action to `MemberCardPage`
- [ ] Authorization: same as card operations
- [ ] No public PDF URLs
- [ ] No enumeration via PDF endpoint

### Task 9.3: PDF Tests

- [ ] Test: PDF generates successfully
- [ ] Test: PDF contains correct data
- [ ] Test: PDF contains QR code
- [ ] Test: unauthorized user cannot generate PDF

---

## PHASE 10 — Final Security + Regression

### Task 10.1: Security Regression Matrix

Create `tests/Feature/Sp5SecurityRegressionTest.php`:

- [ ] SBA A → Member SBA A: Allow
- [ ] SBA A → Member SBA B: 404/Forbidden
- [ ] SBA A → Card SBA A: Allow
- [ ] SBA A → Card SBA B: 404/Forbidden
- [ ] SBA A → Export SBA A: Allow
- [ ] SBA A → Export SBA B: Deny
- [ ] Anonymous → Member: Deny
- [ ] Anonymous → Export: Deny
- [ ] Anonymous → Card verification: Allow (minimal)
- [ ] Editor → Individual member PII: Deny
- [ ] Editor → Federation aggregate: Deny
- [ ] SBA → Federation report: Deny

### Task 10.2: Input Tampering Tests

- [ ] Test: organization_id from request is ignored
- [ ] Test: member_id from request is validated against tenant
- [ ] Test: card_id from request is validated against tenant
- [ ] Test: export filter cannot access other tenant

### Task 10.3: Full Regression

- [ ] Run `composer test` — all green (SP1–SP4 + SP5)
- [ ] Run `vendor/bin/pint --test` — clean
- [ ] Run `npm run build` — clean
- [ ] Run `php artisan migrate:fresh --seed` — clean
- [ ] Run `composer test` again after fresh migration

### Task 10.4: Manual QA

- [ ] Federation: `/admin` → Ringkasan Operasional widget
- [ ] SBA: `/panel-sba` → Laporan (4 reports)
- [ ] SBA: `/panel-sba` → Kartu Anggota
- [ ] Public: `/verifikasi/kartu/{token}`
- [ ] Existing public site: home, tentang, berita, SBA, e-resource, e-learning, kontak

### Task 10.5: Documentation

- [ ] Update `README.md` with SP5 scope
- [ ] Update `knowledge.md` with SP5 conventions
- [ ] Document deviations from spec (if any) with `(** executed: ... **)` annotations

---

## Definition of Done

SP5 is complete when:

### P0 — Reporting
- [ ] SBA member report
- [ ] SBA dues report
- [ ] SBA attendance report
- [ ] SBA complaint report
- [ ] Federation aggregate dashboard
- [ ] Filters work server-side
- [ ] Tenant isolation tested

### P0 — Export
- [ ] CSV + XLSX for all entities
- [ ] Explicit column whitelist
- [ ] Private/temporary file handling
- [ ] Tenant isolation tested
- [ ] Authorization tested

### P1 — Card
- [ ] `member_cards` table with `organization_id`
- [ ] Issue, revoke, reissue
- [ ] Card history
- [ ] Unique card number (collision handling)
- [ ] Unique verification token
- [ ] Print/PDF
- [ ] QR verification
- [ ] Public-safe verification endpoint

### P1 — Security
- [ ] Cross-tenant denial
- [ ] Public PII protection
- [ ] Editor PII restriction
- [ ] Export authorization
- [ ] Card authorization
- [ ] Token protection

### Quality
- [ ] SP1–SP4 tests still green
- [ ] SP5 tests green
- [ ] Pint clean
- [ ] npm build clean
- [ ] `php artisan migrate:fresh --seed` clean
- [ ] Manual QA passed

---

## Deviation Policy

If implementation differs from spec due to:
- Existing repository constraint
- Laravel/Filament limitation
- Database portability
- Deployment limitation
- Existing package capability

Then:
1. Choose smallest safe solution
2. Do not change scope
3. Document deviation with `(** executed: ... **)` annotation
4. Add/change tests to prove behavior meets requirement

---

## Final Verification

```bash
composer test
vendor/bin/pint --test
npm run build
php artisan migrate:fresh --seed
composer test
```

Manual smoke:
```
/admin → Ringkasan Operasional widget
/panel-sba/.../laporan → 4 reports accessible
/panel-sba/.../kartu → card management
/verifikasi/kartu/{token} → verification minimal
/ → public site normal
/sba → directory normal
/e-learning → catalog normal
```
