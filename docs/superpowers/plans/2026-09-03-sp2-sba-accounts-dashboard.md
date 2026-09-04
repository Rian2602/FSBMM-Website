# FSBMM Website — SP2 Implementation Plan (SBA Accounts + Panel + Tenant Scoping + Federation Overview)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship SP2 on top of the finished SP1 codebase: SBA (Serikat Buruh Anggota) accounts created by the federation, a separate SBA Filament panel where an SBA admin views a summary and edits **their own organization's public profile**, mandatory tenant scoping (SBA admins can never see/act on another organization's data; staff roles can never enter the SBA panel and vice versa), and a federation overview of all SBA accounts.

**Architecture:** One Laravel 12 app, one DB (MySQL prod / SQLite dev+test). SP1 shipped one Filament panel at `/admin` (roles `super_admin`, `editor`). SP2 adds a **second Filament panel** (id `sba`) at **`/panel-sba`** sharing the same `users` table and `web` guard. Why not path `sba`? The public site already owns `/sba` (directory index) and `/sba/{organization:slug}` — a panel at `/sba` would collide with the public directory. Role stays a string column (`User::ROLE_SBA_ADMIN = 'sba_admin'` added), no permission package. Tenant rows = `users.organization_id` (nullable FK, `nullOnDelete`) + the organization's own row. Scoping is enforced at the SBA-panel layer (`canAccessPanel()` per panel id + tenant-scoped `getEloquentQuery()`), **never** via a global scope (a global scope would break the public directory which shows all published organizations).

**Tech Stack:** PHP 8.3+, Laravel 12, Filament 3 (installed: v3.3.55 — EditProfile with password change ships in core via `->profile()`; the Filament `Authenticate` middleware aborts **403** for an authenticated user failing `canAccessPanel()`, and redirects anonymous users to that panel's login URL), SQLite `:memory:` tests, PHPUnit feature tests, Pint.

**Spec:** `docs/superpowers/specs/2026-09-03-fsbmm-website-sp2-sba-accounts-dashboard.md` (same repo). SP1 parent spec/plan: `...sp1-design.md` / `...sp1-foundation-public-site.md` — **read them; never edit the SP1 spec/plan files**. SP1's plan records executed deviations as inline `(** executed: ... **)` annotations — preserve them; if this SP2 work must deviate from SP1 behavior, append a new annotation in the SP1 plan, never rewrite history.

## Global Constraints

- Working directory: the repo root (this git repo). SP1 is committed and green; do not rewrite SP1 migrations/models — only additive changes.
- UI copy and admin labels in **Bahasa Indonesia**. Code identifiers, migration/class names, and commit messages in English (repo convention).
- Roles: fixed string values `super_admin`, `editor`, `sba_admin`. Federation staff accounts keep `organization_id = null`. Every `sba_admin` **must** have `organization_id` set (form validation + panel gate).
- `canAccessPanel(Panel $panel)` branches on `$panel->getId()`: `admin` → super_admin/editor; `sba` → sba_admin with non-null `organization_id`; anything else → false.
- Federation-controlled fields that SBA must NOT touch: `slug` (stable public URL), `is_published` (publication decision), `member_count` (public aggregate; real numbers arrive in SP3). SBA edits: `name`, `company`, `logo_path`, `description`, `website`, `location`, `founded_year`.
- Public site behavior must not change (all SP1 tests stay green): `/sba` still lists every published organization.
- No email/notifications in SP2: initial SBA passwords are shared out-of-band by the federation; the SBA panel gets `->profile()` so account-holders can change their own password.
- No PII added; uploads remain staff/SBA-authenticated only.
- Commit after every task's green test run. Run `vendor/bin/pint` before committing (generated Filament pages can drift with unused imports).

## File Structure (locked in here)

```
database/migrations/2026_09_03_000008_add_organization_id_to_users_table.php
app/Models/User.php                       (+ ROLE_SBA_ADMIN, isSbaAdmin, organization(), panel-aware canAccessPanel)
app/Models/Organization.php               (+ users(), hasSbaAccounts())
app/Providers/Filament/SbaPanelProvider.php
bootstrap/providers.php                   (+ SbaPanelProvider)
app/Filament/Sba/Resources/OrganizationResource.php
app/Filament/Sba/Resources/OrganizationResource/Pages/{ListOrganizations,EditOrganization}.php
app/Filament/Sba/Widgets/OrganizationSummaryWidget.php
app/Filament/Widgets/SbaAccountsOverviewWidget.php
resources/views/filament/widgets/organization-summary.blade.php
resources/views/filament/widgets/sba-accounts-overview.blade.php
app/Filament/Resources/UserResource.php   (+ sba_admin role, organization Select, table column/filter)
app/Filament/Resources/OrganizationResource.php  (+ delete guards)
database/seeders/SbaAccountSeeder.php     (+ DatabaseSeeder wiring)
.env.example, README.md                   (+ demo SBA accounts)
tests/Feature/SbaTenantTest.php
tests/Feature/SbaAuthTest.php
tests/Feature/SbaOrganizationTest.php
tests/Feature/SbaAccountManagementTest.php
tests/Feature/FederationOverviewTest.php
```

---

### Task 1: `users.organization_id` + `sba_admin` role + model relations + panel-aware gate

**Files:**
- Create: `database/migrations/2026_09_03_000008_add_organization_id_to_users_table.php`, `tests/Feature/SbaTenantTest.php`
- Modify: `app/Models/User.php`, `app/Models/Organization.php`, `database/factories/UserFactory.php`

**Interfaces:**
- Produces: nullable FK `users.organization_id` (`nullOnDelete`, indexed); `User::ROLE_SBA_ADMIN`, `isSbaAdmin()`, `organization()`; `Organization::users()`, `hasSbaAccounts()`; `canAccessPanel(Panel $panel)` keyed on panel id; `UserFactory::sbaAdmin(Organization $org)` state.

- [ ] **Step 1: Write the failing test `tests/Feature/SbaTenantTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SbaTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_migration_has_nullable_organization_id(): void
    {
        $this->assertNull(User::factory()->create()->organization_id);
    }

    public function test_sba_admin_factory_state_links_an_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->sbaAdmin($org)->create();

        $this->assertSame(User::ROLE_SBA_ADMIN, $user->role);
        $this->assertTrue($user->organization->is($org));
        $this->assertTrue($org->users->contains($user));
    }

    public function test_sba_role_cannot_access_the_admin_panel(): void
    {
        // 'admin' panel exists from SP1; the full per-panel matrix lives in
        // SbaAuthTest (Task 2), once the 'sba' panel is registered.
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();

        $this->assertFalse($sba->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_organization_with_sba_account_reports_has_sba_accounts(): void
    {
        $with = Organization::factory()->create();
        User::factory()->sbaAdmin($with)->create();

        $without = Organization::factory()->create();

        $this->assertTrue($with->hasSbaAccounts());
        $this->assertFalse($without->hasSbaAccounts());
    }

    public function test_deleting_an_organization_detaches_accounts_instead_of_deleting_them(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();

        $org->delete(); // DB-level nullOnDelete (bypasses UI guards on purpose)

        $this->assertNull($sba->fresh()->organization_id);
        $this->assertDatabaseHas('users', ['id' => $sba->id]);
    }
}
```

(Whether the detached account still reaches the panel is Task 2's SbaAuthTest, once the `'sba'` panel exists.)
- [ ] **Step 2: Migration**

```bash
php artisan make:migration add_organization_id_to_users_table
```

```php
// database/migrations/2026_09_03_000008_add_organization_id_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('organization_id')
        ->nullable()
        ->after('role')
        ->constrained('organizations')
        ->nullOnDelete();
    $table->index('organization_id');
});
```

(`->after()` is ignored by SQLite — harmless for dev/tests.)

- [ ] **Step 3: `app/Models/User.php` additions**

```php
public const ROLE_SBA_ADMIN = 'sba_admin';
// fillable: add 'organization_id'

public function isSbaAdmin(): bool
{
    return $this->role === self::ROLE_SBA_ADMIN;
}

public function organization()
{
    return $this->belongsTo(Organization::class);
}

public function canAccessPanel(Panel $panel): bool
{
    return match ($panel->getId()) {
        'admin' => in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_EDITOR], true),
        'sba' => $this->role === self::ROLE_SBA_ADMIN && $this->organization_id !== null,
        default => false,
    };
}
```

- [ ] **Step 4: `app/Models/Organization.php` additions**

```php
public function users()
{
    return $this->hasMany(User::class);
}

public function hasSbaAccounts(): bool
{
    return $this->users()->where('role', User::ROLE_SBA_ADMIN)->exists();
}
```

- [ ] **Step 5: `database/factories/UserFactory.php` state**

```php
public function sbaAdmin(Organization $organization): static
{
    return $this->state(fn (array $attributes) => [
        'role' => User::ROLE_SBA_ADMIN,
        'organization_id' => $organization->id,
    ]);
}
```

- [ ] **Step 6: Green run** — `php artisan test --filter SbaTenantTest` (this file only touches the `'admin'` panel, which exists from SP1, so it is green standalone).

- [ ] **Step 7: Commit** — `feat(tenant): users.organization_id + sba_admin role with panel-aware access gate`

---

### Task 2: SBA panel shell (`SbaPanelProvider`) + auth/role tests

**Files:**
- Create: `app/Providers/Filament/SbaPanelProvider.php`, `tests/Feature/SbaAuthTest.php`
- Modify: `bootstrap/providers.php`

**Interfaces:**
- Produces: second Filament panel id `sba`, path `panel-sba`, `->login()` at `/panel-sba/login`, `->profile()` (built-in EditProfile — name/email/password change), dashboard page + discovered Sba pages/widgets. Anonymous → redirect to `/panel-sba/login`; authenticated-but-ineligible → 403 (verified Filament middleware behavior in v3.3.55).

- [ ] **Step 1: Write the failing test `tests/Feature/SbaAuthTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_sba_login_page_is_reachable(): void
    {
        $this->get('/panel-sba/login')->assertStatus(200);
    }

    public function test_anonymous_is_redirected_away_from_sba_panel(): void
    {
        $this->get('/panel-sba')->assertRedirect('/panel-sba/login');
    }

    public function test_sba_admin_with_organization_can_enter_the_panel(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create();

        $this->actingAs($sba)->get('/panel-sba')->assertSuccessful();
    }

    public function test_sba_admin_without_organization_is_blocked_from_the_panel(): void
    {
        $orphan = User::factory()->create(['role' => User::ROLE_SBA_ADMIN]); // organization_id null

        $this->actingAs($orphan)->get('/panel-sba')->assertForbidden();
    }

    public function test_super_admin_and_editor_cannot_enter_sba_panel(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba')->assertForbidden();
    }

    public function test_sba_admin_cannot_enter_admin_panel(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create();

        $this->actingAs($sba)->get('/admin')->assertForbidden();
    }

    public function test_sba_admin_can_log_in_via_the_sba_login_form(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create([
            'email' => 'pengurus@spm-demo.fsbmm.test',
            'password' => 'password', // hashed by cast
        ]);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'pengurus@spm-demo.fsbmm.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_sba_admin_can_open_own_profile_page(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create();

        $this->actingAs($sba)->get('/panel-sba/profile')->assertSuccessful();
    }

    public function test_can_access_panel_matrix_per_panel_id(): void
    {
        $org = Organization::factory()->create();
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $sba = User::factory()->sbaAdmin($org)->create();
        $orphan = User::factory()->create(['role' => User::ROLE_SBA_ADMIN]); // no org

        $adminPanel = Filament::getPanel('admin');
        $sbaPanel = Filament::getPanel('sba');

        $this->assertTrue($super->canAccessPanel($adminPanel));
        $this->assertTrue($editor->canAccessPanel($adminPanel));
        $this->assertFalse($sba->canAccessPanel($adminPanel));
        $this->assertTrue($sba->canAccessPanel($sbaPanel));
        $this->assertFalse($super->canAccessPanel($sbaPanel));
        $this->assertFalse($editor->canAccessPanel($sbaPanel));
        $this->assertFalse($orphan->canAccessPanel($sbaPanel)); // organization required
    }
}
```

Notes: the factory default keeps `organization_id` null — a plain `role => sba_admin` user has no org. Filament's `Authenticate` middleware aborts 403 for an authenticated user failing `canAccessPanel()` (verified in `vendor/filament/filament/src/Http/Middleware/Authenticate.php` v3.3.55).

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter SbaAuthTest` → FAIL (no provider/route yet).

- [ ] **Step 3: Create `app/Providers/Filament/SbaPanelProvider.php`**

Mirror `AdminPanelProvider`'s middleware lists exactly; only the identity changes:

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SbaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('sba')
            ->path('panel-sba')
            ->login()
            ->profile()
            ->colors([
                'primary' => Color::Emerald, // same placeholder brand family as /admin
            ])
            ->discoverResources(in: app_path('Filament/Sba/Resources'), for: 'App\\Filament\\Sba\\Resources')
            ->discoverPages(in: app_path('Filament/Sba/Pages'), for: 'App\\Filament\\Sba\\Pages')
            ->discoverWidgets(in: app_path('Filament/Sba/Widgets'), for: 'App\\Filament\\Sba\\Widgets')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

Register in `bootstrap/providers.php` **after** `AdminPanelProvider`. Do NOT call `->default()` (admin stays the default panel).

- [ ] **Step 4: Green run** — `php artisan test --filter SbaAuthTest`, plus Task 1's `SbaTenantTest` now that `Filament::getPanel('sba')` resolves. Then `php artisan test` to confirm the whole SP1 suite is still green.

- [ ] **Step 5: Commit** — `feat(sba): second Filament panel /panel-sba with role-gated login + profile`

---

### Task 3: SBA panel content — tenant-scoped Organization resource + dashboard summary widget

**Files:**
- Create: `app/Filament/Sba/Resources/OrganizationResource.php`, `app/Filament/Sba/Resources/OrganizationResource/Pages/{ListOrganizations,EditOrganization}.php`, `app/Filament/Sba/Widgets/OrganizationSummaryWidget.php`, `resources/views/filament/widgets/organization-summary.blade.php`, `tests/Feature/SbaOrganizationTest.php`

**Interfaces:**
- Produces: in the SBA panel, an `Organization` resource that can only ever see/edit the acting user's own organization (create/delete disabled; edit of another org's URL → 404); form subset excludes `slug`, `is_published`, `member_count`; dashboard widget summarizing the own organization.

- [ ] **Step 1: Write the failing test `tests/Feature/SbaOrganizationTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\OrganizationResource;
use App\Filament\Sba\Resources\OrganizationResource\Pages\EditOrganization;
use App\Filament\Sba\Widgets\OrganizationSummaryWidget;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_sba_directory_still_lists_all_published_organizations(): void
    {
        Organization::factory()->create(['name' => 'SPM A', 'is_published' => true]);
        Organization::factory()->create(['name' => 'SPM B', 'is_published' => true]);
        Organization::factory()->create(['name' => 'SPM Rahasia', 'is_published' => false]);

        $this->get('/sba')
            ->assertOk()
            ->assertSee('SPM A')
            ->assertSee('SPM B')
            ->assertDontSee('SPM Rahasia');
    }

    public function test_sba_list_shows_only_own_organization(): void
    {
        [$user, $org] = $this->sbaUser();
        Organization::factory()->create(['name' => 'SPM Organisasi Lain']);

        $this->actingAs($user)->get('/panel-sba/organizations')
            ->assertOk()
            ->assertSee($org->name);
    }

    public function test_editing_another_organizations_url_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create(['name' => 'SPM Milik Orang Lain']);

        $this->actingAs($user)->get('/panel-sba/organizations/'.$other->slug.'/edit')
            ->assertNotFound();
    }

    public function test_sba_cannot_create_or_delete_organizations(): void
    {
        [$user] = $this->sbaUser();

        $this->assertFalse(OrganizationResource::canCreate());
        $this->actingAs($user)->get('/panel-sba/organizations/create')->assertNotFound();
    }

    public function test_sba_edits_own_organization_profile_fields_only(): void
    {
        [$user, $org] = $this->sbaUser();
        $originalSlug = $org->slug;

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditOrganization::class, ['record' => $org->slug])
            ->fillForm([
                'description' => '<p>Deskripsi baru dari pengurus SBA.</p>',
                'website' => 'https://contoh-sba.example',
                'location' => 'Karawang, Jawa Barat',
                // Federation-controlled fields are NOT in the form — attempt to smuggle them:
                'slug' => 'slug-bajakan',
                'is_published' => true,
                'member_count' => 999999,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $org->refresh();

        $this->assertSame('<p>Deskripsi baru dari pengurus SBA.</p>', $org->description);
        $this->assertSame('https://contoh-sba.example', $org->website);
        $this->assertSame('Karawang, Jawa Barat', $org->location);
        // Fields absent from the SBA form cannot be changed through the panel:
        $this->assertSame($originalSlug, $org->slug);
        $this->assertFalse($org->is_published);
        $this->assertSame(0, $org->member_count);
    }

    public function test_own_profile_edit_is_reflected_on_the_public_page(): void
    {
        [$user, $org] = $this->sbaUser();
        $org->update(['is_published' => true, 'description' => 'Profil lama']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditOrganization::class, ['record' => $org->slug])
            ->fillForm(['description' => '<p>Profil baru yang diubah pengurus.</p>'])
            ->call('save');

        $this->get('/sba/'.$org->slug)->assertOk()->assertSee('Profil baru yang diubah pengurus');
    }

    public function test_dashboard_summary_widget_shows_own_organization_only(): void
    {
        [$user, $org] = $this->sbaUser();
        Organization::factory()->create(['name' => 'SPM Lain Yang Tidak Terlihat']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(OrganizationSummaryWidget::class)
            ->assertOk()
            ->assertSee($org->name)
            ->assertDontSee('SPM Lain Yang Tidak Terlihat');
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter SbaOrganizationTest` → FAIL (no Sba resource/widget).

- [ ] **Step 3: `app/Filament/Sba/Resources/OrganizationResource.php`**

Craft manually (do NOT reuse the admin resource class — two resource classes for one model is fine in Filament; distinct namespaces/panels). Locked points:

```php
<?php

namespace App\Filament\Sba\Resources;

use App\Filament\Sba\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Profil Organisasi';

    protected static ?string $modelLabel = 'Profil Organisasi';

    /** Tenant-scoped: the SBA admin only ever sees their own organization row. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereKey(auth()->user()?->organization_id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    // Route binding resolves through getEloquentQuery(), so another org's slug 404s.

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(200),
            Forms\Components\TextInput::make('company')->label('Perusahaan')->maxLength(200),
            Forms\Components\FileUpload::make('logo_path')
                ->label('Logo')->image()->directory('organizations')->imageEditor()->columnSpanFull(),
            Forms\Components\RichEditor::make('description')->label('Deskripsi')->columnSpanFull(),
            Forms\Components\TextInput::make('website')->url()->maxLength(255),
            Forms\Components\TextInput::make('location')->label('Lokasi')->maxLength(200),
            Forms\Components\TextInput::make('founded_year')
                ->label('Tahun berdiri')->nullable()->numeric()->minValue(1900)->maxValue(2100),
            // slug, is_published, member_count are federation-controlled — intentionally absent.
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('company')->label('Perusahaan'),
                Tables\Columns\TextColumn::make('updated_at')->label('Diperbarui')->dateTime('d M Y'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
```

Pages mirror the admin-generated ones (`protected static string $resource = OrganizationResource::class;`), with ListOrganizations' header create action removed. Generated Filament pages can be reused by moving them into the Sba namespace — remember `vendor/bin/pint` for unused imports.

- [ ] **Step 4: Dashboard summary widget** (`app/Filament/Sba/Widgets/OrganizationSummaryWidget.php`)

```php
<?php

namespace App\Filament\Sba\Widgets;

use App\Models\Organization;
use Filament\Widgets\Widget;

class OrganizationSummaryWidget extends Widget
{
    protected static string $view = 'filament.widgets.organization-summary';

    protected int | string | array $columnSpan = 'full';

    public function getOrganization(): ?Organization
    {
        return auth()->user()?->organization;
    }
}
```

`resources/views/filament/widgets/organization-summary.blade.php`: a simple card listing nama organisasi, lokasi, tahun berdiri, jumlah anggota (read-only), status publikasi (Terbit/Belum terbit — informational), and a link to the edit page via `\App\Filament\Sba\Resources\OrganizationResource::getUrl('edit', ['record' => $organization])`. Query nothing but `auth()->user()->organization` — never a cross-tenant query.

- [ ] **Step 5: Green run** — `php artisan test --filter SbaOrganizationTest`. Then smoke: `php artisan serve` + curl `/panel-sba/login` for 200.

- [ ] **Step 6: Commit** — `feat(sba-panel): tenant-scoped organization profile resource + dashboard widget`

---

### Task 4: Admin — create/manage SBA accounts in `UserResource`

**Files:**
- Modify: `app/Filament/Resources/UserResource.php`
- Create: `tests/Feature/SbaAccountManagementTest.php`

**Interfaces:**
- Produces: super admin can create/edit `sba_admin` accounts with a required linked organization; the organization Select appears only when role = `sba_admin`, is required, and is cleared when the role changes away; the users table shows the organization column and the role filter gains `sba_admin`.

- [ ] **Step 1: Write the failing test `tests/Feature/SbaAccountManagementTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_creates_an_sba_account_with_organization(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $org = Organization::factory()->create();

        Livewire::actingAs($super)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Pengurus SPM Contoh',
                'email' => 'pengurus@contoh.fsbmm.test',
                'password' => 'rahasia-awal',
                'role' => User::ROLE_SBA_ADMIN,
                'organization_id' => $org->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'pengurus@contoh.fsbmm.test',
            'role' => User::ROLE_SBA_ADMIN,
            'organization_id' => $org->id,
        ]);
    }

    public function test_sba_account_requires_an_organization(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        Livewire::actingAs($super)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Pengurus Tanpa Org',
                'email' => 'yatim@contoh.fsbmm.test',
                'password' => 'rahasia-awal',
                'role' => User::ROLE_SBA_ADMIN,
                'organization_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['organization_id' => 'required']);
    }

    public function test_staff_roles_never_receive_an_organization(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $org = Organization::factory()->create();

        Livewire::actingAs($super)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Editor Federasi',
                'email' => 'editor@fsbmm.test',
                'password' => 'rahasia-awal',
                'role' => User::ROLE_EDITOR,
                'organization_id' => $org->id, // must be ignored
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'editor@fsbmm.test', 'organization_id' => null]);
    }

    public function test_super_admin_can_reassign_and_demote_an_sba_account(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($orgA)->create();

        Livewire::actingAs($super)
            ->test(EditUser::class, ['record' => $sba->getRouteKey()])
            ->fillForm(['organization_id' => $orgB->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($orgB->id, $sba->fresh()->organization_id);

        Livewire::actingAs($super)
            ->test(EditUser::class, ['record' => $sba->getRouteKey()])
            ->fillForm(['role' => User::ROLE_EDITOR])
            ->call('save')
            ->assertHasNoFormErrors();

        $sba->refresh();
        $this->assertSame(User::ROLE_EDITOR, $sba->role);
        $this->assertNull($sba->organization_id); // cleared on demotion
    }

    public function test_users_table_shows_sba_account_with_its_organization(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $org = Organization::factory()->create(['name' => 'SPM Terlihat Di Tabel']);
        $sba = User::factory()->sbaAdmin($org)->create();

        $this->actingAs($super)->get('/admin/users')
            ->assertOk()
            ->assertSee($sba->name)
            ->assertSee('SPM Terlihat Di Tabel');
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter SbaAccountManagementTest` → FAIL.

- [ ] **Step 3: `UserResource` form changes**

Role Select: add `'sba_admin' => 'Pengurus SBA (Akun Organisasi)'` and make it `->live()`. Add after the role select:

```php
Forms\Components\Select::make('organization_id')
    ->label('Organisasi SBA')
    ->relationship('organization', 'name')
    ->searchable()
    ->preload()
    ->required(fn (Forms\Get $get): bool => $get('role') === User::ROLE_SBA_ADMIN)
    ->visible(fn (Forms\Get $get): bool => $get('role') === User::ROLE_SBA_ADMIN)
    ->dehydrated(fn (Forms\Get $get): bool => $get('role') === User::ROLE_SBA_ADMIN)
    ->helperText('Akun pengurus SBA wajib ditautkan ke satu organisasi.'),
```

`dehydrated(false)` when role ≠ sba_admin is what clears `organization_id` on demotion. Keep all existing anti-lockout guards (`canEdit`/`canDelete`/bulk) untouched — deleting/demoting an `sba_admin` is always allowed for a super admin, only super-admin self-protection rules apply.

- [ ] **Step 4: `UserResource` table changes**

Add column `Tables\Columns\TextColumn::make('organization.name')->label('Organisasi')->searchable()` (after email) and add `sba_admin => 'Pengurus SBA'` to the existing role SelectFilter. Role badge `formatStateUsing` map needs a third branch: `'Pengurus SBA'`.

- [ ] **Step 5: Green run** — `php artisan test --filter SbaAccountManagementTest`, then `php artisan test` (existing AdminAuth/AdminResourcesRender tests must stay green — they exercise the editor-role edit page where the org Select stays hidden).

- [ ] **Step 6: Commit** — `feat(admin-users): super admin creates/manages sba_admin accounts with linked organization`

---

### Task 5: Federation overview widget + organization delete guards

**Files:**
- Create: `app/Filament/Widgets/SbaAccountsOverviewWidget.php`, `resources/views/filament/widgets/sba-accounts-overview.blade.php`, `tests/Feature/FederationOverviewTest.php`
- Modify: `app/Filament/Resources/OrganizationResource.php` (admin panel)

**Interfaces:**
- Produces: an admin-dashboard widget (auto-discovered from `app/Filament/Widgets`) showing SBA-account stats + account list for super admins; admin `OrganizationResource` cannot delete (single or bulk) an organization that still has `sba_admin` accounts.

- [ ] **Step 1: Write the failing test `tests/Feature/FederationOverviewTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\OrganizationResource\Pages\ListOrganizations;
use App\Filament\Widgets\SbaAccountsOverviewWidget;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FederationOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_widget_lists_sba_accounts_with_their_organizations(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->create(['role' => User::ROLE_EDITOR]); // staff must not appear
        $orgA = Organization::factory()->create(['name' => 'SPM Alpha']);
        $orgB = Organization::factory()->create(['name' => 'SPM Beta']);
        User::factory()->sbaAdmin($orgA)->create(['name' => 'Pengurus Alpha']);
        User::factory()->sbaAdmin($orgB)->create(['name' => 'Pengurus Beta']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($super)
            ->test(SbaAccountsOverviewWidget::class)
            ->assertOk()
            ->assertSee('Pengurus Alpha')
            ->assertSee('SPM Alpha')
            ->assertSee('Pengurus Beta')
            ->assertDontSee('Editor Federasi');
    }

    public function test_overview_widget_is_super_admin_only(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor);
        $this->assertFalse(SbaAccountsOverviewWidget::canView());

        $this->actingAs($super);
        $this->assertTrue(SbaAccountsOverviewWidget::canView());
    }

    public function test_organization_with_sba_accounts_cannot_be_deleted(): void
    {
        $org = Organization::factory()->create();
        User::factory()->sbaAdmin($org)->create();

        $this->assertFalse(OrganizationResource::canDelete($org));
    }

    public function test_organization_without_sba_accounts_can_be_deleted(): void
    {
        $org = Organization::factory()->create();

        $this->assertTrue(OrganizationResource::canDelete($org));
    }

    public function test_bulk_delete_is_blocked_when_any_selected_organization_has_sba_accounts(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $withAccounts = Organization::factory()->create();
        User::factory()->sbaAdmin($withAccounts)->create();
        $empty = Organization::factory()->create();

        Livewire::actingAs($super)
            ->test(ListOrganizations::class)
            ->mountTableBulkAction('delete', [$withAccounts, $empty])
            ->callMountedTableBulkAction();

        $this->assertDatabaseHas('organizations', ['id' => $withAccounts->id]);
        $this->assertDatabaseHas('organizations', ['id' => $empty->id]); // whole batch aborted
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter FederationOverviewTest` → FAIL (widget missing, delete allowed).

- [ ] **Step 3: Overview widget** (`app/Filament/Widgets/SbaAccountsOverviewWidget.php`)

Plain widget (avoids the deprecated `StatsOverviewWidget` API in v3.3.55 — verified absent of the newer `Stats` class; plain `Widget` + blade is version-safe):

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Organization;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class SbaAccountsOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.sba-accounts-overview';

    protected int | string | array $columnSpan = 'full';

    /** Federation overview is a super-admin view; editors stay out. */
    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getTotalAccounts(): int
    {
        return User::where('role', User::ROLE_SBA_ADMIN)->count();
    }

    public function getTotalOrganizations(): int
    {
        return Organization::count();
    }

    public function getPublishedOrganizations(): int
    {
        return Organization::published()->count();
    }

    public function getAccounts(): Collection
    {
        return User::where('role', User::ROLE_SBA_ADMIN)
            ->with('organization')
            ->orderBy('name')
            ->get();
    }
}
```

Blade `resources/views/filament/widgets/sba-accounts-overview.blade.php`: heading "Akun SBA", stat line (akun SBA / organisasi terdaftar / organisasi terbit), then a table of `$this->getAccounts()` — Nama, Email, Organisasi (or "-"), Dibuat (`created_at->translatedFormat('d M Y')`). Only ever queries `role = sba_admin`. Widgets on the admin dashboard are shown to every member by default, so `canView()` gates this one to `super_admin` (editors must not see federation account data); verified `Widget::canView()` exists in v3.3.55.

- [ ] **Step 4: Delete guards on admin `OrganizationResource`**

```php
// In app/Filament/Resources/OrganizationResource.php
public static function canDelete($record): bool
{
    return $record instanceof Organization && ! $record->hasSbaAccounts();
}
```

Keep the existing `DeleteBulkAction`, but make it abort the whole batch when any org still has accounts (mirrors the UserResource anti-lockout bulk pattern):

```php
Tables\Actions\DeleteBulkAction::make()
    ->using(function (\Illuminate\Database\Eloquent\Collection $records): void {
        $blocked = $records->first(fn (Organization $org) => $org->hasSbaAccounts());

        if ($blocked) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'table' => "Organisasi '{$blocked->name}' masih memiliki akun pengurus SBA. Pindahkan atau hapus akun tersebut terlebih dahulu.",
            ]);
        }

        $records->each->delete();
    }),
```

Note: `hasSbaAccounts()` on a fresh model fires a query per org — fine at these row counts; do not over-optimize.

- [ ] **Step 5: Green run** — `php artisan test --filter FederationOverviewTest`, then the full suite.

- [ ] **Step 6: Commit** — `feat(admin): federation SBA overview widget + block deleting organizations with SBA accounts`

---

### Task 6: Demo SBA accounts + README/env docs + full green + final verification

**Files:**
- Create: `database/seeders/SbaAccountSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `.env.example`, `README.md`

**Interfaces:**
- Produces: one demo `sba_admin` account per seeded demo organization (SP1's SPM Kecap Bango / Minuman Segar / Roti Nusantara); documented credentials and SP2 scope in the README; `.env.example` documents `FSBMM_SBA_PASSWORD`.

- [ ] **Step 1: `database/seeders/SbaAccountSeeder.php`** (mirror `AdminSeeder`'s env guard)

```php
<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class SbaAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Same convention as AdminSeeder: no baked-in weak default for production.
        $password = env('FSBMM_SBA_PASSWORD');

        if (! $password) {
            if (! app()->environment(['local', 'testing'])) {
                throw new \RuntimeException(
                    'FSBMM_SBA_PASSWORD wajib di-set untuk seeding di luar local/testing.'
                );
            }

            $password = 'password'; // local/testing convenience only
        }

        $accounts = [
            ['name' => 'Pengurus SPM Kecap Bango', 'email' => 'pengurus@spm-kecap-bango.fsbmm.test', 'org' => 'spm-kecap-bango'],
            ['name' => 'Pengurus SPM Minuman Segar', 'email' => 'pengurus@spm-minuman-segar.fsbmm.test', 'org' => 'spm-minuman-segar'],
            ['name' => 'Pengurus SPM Roti Nusantara', 'email' => 'pengurus@spm-roti-nusantara.fsbmm.test', 'org' => 'spm-roti-nusantara'],
        ];

        foreach ($accounts as $account) {
            $org = Organization::where('slug', $account['org'])->firstOrFail();

            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $password,
                    'role' => User::ROLE_SBA_ADMIN,
                    'organization_id' => $org->id,
                ]
            );
        }
    }
}
```

Add `SbaAccountSeeder::class` to `DatabaseSeeder::run()` **after** `OrganizationSeeder::class` (the orgs must exist first).

- [ ] **Step 2: `.env.example`** — add under the admin block:

```
# Demo SBA (pengurus) accounts, created by the seeder — MUST be changed in production.
FSBMM_SBA_PASSWORD=
```

(Local `.env` may leave it empty; the seeder falls back to `password` in local/testing only.)

- [ ] **Step 3: README updates**

- Scope/roadmap: move SP2 from "roadmap" into the implemented scope — add a row noting SBA accounts + panel `/panel-sba` + tenant scoping (docs pointer to the SP2 spec/plan).
- Add demo account credentials (three `pengurus@...fsbmm.test` addresses, password from `FSBMM_SBA_PASSWORD`, dev fallback `password`), next to the existing admin-account note.
- Note the two panels: `/admin` (federasi) vs `/panel-sba` (pengurus SBA) — and why the SBA panel is not at `/sba` (public directory route).
- Security notes: federation-controlled fields (`slug`/`is_published`/`member_count`) and the org-delete guard.

- [ ] **Step 4: Full suite green + lint**

```bash
php artisan test
vendor/bin/pint
npm run build
```

Expected: every SP1 test (auth, articles, library, organizations, pages, SEO, resource-render smoke) plus all SP2 tests pass; Pint clean.

- [ ] **Step 5: Final verification**

```bash
php artisan migrate:fresh --seed
php artisan serve   # manual smoke:
```

- `/panel-sba/login` loads; demo `pengurus@spm-kecap-bango.fsbmm.test` / `password` can log in, sees only SPM Kecap Bango, edits its description, and the change shows on `/sba/spm-kecap-bango`.
- `admin@fsbmm.test` still works on `/admin`; the admin dashboard shows the SBA overview; the users table lists the three demo accounts with their organizations.
- `/admin`, `/sba`, `/berita`, `/e-resource`, `/e-learning` unaffected.

- [ ] **Step 6: Commit** — `feat(sba): seed demo SBA accounts + README/env docs for SP2`

---

## Final Verification

```bash
php artisan test                  # all SP1 + SP2 feature tests pass
vendor/bin/pint                   # clean
npm run build                     # production assets build clean
php artisan migrate:fresh --seed  # clean DB: 3 orgs + 3 demo SBA accounts + SP1 demo content
php artisan serve                 # manual smoke: /panel-sba/login, /panel-sba (SBA login),
                                  # /admin (federation), /sba, /berita, /e-resource, /e-learning
```

## Guard-count/scope bookkeeping

SP2 adds: one migration (`users.organization_id`), one role (`sba_admin`), one new Filament panel (`/panel-sba`) with a tenant-scoped Organization profile resource + dashboard widget, SBA-account management inside `UserResource`, a federation overview widget, organization-delete guards, and a demo-account seeder — 5 new feature test files. Public site surface is unchanged.

## Non-Goals (from spec, repeated for executor clarity)

- No SBA-authored articles, no `organization_id` on content tables (SP3/SP4 territory).
- No email invites/notifications, no approval/pending workflow, no self-registration, no public member login.
- No global tenant scope (would break the public directory); tenant scoping lives only in the SBA panel layer.
- `member_count`, `slug`, and `is_published` stay federation-controlled.
- Admin panel does not gain `->profile()` (consistent with SP1; can follow later).
