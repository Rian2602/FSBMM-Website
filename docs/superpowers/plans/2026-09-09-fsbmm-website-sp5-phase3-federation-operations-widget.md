# SP5 Phase 3 — Task 3.1: Federation Operations Widget — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: `superpowers:subagent-driven-development` or `superpowers:executing-plans`. Steps use `- [ ]` syntax.

**Goal:** Add the super-admin-only `Ringkasan Operasional Federasi` dashboard widget (`FederationOperationsWidget`) to `/admin`, showing 8 aggregate metrics + a per-SBA aggregate breakdown, with a no-PII guarantee.

**Architecture:** Follows the exact `MemberDataOverviewWidget` / `SbaAccountsOverviewWidget` precedent — a Filament `Widget` in `app/Filament/Widgets/` (auto-discovered by `AdminPanelProvider`), `canView()` = `isSuperAdmin()`, `$isLazy = false`, server-rendered blade with inline-styled metric cards + a plain `<table>` breakdown. All queries are `COUNT`/`SUM` grouped aggregates — never individual `members` rows.

**Tech Stack:** Laravel 12 / Filament 3.3.55 / PHP 8.3+ / SQLite (tests) / MySQL (prod).

## Global Constraints (from AGENTS.md + SP5 spec)

1. **PII boundary:** federation aggregate only. Prohibited in `/admin`: NIK, name, address, birthdate, salary, complaint content. `SUM(member_count)` + `COUNT`/`SUM` by DB GROUP BY only — never touch `members` individual rows.
2. Spec §6.6: aggregate SQL, no `Member::all()` → count-in-PHP.
3. `canView()` uses `auth()->user()?->isSuperAdmin() ?? false` — **never** `Filament::auth()` (null in Livewire test context).
4. `$isLazy = false` so content is in the initial `/admin` HTTP response (widget tests assert on it).
5. **Registration is auto-discovery** — `AdminPanelProvider->discoverWidgets(in: app_path('Filament/Widgets')...)` already covers `app/Filament/Widgets`. The checklist's "Register in AdminPanelProvider" needs **no explicit `->widgets()` entry** (precedent: `MemberDataOverviewWidget`, `LearningReportWidget`, `SbaAccountsOverviewWidget`).
6. `member_cards` table **does not exist until Phase 5** (no migration on disk). **User-approved decision:** keep the full 8-metric + 5-column surface now under a `Schema::hasTable('member_cards')` guard returning `0` (via `DB::table(...)`, since no `MemberCard` model exists yet); replace with `MemberCard` queries and delete the guard when Task 5.2 lands. Schema from canonical Task 5.1: `status` (`aktif`/`dicabut`), `organization_id` (mandatory FK).
7. Current-period dues = `now()->format('Y-m')` (exact pattern of `MemberDataOverviewWidget::getCurrentMonthDuesTotal`).
8. `Organization.member_count` is auto-synced by `MemberObserver` in tests (no `WithoutModelEvents`) → create `status=aktif` members and assert **effective** counts; never assert the factory's random 50–2000 default. Zero-data orgs need explicit `['member_count' => 0]`.
9. `dues` unique `(member_id, period)` → one `Due` row per member+period in fixtures.
10. Indonesian labels; `number_format($n, 0, ',', '.')`; `Rp ` money prefix.
11. `vendor/bin/pint` clean before committing; `composer test` at the end (the 2 pre-existing `Sp5SecurityTest` expected failures stay).

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `app/Filament/Widgets/FederationOperationsWidget.php` | Create | Metrics + per-SBA breakdown getters, `canView()`, `$isLazy` |
| `resources/views/filament/widgets/federation-operations.blade.php` | Create | 8 metric cards + 6-column breakdown table |
| `tests/Feature/FederationReportingTest.php` | Create | Full widget test suite (covers canonical Task 3.2 targets) |
| `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` | Modify (append) | `(** executed **)` annotations on Task 3.1 + Task 3.2 |

## Task 1 — Widget class + metrics getters (tests first)

**Files:**
- Create: `app/Filament/Widgets/FederationOperationsWidget.php`
- Test: `tests/Feature/FederationReportingTest.php`

- [ ] **Step 1 — Write the failing tests** (`tests/Feature/FederationReportingTest.php`):

```php
<?php

namespace Tests\Feature;

use App\Filament\Widgets\FederationOperationsWidget;
use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class FederationReportingTest extends TestCase
{
    use RefreshDatabase;

    private function seedOperationalData(): void
    {
        $orgA = Organization::factory()->create(['name' => 'SPM Alpha', 'member_count' => 0]);
        $orgB = Organization::factory()->create(['name' => 'SPM Beta', 'member_count' => 0]);
        Organization::factory()->create(['name' => 'SPM Gamma', 'member_count' => 0]);

        $period = now()->format('Y-m');

        $a1 = Member::factory()->for($orgA)->create();
        $a2 = Member::factory()->for($orgA)->create();
        $b1 = Member::factory()->for($orgB)->create();

        Due::factory()->for($orgA, 'organization')->for($a1, 'member')->create(['period' => $period, 'amount' => 50000]);
        Due::factory()->for($orgA, 'organization')->for($a2, 'member')->create(['period' => $period, 'amount' => 50000]);
        Due::factory()->for($orgB, 'organization')->for($b1, 'member')->create(['period' => $period, 'amount' => 25000]);

        $eventA1 = Event::factory()->for($orgA)->create(['title' => 'Rapat Alpha']);
        $eventA2 = Event::factory()->for($orgA)->create(['title' => 'Pelatihan Alpha']);
        $eventB1 = Event::factory()->for($orgB)->create(['title' => 'Rapat Beta']);

        Attendance::factory()->for($orgA, 'organization')->for($eventA1, 'event')->for($a1, 'member')->create(['status' => 'hadir']);
        Attendance::factory()->for($orgA, 'organization')->for($eventA2, 'event')->for($a2, 'member')->create(['status' => 'hadir']);
        Attendance::factory()->for($orgA, 'organization')->for($eventA1, 'event')->for($a2, 'member')->create(['status' => 'izin']);
        Attendance::factory()->for($orgB, 'organization')->for($eventB1, 'event')->for($b1, 'member')->create(['status' => 'hadir']);

        Complaint::factory()->for($orgA, 'organization')->create(['status' => 'baru']);
        Complaint::factory()->for($orgB, 'organization')->create(['status' => 'selesai']);
    }

    public function test_metrics_aggregate_across_all_orgs(): void
    {
        $this->seedOperationalData();

        auth()->login(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $metrics = (new FederationOperationsWidget)->getMetrics();

        $this->assertSame(3, $metrics['organizations']);
        $this->assertSame(3, $metrics['active_members']);
        $this->assertSame(125000.0, $metrics['current_dues']);
        $this->assertSame(3, $metrics['events']);
        $this->assertSame(3, $metrics['attendances_hadir']);
        $this->assertSame(1, $metrics['open_complaints']);
        $this->assertSame(0, $metrics['active_cards']);
        $this->assertSame(0, $metrics['revoked_cards']);
    }

    public function test_widget_is_super_admin_only_and_server_rendered(): void
    {
        $this->assertFalse(FederationOperationsWidget::canView());

        auth()->login(User::factory()->create(['role' => User::ROLE_EDITOR]));
        $this->assertFalse(FederationOperationsWidget::canView());

        auth()->login(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
        $this->assertTrue(FederationOperationsWidget::canView());

        $isLazy = (new ReflectionClass(FederationOperationsWidget::class))->getStaticPropertyValue('isLazy');
        $this->assertFalse($isLazy);
    }
}
```

- [ ] **Step 2 — Run tests to verify they fail**

Run: `php artisan test --filter FederationReportingTest`
Expected: FAIL — class `App\Filament\Widgets\FederationOperationsWidget` not found.

- [ ] **Step 3 — Implement the widget class** (`app/Filament/Widgets/FederationOperationsWidget.php`):

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Organization;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FederationOperationsWidget extends Widget
{
    protected static string $view = 'filament.widgets.federation-operations';

    protected int|string|array $columnSpan = 'full';

    // (** executed: SP5 Task 3.1 — server-rendered so totals are in the initial
    // /admin HTML and the no-PII dashboard assertions are real (SP3/SP4
    // widget convention, same as MemberDataOverviewWidget). **)
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getMetrics(): array
    {
        return [
            'organizations' => Organization::count(),
            'active_members' => (int) Organization::sum('member_count'),
            'current_dues' => (float) Due::where('period', now()->format('Y-m'))->sum('amount'),
            'events' => Event::count(),
            'attendances_hadir' => Attendance::where('status', 'hadir')->count(),
            'open_complaints' => Complaint::where('status', '!=', 'selesai')->count(),
            // ponytail: member_cards arrives in Phase 5 (Task 5.1/5.2); the
            // hasTable guard keeps the 8-metric surface rendering 0 until then.
            // Replace these DB::table queries with MemberCard::where(status, ...)
            // and delete hasMemberCards() when Task 5.2 lands.
            'active_cards' => $this->hasMemberCards() ? (int) DB::table('member_cards')->where('status', 'aktif')->count() : 0,
            'revoked_cards' => $this->hasMemberCards() ? (int) DB::table('member_cards')->where('status', 'dicabut')->count() : 0,
        ];
    }

    private function hasMemberCards(): bool
    {
        return Schema::hasTable('member_cards');
    }
}
```

- [ ] **Step 4 — Run tests to verify they pass**

Run: `php artisan test --filter test_metrics_aggregate_across_all_orgs test_widget_is_super_admin_only_and_server_rendered`
Expected: PASS (both).

> Note: the widget has `canView()` true for the test user and renders on `/admin`, but the view doesn't exist yet — do **not** hit `/admin` HTTP until Task 3.

- [ ] **Step 5 — Commit**

```bash
git add app/Filament/Widgets/FederationOperationsWidget.php tests/Feature/FederationReportingTest.php
git commit -m "feat(sp5): FederationOperationsWidget metrics + super-admin gating"
```

## Task 2 — Per-SBA breakdown getter

**Files:**
- Modify: `app/Filament/Widgets/FederationOperationsWidget.php`
- Test: `tests/Feature/FederationReportingTest.php`

**Interfaces:**
- Consumes: `getMetrics()`, `canView()`, `hasMemberCards()` from Task 1.
- Produces: `getPerSbaBreakdown(): array` returning `list<array{name: string, active_members: int, current_dues: float, events: int, open_complaints: int, active_cards: int}>` ordered by org name.

- [ ] **Step 1 — Write the failing test** (append to `FederationReportingTest`):

```php
public function test_per_sba_breakdown_is_aggregate_only(): void
{
    $this->seedOperationalData();

    auth()->login(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

    $breakdown = (new FederationOperationsWidget)->getPerSbaBreakdown();

    $this->assertCount(3, $breakdown);
    $this->assertSame('SPM Alpha', $breakdown[0]['name']);
    $this->assertSame(2, $breakdown[0]['active_members']);
    $this->assertSame(100000.0, $breakdown[0]['current_dues']);
    $this->assertSame(2, $breakdown[0]['events']);
    $this->assertSame(1, $breakdown[0]['open_complaints']);
    $this->assertSame(0, $breakdown[0]['active_cards']);

    $this->assertSame('SPM Beta', $breakdown[1]['name']);
    $this->assertSame(1, $breakdown[1]['active_members']);
    $this->assertSame(25000.0, $breakdown[1]['current_dues']);
    $this->assertSame(1, $breakdown[1]['events']);
    $this->assertSame(0, $breakdown[1]['open_complaints']);

    $this->assertSame('SPM Gamma', $breakdown[2]['name']);
    $this->assertSame([0, 0.0, 0, 0, 0], [
        $breakdown[2]['active_members'],
        $breakdown[2]['current_dues'],
        $breakdown[2]['events'],
        $breakdown[2]['open_complaints'],
        $breakdown[2]['active_cards'],
    ]);
}
```

- [ ] **Step 2 — Run to verify it fails**

Run: `php artisan test --filter test_per_sba_breakdown_is_aggregate_only`
Expected: FAIL — call to undefined method `getPerSbaBreakdown()`.

- [ ] **Step 3 — Implement `getPerSbaBreakdown()`** (add to the widget class):

```php
public function getPerSbaBreakdown(): array
{
    $duesByOrg = Due::where('period', now()->format('Y-m'))
        ->selectRaw('organization_id, sum(amount) as total')
        ->groupBy('organization_id')
        ->get()
        ->mapWithKeys(fn ($row) => [(int) $row->organization_id => (float) $row->total]);

    $eventsByOrg = Event::selectRaw('organization_id, count(*) as c')
        ->groupBy('organization_id')
        ->get()
        ->mapWithKeys(fn ($row) => [(int) $row->organization_id => (int) $row->c]);

    $openComplaintsByOrg = Complaint::where('status', '!=', 'selesai')
        ->selectRaw('organization_id, count(*) as c')
        ->groupBy('organization_id')
        ->get()
        ->mapWithKeys(fn ($row) => [(int) $row->organization_id => (int) $row->c]);

    $activeCardsByOrg = $this->hasMemberCards()
        ? DB::table('member_cards')->where('status', 'aktif')
            ->selectRaw('organization_id, count(*) as c')
            ->groupBy('organization_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->organization_id => (int) $row->c])
        : collect();

    return Organization::orderBy('name')
        ->get()
        ->map(fn (Organization $org) => [
            'name' => $org->name,
            'active_members' => (int) $org->member_count,
            'current_dues' => (float) ($duesByOrg[$org->id] ?? 0),
            'events' => (int) ($eventsByOrg[$org->id] ?? 0),
            'open_complaints' => (int) ($openComplaintsByOrg[$org->id] ?? 0),
            'active_cards' => (int) ($activeCardsByOrg[$org->id] ?? 0),
        ])
        ->all();
}
```

> Keys are cast via `mapWithKeys(fn (...) => [(int) $row->organization_id => ...])` — `pluck('total','organization_id')` would leave string keys that miss `$org->id` (int) lookups. A zero-record org (Gamma) must render `0` — the `?? 0` fallback covers it.

- [ ] **Step 4 — Run to verify it passes**

Run: `php artisan test --filter test_per_sba_breakdown_is_aggregate_only`
Expected: PASS.

- [ ] **Step 5 — Commit**

```bash
git add app/Filament/Widgets/FederationOperationsWidget.php tests/Feature/FederationReportingTest.php
git commit -m "feat(sp5): per-SBA aggregate breakdown for operations widget"
```

## Task 3 — Blade view + admin dashboard integration

**Files:**
- Create: `resources/views/filament/widgets/federation-operations.blade.php`
- Test: `tests/Feature/FederationReportingTest.php`

**Interfaces:**
- Consumes: `$this->getMetrics()`, `$this->getPerSbaBreakdown()` (Tasks 1–2).

- [ ] **Step 1 — Create the blade view** (`resources/views/filament/widgets/federation-operations.blade.php`), mirroring the inline-styled pattern of `member-data-overview.blade.php`. Capture the getter results once at the top so the section never calls them repeatedly:

```blade
@php
    $metrics = $this->getMetrics();
    $breakdown = $this->getPerSbaBreakdown();
@endphp

<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-clipboard-document-list">
        <x-slot name="heading">
            Ringkasan Operasional Federasi
        </x-slot>

        <dl class="mb-4 grid grid-cols-2 gap-2 text-sm lg:grid-cols-4">
            <div style="border-left: 4px solid #3a86ff; background: #eef5ff; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #3a86ff;">Jumlah SBA</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['organizations'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #8ac926; background: #f3fae6; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #5a850d;">Total anggota aktif</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['active_members'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #2ec4b6; background: #e7fcf9; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #168b7d;">Iuran bulan berjalan</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">Rp {{ number_format($metrics['current_dues'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #ffb703; background: #fff7de; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #b57e00;">Jumlah kegiatan</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['events'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #8338ec; background: #f6f0fe; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #8338ec;">Peserta hadir</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['attendances_hadir'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #ff006e; background: #ffedf5; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #d3005d;">Pengaduan terbuka</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['open_complaints'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #06aed5; background: #e7f7fc; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #05809c;">Kartu aktif</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['active_cards'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #e63946; background: #fdedef; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #b31f2b;">Kartu dicabut</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['revoked_cards'], 0, ',', '.') }}</dd>
            </div>
        </dl>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b-2 border-gray-200 text-left text-xs text-gray-500">
                    <th class="py-2 pr-3 font-semibold">Nama</th>
                    <th class="py-2 pr-3 font-semibold">Anggota Aktif</th>
                    <th class="py-2 pr-3 font-semibold">Iuran</th>
                    <th class="py-2 pr-3 font-semibold">Kegiatan</th>
                    <th class="py-2 pr-3 font-semibold">Pengaduan Terbuka</th>
                    <th class="py-2 font-semibold">Kartu Aktif</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($breakdown as $row)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 pr-3">{{ $row['name'] }}</td>
                        <td class="py-2 pr-3">{{ number_format($row['active_members'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">Rp {{ number_format($row['current_dues'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">{{ number_format($row['events'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">{{ number_format($row['open_complaints'], 0, ',', '.') }}</td>
                        <td class="py-2">{{ number_format($row['active_cards'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-2 text-gray-500">Belum ada data operasional.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
```

- [ ] **Step 2 — Write the failing HTTP integration tests** (append to `FederationReportingTest`):

```php
public function test_super_admin_dashboard_shows_operations_widget(): void
{
    $this->seedOperationalData();

    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee('Ringkasan Operasional Federasi')
        ->assertSee('125.000', false);
}

public function test_editor_dashboard_hides_operations_widget(): void
{
    $this->seedOperationalData();

    $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

    $this->actingAs($editor)->get('/admin')
        ->assertOk()
        ->assertDontSee('Ringkasan Operasional Federasi');
}

public function test_anonymous_cannot_access_admin_dashboard(): void
{
    $this->get('/admin')->assertRedirect('/admin/login');
}

public function test_admin_dashboard_never_leaks_member_pii(): void
{
    $org = Organization::factory()->create(['name' => 'SPM PII', 'member_count' => 0]);

    Member::factory()->for($org)->create([
        'name' => 'Nama Sangat Rahasia',
        'nik' => '9876543210',
        'address' => 'Jl. Rahasia No. 13',
        'birthdate' => '1990-01-01',
        'basic_salary' => 12345678,
    ]);

    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertDontSee('Nama Sangat Rahasia')
        ->assertDontSee('9876543210')
        ->assertDontSee('Jl. Rahasia No. 13')
        ->assertDontSee('1990-01-01')
        ->assertDontSee('12345678');
}
```

- [ ] **Step 3 — Run the full new suite**

Run: `php artisan test --filter FederationReportingTest`
Expected: 7 tests pass (2 from Task 1, 1 from Task 2, 4 HTTP integration). The new widget auto-discovered → renders in `/admin` for super admin (proves the checklist's "Register in AdminPanelProvider" is satisfied by discovery).

- [ ] **Step 4 — Commit**

```bash
git add resources/views/filament/widgets/federation-operations.blade.php tests/Feature/FederationReportingTest.php
git commit -m "feat(sp5): FederationOperationsWidget blade + admin dashboard no-PII integration"
```

## Task 4 — Plan annotations + final verification

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` (append, never rewrite)

- [ ] **Step 1 — Append `(** executed **)` annotations** after canonical Task 3.1's checklist:

```markdown
(** executed @2026-09-09 task-3.1-federation-operations-widget: `FederationOperationsWidget` (+ `filament.widgets.federation-operations` blade). Registration = auto-discovery via `AdminPanelProvider->discoverWidgets(... app/Filament/Widgets)` — matches MemberDataOverviewWidget/LearningReportWidget precedent; no explicit `->widgets()` entry needed (deviation note). `canView()` = `isSuperAdmin()`; `$isLazy = false`. Metrics: jumlah SBA (`organizations.count`), total anggota aktif (`SUM(organizations.member_count)` — never queries `members`), iuran bulan berjalan (`dues` where `period = now()->format('Y-m')`, same as MemberDataOverviewWidget), jumlah kegiatan (`events.count`), peserta hadir (`attendances` status `hadir` count), pengaduan terbuka (`complaints` status != `selesai`). Kartu aktif/dicabut + breakdown "Kartu Aktif" column: kept now via `Schema::hasTable('member_cards')` guard (`DB::table('member_cards')`) returning 0, per user decision — replace with `MemberCard` queries and delete `hasMemberCards()` when Task 5.2 lands. Per-SBA breakdown keyed by int org id (`mapWithKeys`) so zero-record orgs default 0; all GROUP BY queries MySQL `ONLY_FULL_GROUP_BY`-safe. **)

(** executed @2026-09-09 task-3.2-federation-reporting-tests: `tests/Feature/FederationReportingTest.php` — 7 tests: metric aggregates across orgs, per-SBA breakdown incl. zero-data org, canView gating (editor + unauthenticated) + `$isLazy=false` reflection, super admin /admin render asserts totals + heading, editor dashboard hides widget, anonymous /admin redirect, no-PII assertions (name/NIK/address/birthdate/salary not in /admin HTML). **)
```

- [ ] **Step 2 — Lint + full suite**

Run: `vendor/bin/pint` then `composer test`
Expected: pint clean; suite green **except exactly the 2 pre-existing `Sp5SecurityTest` expected failures** (anonymous export redirect, card verification). New total ≈ 2 failed / 280 passed.

- [ ] **Step 3 — Commit**

```bash
git add docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md
git commit -m "docs(sp5): Phase 3 Task 3.1/3.2 executed annotations"
```

---

## Self-review (pre-write)

- **Spec §7.1/§7.3 coverage:** all 8 metrics + 5 breakdown columns addressed; card metrics via guard per user decision.
- **No placeholders:** every step has concrete code/tests; all factory `->for()` calls pass explicit relation names (`'organization'`, `'event'`, `'member'` — AGENTS.md footgun).
- **Type consistency:** `getMetrics()` keys (`active_cards`, `revoked_cards`) and breakdown keys (`name/active_members/current_dues/events/open_complaints/active_cards`) match between widget, blade, and tests; `.mapWithKeys` int-key lookups consistent.
- **Frameworks/quirk compliance:** `auth()->user()` not `Filament::auth()`; `$isLazy=false`; final `composer test` gate.