# FSBMM Website — SP3 Implementation Plan (Data Anggota per-SBA + Kebijakan PII)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship SP3 on top of the finished SP1+SP2 codebase: per-SBA member data (roster/profile, monthly dues, events + attendance, complaints) living entirely inside the existing `sba` Filament panel, tenant-scoped exactly like SP2's `Sba/Resources/OrganizationResource`, with `Organization.member_count` becoming a real computed number instead of a federation-typed placeholder. Federation staff get aggregate-only visibility (counts/sums, never individual PII rows) — this is a deliberate PII-minimization decision, not a follow-up TODO.

**Architecture:** Same one Laravel 12 app, same DB, same two Filament panels (`admin`, `sba`) — **no new panel**. Five new tenant tables (`members`, `dues`, `events`, `attendances`, `complaints`), every one carrying its own `organization_id` column (shared-schema, row-level tenancy — the pattern SP1 §3 set up and SP2 reused; SP3 just fills it with real data for the first time). Tenant scoping is `getEloquentQuery()` per Sba resource, never a global scope (same rationale as SP2: a global scope would break anything federation-side that legitimately spans organizations — here, the new aggregate widget). `Organization.member_count` is kept in sync by a `Member` model observer (`created`/`updated`/`deleted`/`restored`) that recalculates `members()->where('status','aktif')->count()` and saves quietly — the public site (`/sba/{slug}`) and SP1/SP2 admin form both already just read that column, so **zero public-route changes**.

**Tech Stack:** PHP 8.3+, Laravel 12, Filament 3 (both panels, unchanged versions from SP2), SQLite `:memory:` tests, PHPUnit feature tests, Pint.

**Spec:** `docs/superpowers/specs/2026-09-04-fsbmm-website-sp3-member-data.md` (same repo). SP1/SP2 spec+plan: read them, **never edit them**. SP1's plan records executed deviations as inline `(** executed: ... **)` annotations — preserve them; SP2's plan does the same. If SP3 work must deviate from SP1/SP2 behavior, append a new annotation in *this* plan (SP3's own), never rewrite SP1/SP2 history.

## Global Constraints

- Working directory: repo root. SP1+SP2 are committed and green; do not touch SP1/SP2 migrations, models' existing columns, or the `admin`/`sba` panel providers themselves — only additive changes (new tables, new columns via new migrations if ever needed, new resources, new widgets).
- UI copy and Filament labels in **Bahasa Indonesia**. Code identifiers, migration/class names, and commit messages in English (repo convention, unchanged since SP1).
- Every new table gets its own `organization_id` FK column, even where it's also reachable via `member_id`/`event_id` — this is the established row-level tenancy pattern (SP1 §3), not an SP3 invention. `restrictOnDelete()` on all five `organization_id` FKs (PII must never be left "dangling" without an owning org — contrast with SP2's `users.organization_id` which is deliberately `nullOnDelete`, because a detached SBA-admin account without PII is fine, but a detached PII row is not).
- **No new Filament panel.** All five new resources live under `app/Filament/Sba/Resources/` in the existing `sba` panel (id unchanged, path unchanged from SP2's `/panel-sba`).
- **Federation (`/admin`) gets zero new resources for `members`/`dues`/`attendances`/`complaints`.** Only one new dashboard widget, and that widget's queries must never `SELECT` a PII column (name, NIK, address, birthdate) — aggregates (`COUNT`, `SUM`, or reading the already-synced `organizations.member_count` column) only. This is tested explicitly (Task 6).
- `Member` uses `SoftDeletes`. `dues`/`attendances`/`complaints` referencing a soft-deleted member keep working (FK still valid; soft delete doesn't remove the row) — this is *why* soft delete was chosen over hard delete for `Member`.
- `member_count` sync must not fight the existing federation-manual value for orgs with zero members: the observer only recalculates for orgs that actually have `members` rows; `OrganizationResource`'s form field becomes `disabled()` only when `$organization->hasMembers()` is true.
- Commit after every task's green test run. Run `vendor/bin/pint` before committing.

## File Structure (locked in here)

```
database/migrations/2026_09_04_000009_create_members_table.php
database/migrations/2026_09_04_000010_create_dues_table.php
database/migrations/2026_09_04_000011_create_events_table.php
database/migrations/2026_09_04_000012_create_attendances_table.php
database/migrations/2026_09_04_000013_create_complaints_table.php
app/Models/Member.php
app/Models/Due.php
app/Models/Event.php
app/Models/Attendance.php
app/Models/Complaint.php
app/Models/Organization.php               (+ members(), dues(), events(), attendances(), complaints(), hasMembers(), syncMemberCount())
app/Observers/MemberObserver.php
app/Providers/AppServiceProvider.php      (+ Member::observe(MemberObserver::class))
database/factories/MemberFactory.php
database/factories/DueFactory.php
database/factories/EventFactory.php
database/factories/AttendanceFactory.php
database/factories/ComplaintFactory.php
app/Filament/Sba/Resources/MemberResource.php
app/Filament/Sba/Resources/MemberResource/Pages/{ListMembers,CreateMember,EditMember}.php
app/Filament/Sba/Resources/DuesResource.php
app/Filament/Sba/Resources/DuesResource/Pages/{ListDues,CreateDues,EditDues}.php
app/Filament/Sba/Resources/EventResource.php
app/Filament/Sba/Resources/EventResource/Pages/{ListEvents,CreateEvent,EditEvent}.php
app/Filament/Sba/Resources/EventResource/RelationManagers/AttendancesRelationManager.php
app/Filament/Sba/Resources/ComplaintResource.php
app/Filament/Sba/Resources/ComplaintResource/Pages/{ListComplaints,CreateComplaint,EditComplaint}.php
app/Filament/Sba/Widgets/OrganizationSummaryWidget.php    (modify — SP2 file, add member/dues/complaint stats)
resources/views/filament/widgets/organization-summary.blade.php  (modify — SP2 file)
app/Filament/Widgets/MemberDataOverviewWidget.php          (new — admin aggregate widget)
resources/views/filament/widgets/member-data-overview.blade.php
app/Filament/Resources/OrganizationResource.php            (modify — delete guard + member_count disabled())
database/seeders/MemberDataSeeder.php
database/seeders/DatabaseSeeder.php        (modify — wire MemberDataSeeder after OrganizationSeeder/SbaAccountSeeder)
README.md                                  (modify — scope table, roadmap)
tests/Feature/SbaMemberTest.php
tests/Feature/SbaDuesTest.php
tests/Feature/SbaEventAttendanceTest.php
tests/Feature/SbaComplaintTest.php
tests/Feature/MemberCountSyncTest.php
tests/Feature/MemberDataOverviewTest.php
tests/Feature/FederationOverviewTest.php   (extend — add `hasMembers()` guard cases to the existing SP2 delete-guard tests)
```

The SP2 organization-delete guard tests live in `tests/Feature/FederationOverviewTest.php` (verified: `test_organization_with_sba_accounts_cannot_be_deleted`, `test_organization_without_sba_accounts_can_be_deleted`, `test_bulk_delete_is_blocked_when_any_selected_organization_has_sba_accounts`). Extend that file with the `hasMembers()` guard cases — do not create a duplicate test file.

---

### Task 1: Five migrations + five models + factories + `Organization` relations/observer

**Files:**
- Create: the 5 migrations, `app/Models/{Member,Due,Event,Attendance,Complaint}.php`, `app/Observers/MemberObserver.php`, the 5 factories, `tests/Feature/MemberCountSyncTest.php`
- Modify: `app/Models/Organization.php`, `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Produces: all 5 tables with `organization_id` (`restrictOnDelete`, indexed); `Member` (`SoftDeletes`, `status` enum via string column + constants `STATUS_ACTIVE='aktif'`/`STATUS_INACTIVE='nonaktif'`), unique `(organization_id, nik)`; `Due` unique `(member_id, period)`; `Attendance` unique `(event_id, member_id)`; `Organization::members()/dues()/events()/attendances()/complaints()`, `hasMembers(): bool`, `syncMemberCount(): void`; `MemberObserver` wired in `AppServiceProvider::boot()`.

- [ ] **Step 1: Write the failing test `tests/Feature/MemberCountSyncTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCountSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_active_members_increments_member_count(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);

        Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);
        Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);

        $this->assertSame(2, $org->fresh()->member_count);
    }

    public function test_inactive_members_are_not_counted(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);

        Member::factory()->for($org)->create(['status' => Member::STATUS_INACTIVE]);

        $this->assertSame(0, $org->fresh()->member_count);
    }

    public function test_changing_status_to_inactive_decrements_count(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);

        $member->update(['status' => Member::STATUS_INACTIVE]);

        $this->assertSame(0, $org->fresh()->member_count);
    }

    public function test_soft_deleting_a_member_decrements_count(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);

        $member->delete(); // soft delete

        $this->assertSame(0, $org->fresh()->member_count);
        $this->assertSoftDeleted($member);
    }

    public function test_restoring_a_member_re_increments_count(): void
    {
        $org = Organization::factory()->create(['member_count' => 0]);
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);
        $member->delete();

        $member->restore();

        $this->assertSame(1, $org->fresh()->member_count);
    }

    public function test_organization_with_members_reports_has_members(): void
    {
        $with = Organization::factory()->create();
        Member::factory()->for($with)->create();

        $without = Organization::factory()->create();

        $this->assertTrue($with->hasMembers());
        $this->assertFalse($without->hasMembers());
    }

    public function test_nik_is_unique_per_organization_not_globally(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Member::factory()->for($orgA)->create(['nik' => '11223344']);
        $sameNikOtherOrg = Member::factory()->for($orgB)->create(['nik' => '11223344']);

        $this->assertDatabaseHas('members', ['id' => $sameNikOtherOrg->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Member::factory()->for($orgA)->create(['nik' => '11223344']);
    }
}
```

- [ ] **Step 2: Migrations**

```bash
php artisan make:migration create_members_table
php artisan make:migration create_dues_table
php artisan make:migration create_events_table
php artisan make:migration create_attendances_table
php artisan make:migration create_complaints_table
```

```php
// 2026_09_04_000009_create_members_table.php
Schema::create('members', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->restrictOnDelete();
    $table->string('nik');
    $table->string('name');
    $table->string('gender', 1)->nullable(); // 'L' | 'P'
    $table->string('birthplace')->nullable();
    $table->date('birthdate')->nullable();
    $table->text('address')->nullable();
    $table->string('department')->nullable();
    $table->string('position')->nullable();
    $table->decimal('basic_salary', 14, 2)->nullable();
    $table->date('join_date')->nullable();
    $table->string('education')->nullable();
    $table->string('status')->default('aktif'); // aktif | nonaktif
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['organization_id', 'nik']);
});
```

```php
// 2026_09_04_000010_create_dues_table.php
Schema::create('dues', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->restrictOnDelete();
    $table->foreignId('member_id')->constrained()->cascadeOnDelete();
    $table->string('period'); // 'YYYY-MM'
    $table->decimal('amount', 14, 2);
    $table->date('paid_at');
    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->unique(['member_id', 'period']);
});
```

```php
// 2026_09_04_000011_create_events_table.php
Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->restrictOnDelete();
    $table->string('title');
    $table->date('event_date');
    $table->text('description')->nullable();
    $table->timestamps();
});
```

```php
// 2026_09_04_000012_create_attendances_table.php
Schema::create('attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->restrictOnDelete();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->foreignId('member_id')->constrained()->cascadeOnDelete();
    $table->string('status'); // hadir | izin | tidak_hadir
    $table->text('note')->nullable();
    $table->timestamps();

    $table->unique(['event_id', 'member_id']);
});
```

```php
// 2026_09_04_000013_create_complaints_table.php
Schema::create('complaints', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->restrictOnDelete();
    $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
    $table->string('reporter_name');
    $table->string('title');
    $table->text('description');
    $table->string('status')->default('baru'); // baru | diproses | selesai
    $table->date('submitted_at');
    $table->date('resolved_at')->nullable();
    $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

- [ ] **Step 3: Models**

```php
// app/Models/Member.php
class Member extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'aktif';
    public const STATUS_INACTIVE = 'nonaktif';

    protected $fillable = [
        'organization_id', 'nik', 'name', 'gender', 'birthplace', 'birthdate',
        'address', 'department', 'position', 'basic_salary', 'join_date',
        'education', 'status',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'join_date' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    public function organization() { return $this->belongsTo(Organization::class); }
    public function dues() { return $this->hasMany(Due::class); }
    public function attendances() { return $this->hasMany(Attendance::class); }
    public function complaints() { return $this->hasMany(Complaint::class); }
}
```

The four remaining models follow the exact same shape as `Member`: `protected function casts(): array` (repo convention — every existing model uses this method form, not the `protected $casts` property), `protected $fillable`, and focused relations. All FK relation methods use the default Eloquent naming (`belongsTo(Organization::class)` matches the `organization_id` column automatically).

```php
// app/Models/Due.php
class Due extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'member_id', 'period', 'amount', 'paid_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function organization() { return $this->belongsTo(Organization::class); }
    public function member() { return $this->belongsTo(Member::class); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
}

// app/Models/Event.php
class Event extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'title', 'event_date', 'description'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function organization() { return $this->belongsTo(Organization::class); }
    public function attendances() { return $this->hasMany(Attendance::class); }
}

// app/Models/Attendance.php
class Attendance extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'event_id', 'member_id', 'status', 'note'];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function event() { return $this->belongsTo(Event::class); }
    public function member() { return $this->belongsTo(Member::class); }
}

// app/Models/Complaint.php
class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'member_id', 'reporter_name', 'title', 'description',
        'status', 'submitted_at', 'resolved_at', 'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'date',
            'resolved_at' => 'date',
        ];
    }

    public function organization() { return $this->belongsTo(Organization::class); }
    public function member() { return $this->belongsTo(Member::class); }
}
```

Note: `Attendance.status` and `Complaint.status` store plain strings (see migration defaults); the enum values are used as string literals in the resources/tests, not via model constants — no unconsumed constants added here.

- [ ] **Step 4: `Organization` model — relations + `hasMembers()` + `syncMemberCount()`**

```php
public function members() { return $this->hasMany(Member::class); }
public function dues() { return $this->hasMany(Due::class); }
public function events() { return $this->hasMany(Event::class); }
public function attendances() { return $this->hasMany(Attendance::class); }
public function complaints() { return $this->hasMany(Complaint::class); }

public function hasMembers(): bool
{
    return $this->members()->exists();
}

public function syncMemberCount(): void
{
    $this->member_count = $this->members()->where('status', Member::STATUS_ACTIVE)->count();
    $this->saveQuietly();
}
```

- [ ] **Step 5: `MemberObserver` + registration**

```php
// app/Observers/MemberObserver.php
class MemberObserver
{
    public function created(Member $member): void { $member->organization->syncMemberCount(); }
    public function updated(Member $member): void
    {
        if ($member->wasChanged('status')) {
            $member->organization->syncMemberCount();
        }
    }
    public function deleted(Member $member): void { $member->organization->syncMemberCount(); }
    public function restored(Member $member): void { $member->organization->syncMemberCount(); }
}
```

Register in `AppServiceProvider::boot()`: `Member::observe(MemberObserver::class);`

- [ ] **Step 6: Factories** — each factory's `definition()` must **not** set `organization_id` — it is supplied by the caller via Eloquent `->for($org)` (this is what keeps every factory composable per-tenant and keeps PII off a single hardcoded owner).

`MemberFactory` — `status` defaults to `Member::STATUS_ACTIVE`; `nik` is a **fictional long numeric string** (this repo is public per SP1 §8 — never seed/real NIKs), e.g. `(string) fake()->numberBetween(1000000000000000, 9999999999999999)`; `name`/`birthplace`/`department` etc. drawn from a small fixed list of Indonesian placeholder values (deterministic and locale-independent — avoids depending on whether the `id_ID` faker locale is installed):

```php
// database/factories/MemberFactory.php
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nik' => (string) fake()->numberBetween(1000000000000000, 9999999999999999),
            'name' => fake()->randomElement(['Yoga Pratama', 'Siti Rahma', 'Budi Santoso', 'Dewi Lestari', 'Andi Wijaya']),
            'gender' => fake()->randomElement(['L', 'P']),
            'birthplace' => fake()->randomElement(['Jakarta', 'Bandung', 'Karawang', 'Cikarang']),
            'birthdate' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'address' => fake()->streetAddress(),
            'department' => fake()->randomElement(['Produksi', 'Quality Control', 'Logistik', 'Teknik']),
            'position' => fake()->randomElement(['Operator', 'Staff', 'Supervisor']),
            'basic_salary' => fake()->numberBetween(3000000, 10000000),
            'join_date' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'education' => fake()->randomElement(['SMA', 'D3', 'S1']),
            'status' => Member::STATUS_ACTIVE,
        ];
    }
}
```

`DueFactory` (`period` = current month `Y-m` via `now()->format('Y-m')`, `amount` random 20000–150000, `paid_at` today), `EventFactory` (title + near-future `event_date`), `AttendanceFactory` (`status` random of the 3 string enum values: `hadir`/`izin`/`tidak_hadir`), `ComplaintFactory` (`status` default `baru`, `submitted_at` today). Use Eloquent factory `for($org)` in tests/seeders to attach `organization_id`.

- [ ] **Step 7: Run, confirm GREEN**

```bash
php artisan test --filter MemberCountSyncTest
```

- [ ] **Step 8: Commit** — `feat(members): member/dues/event/attendance/complaint tables + member_count sync observer`

---

### Task 2: `MemberResource` (Sba panel) — roster CRUD, tenant-scoped

**Files:**
- Create: `app/Filament/Sba/Resources/MemberResource.php` + `Pages/{List,Create,Edit}Member.php`, `tests/Feature/SbaMemberTest.php`

**Interfaces:**
- Produces: navigation group "Data Anggota" (shared by all SP3 Sba resources), `getEloquentQuery()` scoped to `Filament::auth()->user()->organization_id` (identical pattern to SP2's `Sba/Resources/OrganizationResource`), form fields per spec §6, table columns (name, nik, department, position, status badge — spec §6 requires jabatan in roster table), filter on `status`.

- [ ] **Step 1: Write the failing test `tests/Feature/SbaMemberTest.php`** — mirror `SbaOrganizationTest`'s structure from SP2 (helper `sbaUser()` returning `[$user, $org]`; `Filament::setCurrentPanel(Filament::getPanel('sba'))` before Livewire calls; 404 via URL edit milik org lain; `assertDatabaseHas` pada create-test untuk membuktikan `organization_id` ter-inject):

```php
<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\MemberResource\Pages\CreateMember;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaMemberTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya (MemberTest)']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_anonymous_is_redirected_to_sba_login(): void
    {
        $this->get('/panel-sba/members')->assertRedirect('/panel-sba/login');
    }

    public function test_super_admin_and_editor_cannot_access_the_sba_members_page(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba/members')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba/members')->assertForbidden();
    }

    public function test_sba_admin_can_list_their_own_members_only(): void
    {
        [$user, $org] = $this->sbaUser();
        Member::factory()->for($org)->create(['name' => 'Anggota Milik Saya']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        Member::factory()->for($other)->create(['name' => 'Anggota Milik Orang Lain']);

        $this->actingAs($user)
            ->get('/panel-sba/members')
            ->assertOk()
            ->assertSee('Anggota Milik Saya')
            ->assertDontSee('Anggota Milik Orang Lain');
    }

    public function test_editing_another_organizations_member_url_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create(['name' => 'SPM Milik Orang Lain']);
        $otherMember = Member::factory()->for($other)->create();

        $this->actingAs($user)
            ->get('/panel-sba/members/'.$otherMember->id.'/edit')
            ->assertNotFound();
    }

    public function test_creating_a_member_auto_links_the_logged_in_sba_organization(): void
    {
        [$user, $org] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateMember::class)
            ->fillForm([
                'nik' => '1122334455667788',
                'name' => 'Anggota Baru',
                'status' => 'aktif',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('members', [
            'name' => 'Anggota Baru',
            'organization_id' => $org->id,
        ]);
    }

    public function test_required_fields_are_validated_not_500(): void
    {
        [$user] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateMember::class)
            ->fillForm(['nik' => '', 'name' => ''])
            ->call('create')
            ->assertHasFormErrors(['nik' => 'required', 'name' => 'required']);
    }
}
```

Six tests total: anonymous redirect, role isolation (super_admin/editor forbidden), scoped list, cross-tenant 404, auto-link `organization_id` on create, required-field validation. The auto-link test is the load-bearing assertion for the "no organization picker" design intent — `assertDatabaseHas` proves `organization_id` matches the logged-in user's org regardless of whether a picker UI element exists.

- [ ] **Step 2: `MemberResource`**

```php
class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static ?string $navigationGroup = 'Data Anggota';
    protected static ?string $navigationLabel = 'Anggota';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', Filament::auth()->user()->organization_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nik')->required()->maxLength(32),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('gender')->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
            TextInput::make('birthplace')->maxLength(255),
            DatePicker::make('birthdate')->maxDate(now()),
            Textarea::make('address'),
            TextInput::make('department')->maxLength(255),
            TextInput::make('position')->maxLength(255),
            TextInput::make('basic_salary')->numeric()->prefix('Rp'),
            DatePicker::make('join_date'),
            TextInput::make('education')->maxLength(255),
            Select::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->required()->default('aktif'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('nik')->searchable(),
            TextColumn::make('department'),
            TextColumn::make('position')->label('Jabatan'),
            TextColumn::make('status')
                ->badge()
                ->formatStateUsing(static fn (string $state): string => $state === 'aktif' ? 'Aktif' : 'Nonaktif'),
        ])->filters([
            SelectFilter::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']),
        ]);
    }
}
```

`CreateMember` page overrides `mutateFormDataBeforeCreate()` to inject `$data['organization_id'] = Filament::auth()->user()->organization_id;` — this is the load-bearing line that keeps the form from ever needing (or trusting) a client-supplied org id.

- [ ] **Step 3: Run, confirm GREEN**

```bash
php artisan test --filter SbaMemberTest
```

- [ ] **Step 4: Commit** — `feat(sba): MemberResource — tenant-scoped roster CRUD`

---

### Task 3: `DuesResource` — monthly dues CRUD, tenant-scoped

**Files:**
- Create: `app/Filament/Sba/Resources/DuesResource.php` + Pages, `tests/Feature/SbaDuesTest.php`

**Interfaces:**
- Produces: navigation group "Data Anggota" (same as Task 2), `getEloquentQuery()` scoped to `Filament::auth()->user()->organization_id`, table columns per spec §6 (anggota/`member.name`, periode/`period`, jumlah/`amount` formatted Rp, tanggal bayar/`paid_at`, dicatat oleh/`recordedBy.name` nullable), form fields (member_id scoped relationship Select, period YYYY-MM regex, amount ≥ 0, paid_at); `organization_id` auto-injected + `recorded_by` auto-set from auth user in `mutateFormDataBeforeCreate()`; belt-and-suspenders server-side validation that member belongs to current org + composite unique `(member_id, period)` — both surfaced as Filament validation errors, not 500s.

- [ ] **Step 1: Write the failing test `tests/Feature/SbaDuesTest.php`** — mirror Task 2's test structure, **plus** period regex, unique constraint, and scoped member Select assertions:

```php
<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\DuesResource\Pages\CreateDues;
use App\Models\Due;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaDuesTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya (DuesTest)']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_anonymous_is_redirected_to_sba_login(): void
    {
        $this->get('/panel-sba/dues')->assertRedirect('/panel-sba/login');
    }

    public function test_super_admin_and_editor_cannot_access_the_sba_dues_page(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba/dues')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba/dues')->assertForbidden();
    }

    public function test_sba_admin_can_list_their_own_dues_only(): void
    {
        [$user, $org] = $this->sbaUser();
        $member = Member::factory()->for($org)->create();
        Due::factory()->for($org)->for($member)->create(['period' => '2026-09']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        $otherMember = Member::factory()->for($other)->create();
        Due::factory()->for($other)->for($otherMember)->create(['period' => '2026-09']);

        $this->actingAs($user)
            ->get('/panel-sba/dues')
            ->assertOk()
            ->assertSee('2026-09')
            ->assertDontSee('Anggota Milik Orang Lain');
    }

    public function test_editing_another_organizations_dues_url_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create(['name' => 'SPM Milik Orang Lain']);
        $otherMember = Member::factory()->for($other)->create();
        $otherDue = Due::factory()->for($other)->for($otherMember)->create();

        $this->actingAs($user)
            ->get('/panel-sba/dues/'.$otherDue->id.'/edit')
            ->assertNotFound();
    }

    public function test_creating_a_due_auto_links_organization_and_recorded_by(): void
    {
        [$user, $org] = $this->sbaUser();
        $member = Member::factory()->for($org)->create(['name' => 'Anggota Uji']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $member->id,
                'period' => '2026-09',
                'amount' => 75000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('dues', [
            'member_id' => $member->id,
            'period' => '2026-09',
            'organization_id' => $org->id,
            'recorded_by' => $user->id,
        ]);
    }

    public function test_required_fields_are_validated_not_500(): void
    {
        [$user] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm(['member_id' => null, 'period' => '', 'amount' => null, 'paid_at' => null])
            ->call('create')
            ->assertHasFormErrors(['member_id' => 'required', 'period' => 'required', 'amount' => 'required', 'paid_at' => 'required']);
    }

    public function test_period_must_match_yyyy_mm_format(): void
    {
        [$user, $org] = $this->sbaUser();
        $member = Member::factory()->for($org)->create();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $member->id,
                'period' => 'bukan-periode',
                'amount' => 50000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors(['period']);
    }

    public function test_duplicate_member_period_fails_validation(): void
    {
        [$user, $org] = $this->sbaUser();
        $member = Member::factory()->for($org)->create();
        Due::factory()->for($org)->for($member)->create(['period' => '2026-09']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $member->id,
                'period' => '2026-09',
                'amount' => 50000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors(['member_id']);
    }

    public function test_member_from_another_org_is_rejected(): void
    {
        [$user, $org] = $this->sbaUser();
        $ownMember = Member::factory()->for($org)->create(['name' => 'Anggota Milik Sendiri']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        $foreignMember = Member::factory()->for($other)->create(['name' => 'Anggota Milik Orang Lain']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        // Positive path: own member succeeds
        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $ownMember->id,
                'period' => '2026-10',
                'amount' => 50000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Negative path: foreign member rejected by server-side validation
        Livewire::actingAs($user)
            ->test(CreateDues::class)
            ->fillForm([
                'member_id' => $foreignMember->id,
                'period' => '2026-11',
                'amount' => 50000,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors(['member_id']);
    }
}
```

Nine tests total: anonymous redirect, role isolation, scoped list, cross-tenant 404, auto-link `organization_id` + `recorded_by`, required-field validation, period regex, unique constraint, and scoped member rejection. The last two tests (unique + foreign member) depend on server-side validation in `mutateFormDataBeforeCreate()` — without it they are RED, which is correct for TDD.

- [ ] **Step 2: `DuesResource` + `CreateDues` page**

```php
class DuesResource extends Resource
{
    protected static ?string $model = Due::class;
    protected static ?string $navigationGroup = 'Data Anggota';
    protected static ?string $navigationLabel = 'Iuran';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', Filament::auth()->user()->organization_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('member_id')
                ->relationship('member', 'name', modifyQueryUsing: fn (Builder $q) => $q->where('organization_id', Filament::auth()->user()->organization_id))
                ->required()
                ->searchable(),
            TextInput::make('period')
                ->required()
                ->rule('regex:/^\d{4}-(0[1-9]|1[0-2])$/')
                ->helperText('Format: YYYY-MM (contoh: 2026-09)'),
            TextInput::make('amount')
                ->numeric()
                ->required()
                ->minValue(0)
                ->prefix('Rp'),
            DatePicker::make('paid_at')
                ->required()
                ->default(now()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('member.name')->label('Anggota')->searchable(),
            TextColumn::make('period')->label('Periode')->sortable(),
            TextColumn::make('amount')
                ->label('Jumlah')
                ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float) $state, 0, ',', '.')),
            TextColumn::make('paid_at')
                ->label('Tanggal Bayar')
                ->date('d M Y'),
            TextColumn::make('recordedBy.name')
                ->label('Dicatat Oleh')
                ->placeholder('-'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDues::route('/'),
            'create' => Pages\CreateDues::route('/create'),
            'edit' => Pages\EditDues::route('/{record}/edit'),
        ];
    }
}
```

`CreateDues` page — `mutateFormDataBeforeCreate()` is the load-bearing method (belt-and-suspenders: UI scoping via `modifyQueryUsing` + server-side validation here + DB unique constraint as final backstop):

```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['organization_id'] = Filament::auth()->user()->organization_id;
    $data['recorded_by'] = Filament::auth()->user()->id;

    // Belt-and-suspenders: member must belong to this org (UI scopes the Select,
    // but a direct HTTP request could bypass the dropdown).
    if (! Member::where('id', $data['member_id'])
        ->where('organization_id', $data['organization_id'])->exists()) {
        throw ValidationException::withMessages([
            'member_id' => 'Anggota tidak terkait dengan organisasi ini.',
        ]);
    }

    // Composite unique (member_id, period) — surfaces as form error, not 500.
    if (Due::where('member_id', $data['member_id'])
        ->where('period', $data['period'])->exists()) {
        throw ValidationException::withMessages([
            'member_id' => 'Anggota ini sudah memiliki iuran untuk periode ini.',
        ]);
    }

    return $data;
}
```

- [ ] **Step 3: Run, confirm GREEN**

```bash
php artisan test --filter SbaDuesTest
```

- [ ] **Step 4: Commit** — `feat(sba): DuesResource — tenant-scoped monthly dues CRUD`

---

### Task 4: `EventResource` + `AttendancesRelationManager`

**Files:**
- Create: `app/Filament/Sba/Resources/EventResource.php` + Pages, `.../RelationManagers/AttendancesRelationManager.php`, `tests/Feature/SbaEventAttendanceTest.php`

**Interfaces:**
- Produces: navigation group "Data Anggota" (same as Tasks 2–3), `getEloquentQuery()` scoped to `Filament::auth()->user()->organization_id`, form fields (title required, event_date required, description nullable), table columns (title searchable, event_date sortable, created_at); `AttendancesRelationManager` attached to `EditEvent` — lists/creates `Attendance` rows for the event's own organization's members only (scoped Select), unique `(event_id, member_id)` enforced at DB level, `organization_id` auto-set in `mutateFormDataBeforeCreate()`.

- [ ] **Step 1: Write the failing test `tests/Feature/SbaEventAttendanceTest.php`** — mirror Task 2/3 patterns:

```php
<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\EventResource\Pages\CreateEvent;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaEventAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya (EventTest)']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_anonymous_is_redirected_to_sba_login(): void
    {
        $this->get('/panel-sba/events')->assertRedirect('/panel-sba/login');
    }

    public function test_super_admin_and_editor_cannot_access_sba_events(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba/events')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba/events')->assertForbidden();
    }

    public function test_sba_admin_can_list_their_own_events_only(): void
    {
        [$user, $org] = $this->sbaUser();
        Event::factory()->for($org)->create(['title' => 'Rapat Bulanan Saya']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        Event::factory()->for($other)->create(['title' => 'Rapat Orang Lain']);

        $this->actingAs($user)
            ->get('/panel-sba/events')
            ->assertOk()
            ->assertSee('Rapat Bulanan Saya')
            ->assertDontSee('Rapat Orang Lain');
    }

    public function test_editing_another_organizations_event_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create();
        $event = Event::factory()->for($other)->create();

        $this->actingAs($user)
            ->get('/panel-sba/events/'.$event->id.'/edit')
            ->assertNotFound();
    }

    public function test_creating_an_event_works(): void
    {
        [$user, $org] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateEvent::class)
            ->fillForm([
                'title' => 'Rapat Koordinasi',
                'event_date' => now()->format('Y-m-d'),
                'description' => 'Agenda bulanan',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'organization_id' => $org->id,
            'title' => 'Rapat Koordinasi',
        ]);
    }

    public function test_adding_attendance_for_own_member_works(): void
    {
        [$user, $org] = $this->sbaUser();
        $event = Event::factory()->for($org)->create();
        $member = Member::factory()->for($org)->create(['name' => 'Anggota Hadir']);

        Livewire::actingAs($user)
            ->test(\App\Filament\Sba\Resources\EventResource\RelationManagers\AttendancesRelationManager::class, ['ownerRecord' => $event])
            ->callTableAction('create', data: [
                'member_id' => $member->id,
                'status' => 'hadir',
                'note' => 'Hadir tepat waktu',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('attendances', [
            'event_id' => $event->id,
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'status' => 'hadir',
        ]);
    }

    public function test_adding_attendance_for_foreign_member_fails(): void
    {
        [$user, $org] = $this->sbaUser();
        $event = Event::factory()->for($org)->create();
        $other = Organization::factory()->create();
        $foreignMember = Member::factory()->for($other)->create();

        Livewire::actingAs($user)
            ->test(\App\Filament\Sba\Resources\EventResource\RelationManagers\AttendancesRelationManager::class, ['ownerRecord' => $event])
            ->callTableAction('create', data: [
                'member_id' => $foreignMember->id,
                'status' => 'hadir',
            ])
            ->assertHasTableActionErrors(['member_id']);
    }

    public function test_duplicate_attendance_fails(): void
    {
        [$user, $org] = $this->sbaUser();
        $event = Event::factory()->for($org)->create();
        $member = Member::factory()->for($org)->create();
        Attendance::factory()->for($event)->for($member)->for($org)->create();

        Livewire::actingAs($user)
            ->test(\App\Filament\Sba\Resources\EventResource\RelationManagers\AttendancesRelationManager::class, ['ownerRecord' => $event])
            ->callTableAction('create', data: [
                'member_id' => $member->id,
                'status' => 'izin',
            ])
            ->assertHasTableActionErrors(['member_id']);
    }

    public function test_attendance_status_must_be_valid(): void
    {
        [$user, $org] = $this->sbaUser();
        $event = Event::factory()->for($org)->create();
        $member = Member::factory()->for($org)->create();

        Livewire::actingAs($user)
            ->test(\App\Filament\Sba\Resources\EventResource\RelationManagers\AttendancesRelationManager::class, ['ownerRecord' => $event])
            ->callTableAction('create', data: [
                'member_id' => $member->id,
                'status' => 'status_tidak_valid',
            ])
            ->assertHasTableActionErrors(['status']);
    }
}
```

Nine tests: anonymous redirect, role isolation, scoped list, cross-tenant 404, event create auto-links org, attendance add own member, attendance add foreign member rejected, duplicate attendance rejected, invalid status rejected. The last three depend on server-side validation in `AttendancesRelationManager::mutateFormDataBeforeCreate()` — without it they are RED (correct for TDD).

- [ ] **Step 2: `EventResource` + `AttendancesRelationManager`**

```php
// app/Filament/Sba/Resources/EventResource.php
<?php

namespace App\Filament\Sba\Resources;

use App\Filament\Sba\Resources\EventResource\Pages;
use App\Filament\Sba\Resources\EventResource\RelationManagers\AttendancesRelationManager;
use App\Models\Event;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;
    protected static ?string $navigationGroup = 'Data Anggota';
    protected static ?string $navigationLabel = 'Kegiatan';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', Filament::auth()->user()->organization_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(255),
            Forms\Components\DatePicker::make('event_date')
                ->label('Tanggal')
                ->required(),
            Forms\Components\Textarea::make('description')
                ->label('Deskripsi')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
            Tables\Columns\TextColumn::make('event_date')->label('Tanggal')->date('d M Y')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->date('d M Y'),
        ]);
    }

    public static function getRelations(): array
    {
        return [AttendancesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
```

```php
// app/Filament/Sba/Resources/EventResource/RelationManagers/AttendancesRelationManager.php
<?php

namespace App\Filament\Sba\Resources\EventResource\RelationManagers;

use App\Models\Attendance;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('member_id')
                ->relationship('member', 'name', modifyQueryUsing: fn (Builder $q) => $q->where('organization_id', $this->getOwnerRecord()->organization_id))
                ->required()
                ->searchable(),
            Forms\Components\Select::make('status')
                ->options(['hadir' => 'Hadir', 'izin' => 'Izin', 'tidak_hadir' => 'Tidak Hadir'])
                ->required(),
            Forms\Components\Textarea::make('note')
                ->label('Catatan')
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('member.name')->label('Anggota')->searchable(),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge(fn (string $state) => match ($state) {
                    'hadir' => 'success',
                    'izin' => 'warning',
                    'tidak_hadir' => 'danger',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('note')->label('Catatan')->limit(50),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $event = $this->getOwnerRecord();

        $data['organization_id'] = $event->organization_id;
        $data['event_id'] = $event->id;

        if (! Member::where('id', $data['member_id'])
            ->where('organization_id', $data['organization_id'])->exists()) {
            throw ValidationException::withMessages([
                'member_id' => 'Anggota tidak terkait dengan organisasi ini.',
            ]);
        }

        if (Attendance::where('event_id', $data['event_id'])
            ->where('member_id', $data['member_id'])->exists()) {
            throw ValidationException::withMessages([
                'member_id' => 'Anggota ini sudah dicatat kehadirannya untuk kegiatan ini.',
            ]);
        }

        return $data;
    }
}
```

- [ ] **Step 3: Run, confirm GREEN**

```bash
php artisan test --filter SbaEventAttendanceTest
```

- [ ] **Step 4: Commit** — `feat(sba): EventResource + attendance relation manager — tenant-scoped`

---

### Task 5: `ComplaintResource`

**Files:**
- Create: `app/Filament/Sba/Resources/ComplaintResource.php` + Pages, `tests/Feature/SbaComplaintTest.php`

**Interfaces:**
- Produces: navigation group "Data Anggota" (same as Tasks 2–4), `getEloquentQuery()` scoped to `Filament::auth()->user()->organization_id`, form fields (reporter_name required, member_id nullable scoped Select, title required, description required, status Select default `baru` with reactive update, submitted_at required default now, resolved_at nullable), table columns (reporter_name, title, status badge, submitted_at); `EditComplaint::mutateFormDataBeforeSave()` sets `handled_by` when status changes away from `baru`; `resolved_at` auto-set to `now()` when status becomes `selesai`; cross-tenant `member_id` rejected by scoped Select + server-side check.

- [ ] **Step 1: Write the failing test `tests/Feature/SbaComplaintTest.php`**:

```php
<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\ComplaintResource\Pages\CreateComplaint;
use App\Filament\Sba\Resources\ComplaintResource\Pages\EditComplaint;
use App\Models\Complaint;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaComplaintTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya (ComplaintTest)']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_anonymous_is_redirected_to_sba_login(): void
    {
        $this->get('/panel-sba/complaints')->assertRedirect('/panel-sba/login');
    }

    public function test_super_admin_and_editor_cannot_access_sba_complaints(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba/complaints')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba/complaints')->assertForbidden();
    }

    public function test_sba_admin_can_list_their_own_complaints_only(): void
    {
        [$user, $org] = $this->sbaUser();
        Complaint::factory()->for($org)->create(['title' => 'Pengaduan Saya']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        Complaint::factory()->for($other)->create(['title' => 'Pengaduan Orang Lain']);

        $this->actingAs($user)
            ->get('/panel-sba/complaints')
            ->assertOk()
            ->assertSee('Pengaduan Saya')
            ->assertDontSee('Pengaduan Orang Lain');
    }

    public function test_editing_another_organizations_complaint_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create();
        $complaint = Complaint::factory()->for($other)->create();

        $this->actingAs($user)
            ->get('/panel-sba/complaints/'.$complaint->id.'/edit')
            ->assertNotFound();
    }

    public function test_creating_a_complaint_auto_links_org_and_sets_defaults(): void
    {
        [$user, $org] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateComplaint::class)
            ->fillForm([
                'reporter_name' => 'Budi Santoso',
                'title' => 'Fasilitas kantor rusak',
                'description' => 'AC ruang rapat tidak berfungsi sejak Senin.',
                'status' => 'baru',
                'submitted_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'organization_id' => $org->id,
            'reporter_name' => 'Budi Santoso',
            'status' => 'baru',
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        [$user] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateComplaint::class)
            ->fillForm([
                'reporter_name' => '',
                'title' => '',
                'description' => '',
                'submitted_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['reporter_name', 'title', 'description', 'submitted_at']);
    }

    public function test_member_id_accepts_null(): void
    {
        [$user, $org] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateComplaint::class)
            ->fillForm([
                'reporter_name' => 'Warga Umum',
                'title' => 'Pengaduan Tanpa Anggota',
                'description' => 'Tanpa data anggota.',
                'member_id' => null,
                'submitted_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'organization_id' => $org->id,
            'member_id' => null,
            'reporter_name' => 'Warga Umum',
        ]);
    }

    public function test_changing_status_to_diproses_sets_handled_by(): void
    {
        [$user, $org] = $this->sbaUser();
        $complaint = Complaint::factory()->for($org)->create(['status' => 'baru']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditComplaint::class, ['record' => $complaint])
            ->fillForm(['status' => 'diproses'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'diproses',
            'handled_by' => $user->id,
        ]);
    }

    public function test_changing_status_to_selesai_sets_handled_by_and_resolved_at(): void
    {
        [$user, $org] = $this->sbaUser();
        $complaint = Complaint::factory()->for($org)->create(['status' => 'baru']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditComplaint::class, ['record' => $complaint])
            ->fillForm(['status' => 'selesai'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'selesai',
            'handled_by' => $user->id,
        ]);

        $complaint->refresh();
        $this->assertNotNull($complaint->resolved_at);
    }

    public function test_cross_tenant_member_id_is_rejected(): void
    {
        [$user, $org] = $this->sbaUser();
        $other = Organization::factory()->create();
        $foreignMember = Member::factory()->for($other)->create();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateComplaint::class)
            ->fillForm([
                'reporter_name' => 'Pelapor Uji',
                'member_id' => $foreignMember->id,
                'title' => 'Pengaduan Silang',
                'description' => 'Uji cross-tenant.',
                'submitted_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors(['member_id']);
    }
}
```

Ten tests: anonymous redirect, role isolation, scoped list, cross-tenant 404, create auto-links org + defaults, required validation, nullable member_id, status→diproses sets handled_by, status→selesai sets handled_by + resolved_at, cross-tenant member rejected. The handled_by/resolved_at tests depend on `mutateFormDataBeforeSave()` in `EditComplaint` — without it they are RED (correct for TDD).

- [ ] **Step 2: `ComplaintResource` + `CreateComplaint`/`EditComplaint` pages**

```php
// app/Filament/Sba/Resources/ComplaintResource.php
<?php

namespace App\Filament\Sba\Resources;

use App\Filament\Sba\Resources\ComplaintResource\Pages;
use App\Models\Complaint;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;
    protected static ?string $navigationGroup = 'Data Anggota';
    protected static ?string $navigationLabel = 'Pengaduan';
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', Filament::auth()->user()->organization_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('reporter_name')
                ->label('Nama Pelapor')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('member_id')
                ->label('Terkait Anggota')
                ->relationship('member', 'name', modifyQueryUsing: fn (Builder $q) => $q->where('organization_id', Filament::auth()->user()->organization_id))
                ->searchable()
                ->nullable(),
            Forms\Components\TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->label('Keterangan')
                ->required()
                ->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options([
                    'baru' => 'Baru',
                    'diproses' => 'Diproses',
                    'selesai' => 'Selesai',
                ])
                ->required()
                ->default('baru')
                ->reactive(),
            Forms\Components\DatePicker::make('submitted_at')
                ->label('Tanggal Masuk')
                ->required()
                ->default(now()),
            Forms\Components\DatePicker::make('resolved_at')
                ->label('Tanggal Selesai')
                ->nullable()
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('reporter_name')->label('Pelapor')->searchable(),
            Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge(fn (string $state) => match ($state) {
                    'baru' => 'info',
                    'diproses' => 'warning',
                    'selesai' => 'success',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('submitted_at')->label('Masuk')->date('d M Y'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplaints::route('/'),
            'create' => Pages\CreateComplaint::route('/create'),
            'edit' => Pages\EditComplaint::route('/{record}/edit'),
        ];
    }
}
```

```php
// app/Filament/Sba/Resources/ComplaintResource/Pages/CreateComplaint.php
<?php

namespace App\Filament\Sba\Resources\ComplaintResource\Pages;

use App\Filament\Sba\Resources\ComplaintResource;
use App\Models\Member;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateComplaint extends CreateRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = Filament::auth()->user()->organization_id;

        if (! empty($data['member_id'])) {
            if (! Member::where('id', $data['member_id'])
                ->where('organization_id', $data['organization_id'])->exists()) {
                throw ValidationException::withMessages([
                    'member_id' => 'Anggota tidak terkait dengan organisasi ini.',
                ]);
            }
        }

        return $data;
    }
}
```

```php
// app/Filament/Sba/Resources/ComplaintResource/Pages/EditComplaint.php
<?php

namespace App\Filament\Sba\Resources\ComplaintResource\Pages;

use App\Filament\Sba\Resources\ComplaintResource;
use App\Models\Member;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditComplaint extends EditRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Filament::auth()->user();

        if (isset($data['status']) && $data['status'] !== 'baru') {
            $data['handled_by'] = $user->id;
        }

        if (isset($data['status']) && $data['status'] === 'selesai') {
            $data['resolved_at'] = now();
        }

        if (! empty($data['member_id'])) {
            if (! Member::where('id', $data['member_id'])
                ->where('organization_id', $this->record->organization_id)->exists()) {
                throw ValidationException::withMessages([
                    'member_id' => 'Anggota tidak terkait dengan organisasi ini.',
                ]);
            }
        }

        return $data;
    }
}
```

```php
// app/Filament/Sba/Resources/ComplaintResource/Pages/ListComplaints.php
<?php

namespace App\Filament\Sba\Resources\ComplaintResource\Pages;

use App\Filament\Sba\Resources\ComplaintResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

- [ ] **Step 3: Run, confirm GREEN**

```bash
php artisan test --filter SbaComplaintTest
```

- [ ] **Step 4: Commit** — `feat(sba): ComplaintResource — tenant-scoped, status workflow`

---

### Task 6: Dashboard widgets (SBA summary extension + federation aggregate-only) + `OrganizationResource` delete guard

**Files:**
- Modify: `app/Filament/Sba/Widgets/OrganizationSummaryWidget.php`, `resources/views/filament/widgets/organization-summary.blade.php`, `app/Filament/Resources/OrganizationResource.php`
- Create: `app/Filament/Widgets/MemberDataOverviewWidget.php`, `resources/views/filament/widgets/member-data-overview.blade.php`, `tests/Feature/MemberDataOverviewTest.php`
- Modify or extend `tests/Feature/FederationOverviewTest.php` with `hasMembers()` cases (the SP2 organization-delete guard tests live here: `test_organization_with_sba_accounts_cannot_be_deleted`, `test_organization_without_sba_accounts_can_be_deleted`, `test_bulk_delete_is_blocked_when_any_selected_organization_has_sba_accounts`). There is **no** `OrganizationDeleteGuardTest` file — that name does not exist.

**Interfaces:**
- SBA dashboard: `OrganizationSummaryWidget` gains active-member count, this-month dues total, open-complaint count — all scoped to the logged-in user's own org (reuses `hasMembers()`/query patterns from Tasks 1–5, no new scoping logic).
- Admin dashboard: `MemberDataOverviewWidget` — three stat cards, **federation-wide aggregates only**: `Organization::sum('member_count')`, `Due::where('period', now()->format('Y-m'))->sum('amount')`, `Complaint::where('status', '!=', 'selesai')->count()`. No per-organization or per-member breakdown table.
- `OrganizationResource`: `canDelete()`/bulk-delete guard extended with `|| $record->hasMembers()` (single) and the `using()` bulk callback extended analogously (mirror the exact SP2 structure — **do not** change the SP2 `hasSbaAccounts()` branch, only add an `||` condition); the existing `member_count` form field is a `TextInput` (`Forms\Components\TextInput::make('member_count')`, verified in `app/Filament/Resources/OrganizationResource.php:78`) — make it `->disabled(fn (?Organization $record) => $record?->hasMembers() ?? false)` with `->helperText(fn (?Organization $record) => $record?->hasMembers() ? 'Dihitung otomatis dari data anggota SBA (SP3).' : null)`.

- [ ] **Step 1: Write the failing test `tests/Feature/MemberDataOverviewTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\{Complaint, Due, Member, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDataOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_aggregate_numbers_on_admin_dashboard(): void
    {
        $org = Organization::factory()->create();
        Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);
        Due::factory()->for($org)->create(['period' => now()->format('Y-m'), 'amount' => 50000]);
        Complaint::factory()->for($org)->create(['status' => 'baru']);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('50.000', false); // formatted dues sum appears somewhere on the dashboard
    }

    public function test_admin_dashboard_response_never_contains_a_member_name_or_nik(): void
    {
        $org = Organization::factory()->create();
        $member = Member::factory()->for($org)->create(['name' => 'Nama Sangat Unik Sekali', 'nik' => '99998888']);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertDontSee('Nama Sangat Unik Sekali');
        $response->assertDontSee('99998888');
    }
}
```

(Second test is the load-bearing PII-minimization assertion for this whole task — it's what makes §8 of the spec enforceable rather than aspirational.)

- [ ] **Step 2: `MemberDataOverviewWidget`** — three `Stat::make()` cards; queries exactly as listed in Interfaces above. **No registration needed**: widgets in `app/Filament/Widgets/` are auto-discovered by `AdminPanelProvider` (`->discoverWidgets(in: app_path('Filament/Widgets'), ...)`), the same way `SbaAccountsOverviewWidget` is picked up without being listed in the `->widgets([...])` block (which only holds the default `AccountWidget`/`FilamentInfoWidget`). Just drop the file in `app/Filament/Widgets/` and the blade view in `resources/views/filament/widgets/`.

- [ ] **Step 3: Extend `OrganizationSummaryWidget`** (SBA dashboard) — this widget is a **custom Blade widget** (`protected static string $view = 'filament.widgets.organization-summary'`), **not** a `StatsOverviewWidget`, so do **not** use `Stat::make()`. Add the three org-scoped numbers (active members via `$organization->member_count`; this-month dues via `$organization->dues()->where('period', now()->format('Y-m'))->sum('amount')`; open complaints via `$organization->complaints()->where('status', '!=', 'selesai')->count()`) as extra `<dl>` rows / stat chips in `resources/views/filament/widgets/organization-summary.blade.php`, exposing them by adding a small `public function stats(): array` on the widget class (or computing inline in the blade). Existing SP2 widget content must stay green (additive only).

- [ ] **Step 4: `OrganizationResource` delete guard + `member_count` field** — locate the SP2 guard code (`hasSbaAccounts()` check in `canDelete()` + bulk `using()`), add the `hasMembers()` condition; locate the `member_count` form field, add `disabled()`/`helperText()`.

- [ ] **Step 5: Extend `tests/Feature/FederationOverviewTest.php`** with the `hasMembers()` guard cases — org with members (no SBA account) → single-delete blocked (`OrganizationResource::canDelete($org)` is `false`); org with members only → bulk-delete blocked (mirror the existing `test_bulk_delete_is_blocked_when_any_selected_organization_has_sba_accounts` shape, using `mountTableBulkAction('delete', [...])` with a member-bearing org). The existing SP2 cases already cover "org with neither can be deleted" and "org with SBA accounts blocked" — leave them untouched, just confirm they still pass.

- [ ] **Step 6: Run, confirm GREEN**

```bash
php artisan test --filter "MemberDataOverviewTest|FederationOverviewTest"
```

(`FederationOverviewTest` is the single SP2+SP3 organization-delete guard test class — no `OrganizationDeleteGuardTest` exists.)

- [ ] **Step 7: Commit** — `feat(dashboard): aggregate-only federation member-data widget + SBA summary extension + org-delete guard for members`

---

### Task 7: Demo seed data + README/env docs + full green + final verification

**Files:**
- Create: `database/seeders/MemberDataSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `README.md`

**Interfaces:**
- Produces: a handful of demo `members` (with plausible fake PII, **not real data** — this repo is public per SP1 §8 visibility note, never seed anything resembling the real spm-kecap-bango roster), a few `dues` rows for the current month, one demo `event` with mixed `attendances`, one demo `complaint`, per each SP1 demo organization. Idempotent (`firstOrCreate` keyed on `(organization_id, nik)` for members; skip if an org already has members, mirroring the "don't fight federation-manual member_count" rule from Global Constraints).

- [ ] **Step 1: `MemberDataSeeder`** — loop the three SP1 demo orgs (`spm-kecap-bango`, `spm-minuman-segar`, `spm-roti-nusantara`), for each: skip if `$org->hasMembers()`, else create ~5 fake members via `MemberFactory`, 1–2 `dues` rows per member for the current period, one `Event` with `Attendance` rows for all its members, one `Complaint`. Wire into `DatabaseSeeder::run()` **after** `SbaAccountSeeder::class` (orgs + SBA accounts must exist first; member data is independent of SBA accounts but the seeding order reads more naturally that way).

- [ ] **Step 2: README updates** — scope table: add a row for member/dues/event/attendance/complaint management, path `/panel-sba` (existing row), note "`member_count` kini dihitung otomatis begitu SBA punya data anggota (SP3)"; roadmap: move SP3 from pending to done (strikethrough like SP2's own entry); struktur-penting bullet list: add the 5 new models; security notes: add the PII-minimization principle (federation sees aggregates only) as a one-line bullet, pointing to the SP3 spec.

- [ ] **Step 3: Full suite green + lint**

```bash
php artisan test
vendor/bin/pint
npm run build
```

Expected: every SP1+SP2 test plus all SP3 tests pass; Pint clean; no public-site/build changes (SP3 touches zero Blade/CSS files, zero public routes).

- [ ] **Step 4: Final verification**

```bash
php artisan migrate:fresh --seed
php artisan serve   # manual smoke:
```

- `/panel-sba` login as `pengurus@spm-kecap-bango.fsbmm.test`: "Data Anggota" nav group shows Anggota/Iuran/Kegiatan/Pengaduan; roster shows the seeded demo members; creating a new member updates the dashboard's active-member stat immediately.
- `/sba/spm-kecap-bango` (public) now shows the real seeded member count, not a stale federation-typed number.
- `/admin` dashboard shows the new aggregate widget with federation-wide totals; **view-source contains no member name or NIK anywhere on the page**.
- Attempting (via a second demo SBA login) to open another organization's member/dues/event/complaint edit URL by guessing an id → 404.
- `/berita`, `/e-resource`, `/e-learning`, `/sba` index unaffected.

- [ ] **Step 5: Commit** — `feat(members): demo seed data + README docs for SP3`

---

## Final Verification

```bash
php artisan test                  # all SP1 + SP2 + SP3 feature tests pass
vendor/bin/pint                   # clean
npm run build                     # production assets build clean (unchanged by SP3)
php artisan migrate:fresh --seed  # clean DB: SP1 demo content + SP2 SBA accounts + SP3 demo member data
php artisan serve                 # manual smoke: /panel-sba (Anggota/Iuran/Kegiatan/Pengaduan),
                                   # /admin (aggregate widget, no PII in HTML), /sba/{slug} (real member_count)
```

## Guard-count/scope bookkeeping

SP3 adds: 5 migrations, 5 models (+1 observer), 4 new Sba-panel resources (one with a relation manager), 2 dashboard widgets (1 new + 1 extended), 1 seeder, 1 extended delete-guard — 6 new feature test files plus extensions to an existing SP2 guard test. Public site surface is **unchanged** (zero Blade/CSS edits, zero new/changed public routes) — SP3 is entirely inside the existing `sba` panel plus one read-only aggregate addition to the existing `admin` panel dashboard.

## Non-Goals (from spec, repeated for executor clarity)

- No member photos, no ID-card generation, no multi-year wage-negotiation scenarios (`basic_salary` is a single reference field).
- No buku kas, no surat-menyurat, no sanksi, no pesangon modules.
- No bulk CSV/Excel roster import — manual entry only.
- No federation access to individual `members`/`dues`/`attendances`/`complaints` rows, ever — aggregate widget only, enforced by `MemberDataOverviewTest`'s "never contains a name or NIK" assertion.
- No field-level PII encryption — access control (tenant scoping + panel auth) is the only control layer, by deliberate decision (spec §8).
- No detailed dues-arrears report, no complaint escalation-to-federation workflow, no email/SMS notifications.
- No self-registration, no public/member login — unchanged from SP1/SP2.
