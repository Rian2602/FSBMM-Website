# FSBMM Website — SP1 Implementation Plan (Foundation + Public Site + CMS)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the FSBMM federation website's SP1: a Laravel 12 app with a data-driven public site (home/tentang/berita/SBA directory/e-resource/e-learning/kontak) and a Filament CMS where federation staff manage every collection and a full page-builder.

**Architecture:** One Laravel 12 app, one database (MySQL in production on cPanel; SQLite for local dev and tests). Public site = custom Blade + Tailwind v4 in the SPMKB Swiss-Brutalist-Green family (federation brand tokens as placeholders until official assets arrive). Federation admin = Filament panel at `/admin`; login is Filament's panel auth on `users` with a fixed `role` enum (`super_admin` | `editor`), no permission package. Multi-tenant foundation: `organizations` table exists in SP1 as the public SBA directory; tenant scoping (`users.organization_id`) is deliberately deferred to SP2.

**Tech Stack:** PHP 8.3+, Composer, Laravel 12, MySQL (prod) / SQLite (dev+test), Blade, Tailwind CSS v4 (@tailwindcss/vite), Filament 3, PHPUnit (feature tests), Vite.

**Spec:** `docs/superpowers/specs/2026-09-03-fsbmm-website-sp1-design.md` (same repo, parent of this plan's `docs/superpowers/plans/`). The plan argues from the spec; read both.

## Global Constraints

- Working directory: `/home/dienk/fsbmm-website` (git repo already initialized, spec committed at `e6a578e`). SPMKB repo is a *read-only reference* — never edit it.
- Requires PHP 8.3+ and Composer. If `php -v` fails at Task 1, STOP and ask the user to run the install block below (needs their sudo password); do not invent an alternative environment.
- UI copy and admin labels in **Bahasa Indonesia**. Code identifiers, migrations, and commit messages in English (repo convention).
- Dev DB = SQLite (`database/database.sqlite`); test DB = SQLite `:memory:` (phpunit.xml). Production `.env.example` documents MySQL.
- Roles are a fixed enum: `super_admin`, `editor`. No permission package. `super_admin` manages users + everything; `editor` manages content only.
- Every public collection query filters `is_published = true` (articles additionally require `published_at <= now()`).
- Model name note: the spec's "resources" collection is implemented as model **`Eresource`** (table `eresources`) to avoid colliding with Filament's `Resource` base class. Naming stays consistent across all tasks.
- No PII anywhere in SP1; file uploads only by authenticated staff; e-resource uploads restricted to PDF.
- Commit after every task's green test run.

## File Structure (locked in here)

```
app/Models/                     User, Organization, Category, Article, Page, PageBlock, Eresource, Course
app/Http/Controllers/           Public/PageController, Public/ArticleController, Public/OrganizationController,
                                Public/EresourceController, Public/CourseController
app/Filament/Admin/Resources/   UserResource, OrganizationResource, ArticleResource (+CategoryResource),
                                PageResource, EresourceResource, CourseResource
app/Filament/Admin/AdminPanelProvider.php   (canAccessPanel role gate)
app/Policies/                   (per-resource policies for editor restrictions)
app/View/Components/Blocks/     Hero, RichText, Image, Stats, Cta, Quote  (+ blocks/*.blade.php)
app/Support/PageBlockRenderer.php
database/migrations/            users(role), organizations, categories, articles, pages, page_blocks,
                                eresources, courses
database/seeders/               DatabaseSeeder (super-admin + demo content + page slugs home/tentang/kontak)
resources/views/layouts/public.blade.php
resources/views/public/*.blade.php          home, articles/index+show, organizations/index+show,
                                            eresources/index, courses/index
resources/views/blocks/*.blade.php
routes/web.php
```

---

### Task 1: Environment + Laravel 12 skeleton + SQLite

**Files:**
- Create: `/home/dienk/fsbmm-website/.env`, `database/database.sqlite`
- Modify: (whole skeleton lands in this dir)
- Test: `php artisan test` (Laravel skeleton's default test)

**Interfaces:**
- Consumes: nothing
- Produces: runnable Laravel app in the existing git repo, `php artisan` works, default test green

- [ ] **Step 1: Verify PHP + Composer**

Run: `php -v && composer --version`
Expected: PHP 8.3.x and Composer 2.x. If PHP is missing, STOP and hand the user this block to run in their own terminal (sudo password required), then resume:

```bash
sudo apt-get update && sudo apt-get install -y php8.3-cli php8.3-common php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-sqlite3 php8.3-gd unzip
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php --install-dir=/usr/local/bin --filename=composer && rm composer-setup.php
```

- [ ] **Step 2: Scaffold Laravel 12 into the repo directory**

The repo dir already contains `.git` + `docs/`, so scaffold in /tmp and merge (preserves docs + history):

```bash
cd /tmp && rm -rf fsbmm-scaffold && composer create-project laravel/laravel fsbmm-scaffold "12.*"
cd /home/dienk/fsbmm-website && rsync -a /tmp/fsbmm-scaffold/ ./ --exclude=.git
rm -rf /tmp/fsbmm-scaffold
```

- [ ] **Step 3: Configure SQLite dev database**

```bash
touch database/database.sqlite
```
Edit `.env`: set `DB_CONNECTION=sqlite` and remove/blank `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD`. Edit `phpunit.xml`: confirm `<env name="DB_CONNECTION" value="sqlite"/>` and `<env name="DB_DATABASE" value=":memory:"/>` are present (Laravel 12 default). Then:

```bash
php artisan key:generate
php artisan migrate
```

- [ ] **Step 4: Verify skeleton green**

Run: `php artisan test`
Expected: 1 test, PASS (Laravel welcome feature test).

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "chore: scaffold Laravel 12 skeleton with SQLite dev database"
```

---

### Task 2: Tailwind v4 + Vite + public layout shell with brand tokens

**Files:**
- Modify: `vite.config.js`, `resources/css/app.css` (rewrite), `resources/views/welcome.blade.php` (replaced in Task 8)
- Create: `resources/views/layouts/public.blade.php`
- Test: build output + `npm run build` exit 0

**Interfaces:**
- Produces: CSS import chain `resources/css/app.css` consumed by Vite; layout `layouts/public.blade.php` yielding `slot`/`$title` for later public views.

- [ ] **Step 1: Install Tailwind v4 + Vite plugin**

```bash
npm install && npm install -D tailwindcss @tailwindcss/vite
```
Rewrite `vite.config.js`:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css'], refresh: true }),
        tailwindcss(),
    ],
});
```

- [ ] **Step 2: Write `resources/css/app.css` (tokens + utilities)**

```css
@import "tailwindcss";

/* Brand tokens — PLACEHOLDER palette from SPMKB family (Swiss Brutalist Green).
   Replace --brand-* values when official federation logo/colors arrive. */
@theme {
  --color-brand-950: #073b32;
  --color-brand-800: #0b5e4c;
  --color-brand-600: #12806a;
  --color-brand-100: #dcefe8;
  --color-accent: #c9d43a;
  --font-display: "Arial Narrow", "Helvetica Neue", Arial, sans-serif;
}
@layer base {
  body { @apply bg-stone-50 text-stone-900 antialiased; }
}
```

- [ ] **Step 3: Create `resources/views/layouts/public.blade.php`**

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @yield('meta')
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen flex flex-col">
    <header class="bg-brand-950 text-white">
        <div class="mx-auto max-w-6xl px-4 py-5 flex items-center justify-between">
            <a href="/" class="font-display text-2xl font-bold tracking-tight">FSBMM<span class="text-accent">.</span></a>
            <nav class="flex gap-6 text-sm font-semibold">
                <a href="/berita" class="hover:text-accent">Berita</a>
                <a href="/sba" class="hover:text-accent">SBA</a>
                <a href="/e-resource" class="hover:text-accent">E-Resource</a>
                <a href="/e-learning" class="hover:text-accent">E-Learning</a>
            </nav>
        </div>
    </header>
    <main class="flex-1">@yield('content')</main>
    <footer class="bg-brand-950 text-white/80 text-sm">
        <div class="mx-auto max-w-6xl px-4 py-6">© {{ date('Y') }} FSBMM — Federasi Serikat Buruh Makanan dan Minuman</div>
    </footer>
</body>
</html>
```

- [ ] **Step 4: Prove the asset pipeline compiles**

```bash
npm run build
ls public/build/manifest.json
```

Acceptance: `npm run build` exits 0 and `public/build/manifest.json` references `resources/css/app.css`. The layout itself is exercised end-to-end from Task 4 onward (views extend it); `/` renders the skeleton `welcome.blade.php` until Task 6 replaces it with the DB home page.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(ui): Tailwind v4 pipeline + public layout shell with brand tokens"
```

---

### Task 3: Role column + super-admin seeder + Filament panel login

**Files:**
- Create: `app/Filament/Admin/AdminPanelProvider.php`, `database/seeders/AdminSeeder.php`
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php` (add `role`), `app/Models/User.php`, `database/seeders/DatabaseSeeder.php`, `.env`
- Test: `tests/Feature/AdminAuthTest.php`

**Interfaces:**
- Consumes: Task 1 skeleton
- Produces: `User::ROLE_SUPER_ADMIN = 'super_admin'`, `User::ROLE_EDITOR = 'editor'` constants; `users.role` string column default `editor`; seeded super-admin `admin@fsbmm.test` / `password` (dev only); Filament `/admin` login functional; `AdminPanelProvider::canAccessPanel()` gate.

- [ ] **Step 1: Write the failing test `tests/Feature/AdminAuthTest.php`**

```php
<?php

use App\Models\User;

test('admin login page is reachable', function () {
    $this->get('/admin/login')->assertStatus(200);
});

test('anonymous is redirected away from admin', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('super_admin can enter the panel', function () {
    $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($user)->get('/admin')->assertSuccessful();
});

test('role column defaults to editor', function () {
    expect(User::factory()->create()->role)->toBe(User::ROLE_EDITOR);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test tests/Feature/AdminAuthTest.php`
Expected: FAIL (no role column / no panel / factory lacks role).

- [ ] **Step 3: Install Filament and add the role column**

```bash
composer require filament/filament:"^3.2"
php artisan filament:install --panels --no-interaction
```
Edit the users migration: after `'password' => ...` add `$table->string('role')->default('editor');`. Edit `app/Models/User.php`: add `class User extends Authenticatable` body additions — `protected $fillable = ['name', 'email', 'password', 'role'];`, `protected $hidden = ['password', 'remember_token'];`, `protected function casts(): array { return ['email_verified_at' => 'datetime', 'password' => 'hashed']; }` (keep Laravel 12 defaults), and:
```php
public const ROLE_SUPER_ADMIN = 'super_admin';
public const ROLE_EDITOR = 'editor';

public function isSuperAdmin(): bool { return $this->role === self::ROLE_SUPER_ADMIN; }
```
Update `UserFactory` (`database/factories/UserFactory.php`) definition to include `'role' => User::ROLE_EDITOR`.

- [ ] **Step 4: Panel access gate**

Edit `app/Providers/Filament/AdminPanelProvider.php` — inside `->id('admin')` chain add the path and, after the panel is configured, add `->authGuard('web')` (default) and enforce roles by editing the `panel->auth()->...` via `->canAccessPanel` on the User model:
```php
// app/Models/User.php
public function canAccessPanel(\Filament\Panel $panel): bool
{
    return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_EDITOR], true);
}
```
Then create the user-management resource restricted to super-admins (spec §5: only `super_admin` manages staff users):

```bash
php artisan make:filament-resource User --generate --no-interaction
```
In `app/Filament/Admin/Resources/UserResource.php` add:
```php
public static function canViewAny(): bool { return auth()->user()?->isSuperAdmin() ?? false; }
public static function canCreate(): bool { return auth()->user()?->isSuperAdmin() ?? false; }
public static function canDeleteAny(): bool { return auth()->user()?->isSuperAdmin() ?? false; }
```
Hide the `password` field from tables; form uses TextInputs name/email + Select role (options super_admin/editor) + password only on create.

- [ ] **Step 5: Seeder for the super-admin**

Create `database/seeders/AdminSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('FSBMM_ADMIN_EMAIL', 'admin@fsbmm.test')],
            ['name' => 'Admin FSBMM', 'password' => env('FSBMM_ADMIN_PASSWORD', 'password'), 'role' => User::ROLE_SUPER_ADMIN]
        );
    }
}
```
In `DatabaseSeeder::run()` call `$this->call([AdminSeeder::class]);`. Add to `.env` (and `.env.example`):
```
FSBMM_ADMIN_EMAIL=admin@fsbmm.test
FSBMM_ADMIN_PASSWORD=password
```

- [ ] **Step 6: Migrate + run tests to green**

```bash
php artisan migrate:fresh --seed
php artisan test tests/Feature/AdminAuthTest.php
```
Expected: 4 PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(auth): role column + Filament admin panel with super_admin/editor gate"
```

---

### Task 4: Organizations (SBA directory) end-to-end

**Files:**
- Create: `app/Models/Organization.php`, `database/migrations/2026_09_03_000001_create_organizations_table.php`, `app/Http/Controllers/Public/OrganizationController.php`, `resources/views/public/organizations/index.blade.php`, `.../show.blade.php`, `app/Filament/Admin/Resources/OrganizationResource.php`, `tests/Feature/OrganizationTest.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (register resource — auto via discovery in Filament 3), `routes/web.php`

**Interfaces:**
- Consumes: Task 3 auth/panel
- Produces: `Organization` model fillable `['name','slug','company','logo_path','description','website','location','founded_year','member_count','is_published']`; `Organization::scopePublished()`; routes `GET /sba`, `GET /sba/{organization:slug}`; Filament resource with `is_published` toggle.

- [ ] **Step 1: Write the failing test `tests/Feature/OrganizationTest.php`**

```php
<?php

use App\Models\Organization;
use App\Models\User;

test('public directory lists only published organizations', function () {
    Organization::factory()->create(['name' => 'SPM Kecap Bango', 'is_published' => true]);
    Organization::factory()->create(['name' => 'SPM Minuman Segar', 'is_published' => false]);
    $this->get('/sba')
        ->assertOk()
        ->assertSee('SPM Kecap Bango')
        ->assertDontSee('SPM Minuman Segar');
});

test('public profile page renders a published SBA', function () {
    $org = Organization::factory()->create(['is_published' => true]);
    $this->get('/sba/'.$org->slug)->assertOk()->assertSee($org->name);
});

test('unpublished SBA profile returns 404', function () {
    $org = Organization::factory()->create(['is_published' => false]);
    $this->get('/sba/'.$org->slug)->assertNotFound();
});
```

- [ ] **Step 2: Run to verify failure** — `php artisan test tests/Feature/OrganizationTest.php` → FAIL (no model/table/route).

- [ ] **Step 3: Migration + Model**

Migration columns (spec §4): `name`, `slug` (unique), `company`, `logo_path` (nullable string), `description` (text, nullable), `website` (nullable), `location` (nullable), `founded_year` (nullable unsignedSmallInteger), `member_count` (unsignedInteger default 0), `is_published` (bool default false), timestamps.
```php
// app/Models/Organization.php
class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'company', 'logo_path', 'description', 'website', 'location', 'founded_year', 'member_count', 'is_published'];
    protected function casts(): array { return ['is_published' => 'bool', 'founded_year' => 'integer', 'member_count' => 'integer']; }

    public function scopePublished($q) { return $q->where('is_published', true); }
    public function getRouteKeyName(): string { return 'slug'; }
}
```
Factory `database/factories/OrganizationFactory.php`: faker name/company/slug, `is_published => true` default; add a `unpublished()` state.

- [ ] **Step 4: Public controller + routes + views**

`routes/web.php` (collection routes must precede any page catch-all):
```php
Route::get('/sba', [OrganizationController::class, 'index'])->name('organizations.index');
Route::get('/sba/{organization:slug}', [OrganizationController::class, 'show'])->name('organizations.show');
```
`app/Http/Controllers/Public/OrganizationController.php`:
```php
public function index()
{
    return view('public.organizations.index', ['organizations' => Organization::published()->orderBy('name')->get()]);
}
public function show(Organization $organization)
{
    abort_unless($organization->is_published, 404);
    return view('public.organizations.show', ['organization' => $organization]);
}
```
Views extend `layouts.public` with `@slot`-style `<x-layouts.public :title="$organization->name">`… simplest: `@extends('layouts.public')` + `@section('title')` — make the layout use `@yield('title')` fallback via `{{ $title ?? '' }}`… to keep layout simple, switch the layout to component-style later is unnecessary; instead layout line becomes `@yield('title', config('app.name'))`. Update Task 2 layout accordingly (change the `<title>` line to `@yield('title', config('app.name'))`). Index view: list cards with `name`, `company`, `location`, `member_count` formatted as "N anggota", linked to show. Show view: full profile fields.

- [ ] **Step 5: Filament resource for federation staff**

```bash
php artisan make:filament-resource Organization --generate --no-interaction
```
Customize `app/Filament/Admin/Resources/OrganizationResource.php` form: `TextInput name`, `TextInput slug` (unique, helperText auto), `TextInput company`, `FileUpload logo_path` (image, directory `organizations`), `RichEditor description`, `TextInput website` (url), `TextInput location`, `TextInput founded_year` (numeric), `TextInput member_count` (numeric), `Toggle is_published`. Table: `name`, `company`, `member_count`, `is_published` (IconColumn/Badge), actions edit/delete. Keep auto-generated pages.

- [ ] **Step 6: Run tests green + seed a demo row**

```bash
php artisan migrate:fresh --seed
php artisan test tests/Feature/OrganizationTest.php
```
Add 3 demo organizations to the seeder (SPM Kecap Bango, SPM Minuman Segar, SPM Roti Nusantara — published) so the directory renders real rows. Expected: 3 PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(sba): Organization model + public directory + Filament resource"
```

---

### Task 5: Categories + Articles (Berita) end-to-end

**Files:**
- Create: `app/Models/Category.php`, `app/Models/Article.php`, `database/migrations/..._create_categories_table.php`, `..._create_articles_table.php`, `app/Http/Controllers/Public/ArticleController.php`, `resources/views/public/articles/index.blade.php`, `.../show.blade.php`, `app/Filament/Admin/Resources/CategoryResource.php`, `app/Filament/Admin/Resources/ArticleResource.php`, `tests/Feature/ArticleTest.php`
- Modify: `routes/web.php`, `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: Organization task conventions (factory + published-scope + resource pattern)
- Produces: `Category` (name, slug), `Article` fillable `['title','slug','excerpt','body','cover_image_path','category_id','author_id','published_at','is_featured']`, `Article::scopePublished()` = `published_at <= now()` (**executed: `published_at` is the single publish control — the spec's articles schema has NO `is_published` column; the draft/future/scheduled tests + scope formula in the original Step 1–3 snippets leaked the `is_published` convention from other collections. Tests were rewritten to `draft()` state = null `published_at`; ArticleResource omits any publish toggle — see DateTimePicker below.**), routes `GET /berita`, `GET /berita/{article:slug}`.

- [ ] **Step 1: Write the failing test `tests/Feature/ArticleTest.php`**

```php
<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\User;

test('article index lists published articles newest first', function () {
    Category::factory()->create(['name' => 'Kabar Federasi']);
    $old = Article::factory()->create(['title' => 'Berita Lama', 'published_at' => now()->subDays(2)]);
    $new = Article::factory()->create(['title' => 'Berita Baru', 'published_at' => now()]);
    Article::factory()->create(['title' => 'Draft Rahasia', 'published_at' => null, 'is_published' => false]);

    $this->get('/berita')
        ->assertOk()
        ->assertSeeInOrder(['Berita Baru', 'Berita Lama'])
        ->assertDontSee('Draft Rahasia');
});

test('future-dated published article is treated as draft', function () {
    Article::factory()->create(['title' => 'Belum Tayang', 'is_published' => true, 'published_at' => now()->addDay()]);
    $this->get('/berita')->assertDontSee('Belum Tayang');
    $this->get('/berita/belum-tayang')->assertNotFound();
});

test('article show renders body', function () {
    $a = Article::factory()->create();
    $this->get('/berita/'.$a->slug)->assertOk()->assertSee($a->title);
});
```

- [ ] **Step 2: Run to verify failure** — `php artisan test tests/Feature/ArticleTest.php` → FAIL.

- [ ] **Step 3: Migrations + models + factories**

`categories`: `name`, `slug` (unique). `articles`: `title`, `slug` (unique), `excerpt` (text), `body` (longText), `cover_image_path` (nullable), `category_id` (nullable FK categories nullOnDelete), `author_id` (FK users), `published_at` (nullable timestamp), `is_featured` (bool default false), timestamps. Models:
```php
class Article extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'cover_image_path', 'category_id', 'author_id', 'published_at', 'is_featured'];
    protected function casts(): array { return ['published_at' => 'datetime', 'is_featured' => 'bool']; }
    public function getRouteKeyName(): string { return 'slug'; }
    public function category() { return $this->belongsTo(Category::class); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
    public function scopePublished($q) { return $q->where('is_published', true)->where('published_at', '<=', now()); }
}
```
Factories: `ArticleFactory` with real-looking Indonesian faker sentences, `published_at => now()`, `is_published => true`, `author_id => User::factory()`; add `draft()` state (`published_at => null, is_published => false`).

- [ ] **Step 4: Controller, routes, views**

```php
Route::get('/berita', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/berita/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');
```
Controller: `index()` → published, latest first, optional `?category=` slug filter (pass `$categories` for the filter bar); `show(Article $article)` → `abort_unless($article->is_published && $article->published_at?->isPast(), 404);` render show with formatted date (`$article->published_at->translatedFormat('d F Y')` — renders Indonesian because foundation config sets `APP_LOCALE=id`; do NOT add per-call locale overrides), category badge, author name, body (safe: store as sanitized HTML from Filament RichEditor — render with `{!! $article->body !!}` only because Filament persists sanitized output; note in code comment). Views extend public layout.

- [ ] **Step 5: Filament resources**

`php artisan make:filament-resource Category --generate` and `... Article --generate`. ArticleResource form: TextInput title (required, live → slug autofill via `->afterStateUpdated` using Str::slug), TextInput slug (unique), Textarea/TextInput excerpt, RichEditor body (required), FileUpload cover_image_path (image, directory `articles`), Select category_id (relationship, searchable, required), Select author_id (relationship, default current user), DateTimePicker published_at (helper text: kosong = draft), Toggle is_featured. Table: title, category name, published_at (sortable), status BadgeColumn computed from `published_at` (Draf/Terjadwal/Tayang) + featured icon; filters incl. status. (**executed: author Select defaults to the current user via `->default(auth()->id())` instead of the `mutateFormDataBeforeCreate` override — the override would have made the author Select inert.**)

- [ ] **Step 6: Seed demo articles (5 items, 2 categories) + green run**

```bash
php artisan migrate:fresh --seed
php artisan test tests/Feature/ArticleTest.php
```
Expected: 3 PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(berita): Category + Article models, public listing/detail, Filament resource"
```

---

### Task 6: Page-builder — Pages + PageBlocks (full CMS pages)

**Files:**
- Create: `app/Models/Page.php`, `app/Models/PageBlock.php`, migrations for `pages` + `page_blocks`, `app/Support/PageBlockRenderer.php`, `app/View/Components/Blocks/{Hero,RichText,Image,Stats,Cta,Quote}.php`, `resources/views/blocks/{hero,rich_text,image,stats,cta,quote}.blade.php`, `resources/views/public/pages/show.blade.php`, `app/Filament/Admin/Resources/PageResource.php`, `tests/Feature/PageTest.php`
- Modify: `app/Http/Controllers/Public/PageController.php` (create), `routes/web.php`, seeder (home/tentang/kontak + blocks)

**Interfaces:**
- Consumes: Tasks 3–5 patterns
- Produces: `Page` (title, slug, meta_title, meta_description, is_published) with `blocks()` ordered hasMany; `PageBlock` (type enum, payload JSON, sort_order); `PageBlockRenderer::render(PageBlock $block): string` used by `pages/show.blade.php`; controller `PageController::show(string $slug)`; routes `/` (home), `/tentang`, `/kontak`.

- [ ] **Step 1: Write the failing test `tests/Feature/PageTest.php`**

```php
<?php

use App\Models\Page;

test('home page renders seeded hero and stats blocks', function () {
    $this->seed();
    $this->get('/')
        ->assertOk()
        ->assertSee('Federasi Serikat Buruh Makanan dan Minuman', false)
        ->assertSee('SBA Terdaftar', false);
});

test('published page renders each block type without error', function () {
    $page = Page::factory()->create(['slug' => 'uji', 'is_published' => true]);
    $page->blocks()->createMany([
        ['type' => 'hero',     'payload' => ['title' => 'Judul Hero', 'subtitle' => 'Sub'], 'sort_order' => 1],
        ['type' => 'rich_text','payload' => ['content' => '<p>Paragraf uji</p>'], 'sort_order' => 2],
        ['type' => 'stats',    'payload' => ['items' => [['label' => 'SBA', 'value' => '25']]], 'sort_order' => 3],
    ]);
    $this->get('/uji')->assertOk()->assertSee('Judul Hero')->assertSee('Paragraf uji')->assertSee('25');
});

test('unpublished page returns 404', function () {
    $page = Page::factory()->create(['is_published' => false]);
    $this->get('/'.$page->slug)->assertNotFound();
});
```

- [ ] **Step 2: Run to verify failure** — `php artisan test tests/Feature/PageTest.php` → FAIL.

- [ ] **Step 3: Migrations + models**

`pages`: `title`, `slug` (unique), `meta_title` (nullable), `meta_description` (nullable text), `is_published` (bool default false), timestamps. `page_blocks`: `page_id` FK cascade, `type` string, `payload` json, `sort_order` unsignedInteger default 0, timestamps. Models:
```php
class Page extends Model
{
    protected $fillable = ['title', 'slug', 'meta_title', 'meta_description', 'is_published'];
    protected function casts(): array { return ['is_published' => 'bool']; }
    public function getRouteKeyName(): string { return 'slug'; }
    public function blocks() { return $this->hasMany(PageBlock::class)->orderBy('sort_order'); }
    public function scopePublished($q) { return $q->where('is_published', true); }
}
class PageBlock extends Model
{
    public const TYPES = ['hero', 'rich_text', 'image', 'stats', 'cta', 'quote'];
    protected $fillable = ['page_id', 'type', 'payload', 'sort_order'];
    protected function casts(): array { return ['payload' => 'array', 'sort_order' => 'integer']; }
}
```
`PageFactory` default `is_published => true`, slug faker unique word. Note: Page model name shadows nothing in Laravel; fine.

- [ ] **Step 4: Renderer + per-type components**

`app/Support/PageBlockRenderer.php`:
```php
<?php

namespace App\Support;

use App\Models\PageBlock;
use Illuminate\Support\Facades\View;

class PageBlockRenderer
{
    public function render(PageBlock $block): string
    {
        $view = 'blocks.'.$block->type;
        abort_unless(View::exists($view), 500, "Block type belum terdaftar: {$block->type}");
        return view($view, ['payload' => $block->payload])->render();
    }
}
```
One component + blade per type. Representative example — `resources/views/blocks/hero.blade.php`:
```blade
<section class="bg-brand-950 text-white py-20">
    <div class="mx-auto max-w-6xl px-4">
        @if(!empty($payload['eyebrow']))<p class="text-accent uppercase tracking-widest text-sm font-bold">{{ $payload['eyebrow'] }}</p>@endif
        <h1 class="font-display text-5xl font-bold mt-2 max-w-3xl">{{ $payload['title'] }}</h1>
        @if(!empty($payload['subtitle']))<p class="mt-4 text-white/80 text-lg max-w-2xl">{{ $payload['subtitle'] }}</p>@endif
        @if(!empty($payload['cta_label']))
            <a href="{{ $payload['cta_url'] ?? '#' }}" class="mt-8 inline-block bg-accent text-brand-950 font-bold px-6 py-3">{{ $payload['cta_label'] }}</a>
        @endif
    </div>
</section>
```
`rich_text.blade.php`: `<section class="prose max-w-none mx-auto max-w-6xl px-4 py-12">{!! $payload['content'] ?? '' !!}</section>` (RichEditor-sanitized HTML; comment the caveat). `stats.blade.php`: grid of `$payload['items']` (`label`/`value`). `image.blade.php`: `<img src="{{ asset('storage/'.$payload['image_path']) }}" alt="{{ $payload['caption'] ?? '' }}">`. `cta` and `quote`: small sections per payload keys defined in the spec §6 renderer note. The 6 component classes are thin `Component` wrappers (optional — blade alone suffices; create only if a class adds logic).

- [ ] **Step 5: Public show view + controller + routes**

`resources/views/public/pages/show.blade.php`:
```blade
@extends('layouts.public')
@section('title', $page->meta_title ?: $page->title)
@section('meta')
    @if($page->meta_description)<meta name="description" content="{{ $page->meta_description }}">@endif
@endsection
@section('content')
    @foreach ($page->blocks as $block)
        {!! app(\App\Support\PageBlockRenderer::class)->render($block) !!}
    @endforeach
@endsection
```
(The public layout already uses `@yield('title')`/`@yield('content')` per Task 2.)

`app/Http/Controllers/Public/PageController.php`:
```php
public function show(string $slug)
{
    $page = Page::published()->where('slug', $slug)->firstOrFail();
    return view('public.pages.show', ['page' => $page]);
}
```
Routes (order matters — after all collection routes):
```php
Route::get('/', fn () => app(PageController::class)->show('home'))->name('home');
Route::get('/tentang', fn () => app(PageController::class)->show('tentang'))->name('tentang');
Route::get('/kontak', fn () => app(PageController::class)->show('kontak'))->name('kontak');
Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');
```
(**executed: the plan's optional `->whereIn()` slug whitelist was OMITTED — a dynamic whitelist freezes at `route:cache` time and would 404 freshly published pages in production; the controller already 404s unknown/unpublished slugs and more specific routes are declared above. `welcome.blade.php` was deleted in this task (its `/` route moved to the DB home page) and ExampleTest now seeds first.**)

- [ ] **Step 6: Seeder pages + blocks (home hero/stats, tentang rich_text, kontak rich_text + cta)**

In `DatabaseSeeder`, create pages `home`/`tentang`/`kontak` with blocks; home: hero (eyebrow "Serikat Buruh", title "Federasi Serikat Buruh Makanan dan Minuman", subtitle placeholder, cta to /tentang) then stats (items: SBA Terdaftar 25, Anggota 10.000+, Wilayah — placeholder numbers marked in a comment) then rich_text intro. Slug collisions with demo articles/orgs are impossible (separate tables); the `/uji` factory page in tests uses non-seeded slug.

- [ ] **Step 7: Filament PageResource**

`php artisan make:filament-resource Page --generate`. Form: TextInput title (required), TextInput slug (unique), TextInput meta_title, Textarea meta_description, Toggle is_published, and a **Repeater named `blocks`** with relationship, `orderColumn('sort_order')`, `schema()` Builder — one Builder per block type is cleaner: use `Repeater::make('blocks')->relationship()->orderColumn('sort_order')->schema([Builder::make('payload')->blocks([...])])` — implement with a single `Builder` whose block options are the six types (hero: TextInputs eyebrow/title/subtitle + FileUpload image_path + TextInput cta_label/cta_url; rich_text: RichEditor content; image: FileUpload image_path + TextInput caption; stats: Repeater items [label,value]; cta: title/body/label/url; quote: quote/author). Table: title, slug, is_published. Add a `getHeaderActions`/view link button optional — skip (YAGNI). Keep this resource's edit page the single place blocks are edited.

- [ ] **Step 8: Green run**

```bash
php artisan migrate:fresh --seed
php artisan test tests/Feature/PageTest.php
```
Expected: 3 PASS. Then visually smoke: `php artisan serve` + curl `/`, `/tentang`, `/kontak` for 200.

- [ ] **Step 9: Commit**

```bash
git add -A && git commit -m "feat(cms): full page-builder (pages + typed blocks) with Filament editor"
```

---

### Task 7: Eresource (e-resource library) + Course catalog

**Files:**
- Create: `app/Models/Eresource.php`, `app/Models/Course.php`, migrations `eresources` + `courses`, `app/Http/Controllers/Public/EresourceController.php`, `.../CourseController.php`, views `public/eresources/index.blade.php`, `public/courses/index.blade.php`, `app/Filament/Admin/Resources/EresourceResource.php`, `CourseResource.php`, `tests/Feature/LibraryTest.php`
- Modify: `routes/web.php`, seeder

**Interfaces:**
- Consumes: Tasks 3–5 conventions (factory, published scope, resource pattern)
- Produces: `Eresource` fillable `['title','slug','description','file_path','is_published','downloads_count']`; `Course` fillable `['title','slug','description','level','is_published']` (level enum `dasar|menengah|lanjut`); routes `GET /e-resource`, `GET /e-learning`; download endpoint increments `downloads_count` (POST or GET with signed URL — use a signed route to avoid CSRF/abuse: `Route::get('/e-resource/{eresource:slug}/download', ...)->name('eresources.download')->middleware('signed')`).

- [ ] **Step 1: Write the failing test `tests/Feature/LibraryTest.php`**

```php
<?php

use App\Models\Course;
use App\Models\Eresource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

test('e-resource index lists only published items with file links', function () {
    Storage::fake('public');
    $res = Eresource::factory()->create(['title' => 'AD/ART Federasi', 'is_published' => true]);
    Eresource::factory()->create(['title' => 'Rahasia Internal', 'is_published' => false]);
    $this->get('/e-resource')
        ->assertOk()
        ->assertSee('AD/ART Federasi')
        ->assertDontSee('Rahasia Internal');
    $this->assertNotNull($res->file_path);
});

test('signed download increments counter and serves the file', function () {
    Storage::fake('public');
    $res = Eresource::factory()->create();
    Storage::disk('public')->put($res->file_path, '%PDF-1.4 fake');
    $url = URL::signedRoute('eresources.download', ['eresource' => $res->slug]);
    $this->get($url)->assertOk();
    expect($res->fresh()->downloads_count)->toBe(1);
});

test('course catalog lists only published courses', function () {
    Course::factory()->create(['title' => 'Keselamatan Kerja', 'is_published' => true]);
    Course::factory()->create(['title' => 'Draft Kursus', 'is_published' => false]);
    $this->get('/e-learning')->assertOk()->assertSee('Keselamatan Kerja')->assertDontSee('Draft Kursus');
});
```

- [ ] **Step 2: Run to verify failure** — `php artisan test tests/Feature/LibraryTest.php` → FAIL.

- [ ] **Step 3: Migrations + models + factories**

`eresources`: `title`, `slug` (unique), `description` (text nullable), `file_path` (string), `is_published` (bool default false), `downloads_count` (unsignedInteger default 0), timestamps. `courses`: `title`, `slug` (unique), `description` (text), `level` (string default 'dasar'), `is_published` (bool default false), timestamps. Models with `getRouteKeyName(): slug`, published scopes, casts. `EresourceFactory` writes a placeholder PDF to `Storage::fake` is unnecessary — store a dummy relative path string like `'eresources/'.$this->faker->slug().'.pdf'` (tests that need a real file `Storage::disk('public')->put(...)` as shown). `CourseFactory` level cycles the three values. (**executed: `eresources` sengaja TANPA `category_id` di SP1, mengikuti kata spec §4:73 "nanti resource" — kategorisasi e-resource didefer; berbeda dari `articles` (Task 5) yang memakai `category_id` sejak awal. Tambahkan FK nullable + Select di EresourceResource + badge/filter frontend di fase lanjut bila dibutuhkan.**)

- [ ] **Step 4: Controllers + routes + views**

```php
Route::get('/e-resource', [EresourceController::class, 'index'])->name('eresources.index');
Route::get('/e-resource/{eresource:slug}/download', [EresourceController::class, 'download'])->name('eresources.download')->middleware('signed');
Route::get('/e-learning', [CourseController::class, 'index'])->name('courses.index');
```
EresourceController: `index()` → published ordered by title; `download(Eresource $eresource)` → `abort_unless($eresource->is_published, 404);` increment counter (`$eresource->increment('downloads_count');`) then `return Storage::disk('public')->download($eresource->file_path);`. Views list cards with "Unduh PDF" buttons using `URL::signedRoute`. CourseController `index()` → published ordered by level then title; badges for `dasar/menengah/lanjut` (Indonesian labels Dasar/Menengah/Lanjut).

- [ ] **Step 5: Filament resources**

`php artisan make:filament-resource Eresource --generate` + `Course --generate`. EresourceResource: TextInputs title/slug, RichEditor/Textarea description, FileUpload file_path (acceptedFileTypes `['application/pdf']`, directory `eresources`; **executed: `storeFileNames(false)` from the original snippet does NOT exist in Filament 3.3 — it crashed every eresource create/edit page with a 500 until removed and guarded by the AdminResourcesRenderTest smoke test**), Toggle is_published, downloads_count displayed read-only (`disabled` TextInput). CourseResource: title/slug/description, Select level (`dasar/menengah/lanjut`), Toggle is_published.

- [ ] **Step 6: Seed + green**

Seed 4 resources (AD/ART, leaflet K3, template surat kuasa, modul dasar) and 3 courses. `php artisan migrate:fresh --seed && php artisan test tests/Feature/LibraryTest.php` → 3 PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(library): e-resource downloads (signed, counted) + course catalog"
```

---

### Task 8: Home composition + SEO + seeder polish + README

**Files:**
- Modify: `resources/views/public/pages/show.blade.php` (meta tags), `routes/web.php` (sitemap), seeder (realistic demo set), `.env.example`, create `README.md`, delete skeleton `welcome.blade.php`
- Create: `tests/Feature/SeoTest.php`

**Interfaces:**
- Consumes: all prior tasks
- Produces: complete homepage experience (hero + stats + latest 3 articles + featured resource strip composed in the `home` page's blocks or a dedicated `home.blade.php`), per-page `<title>`/meta, `/sitemap.xml`, demo data, repo README.

- [ ] **Step 1: Write the failing test `tests/Feature/SeoTest.php`**

```php
<?php

test('public pages emit meta title and description', function () {
    $this->seed();
    $this->get('/')->assertSee('<title>', false);
    $this->get('/berita')->assertSee('<title>', false);
});

test('sitemap lists main collections', function () {
    $this->seed();
    $this->get('/sitemap.xml')->assertOk()->assertSee('/berita')->assertSee('/sba');
});
```

- [ ] **Step 2: Meta + sitemap**

Layout `<head>` gains `@yield('meta')`; in `pages/show.blade.php` add `@section('meta')` with `meta_description`. Create `routes/web.php` sitemap:
```php
Route::get('/sitemap.xml', function () {
    $urls = collect(['/', '/tentang', '/berita', '/sba', '/e-resource', '/e-learning', '/kontak'])
        ->map(fn ($u) => url($u));
    return response()
        ->view('sitemap', ['urls' => $urls])
        ->header('Content-Type', 'application/xml');
});
```
`resources/views/sitemap.blade.php`: standard urlset XML iterating `$urls`.

- [ ] **Step 3: Homepage composition**

Decide: homepage = `home` page blocks (hero + stats) PLUS dynamic strips below via a special case when `$page->slug === 'home'`: append latest 3 published articles and top 3 published eresources. (**executed: the queries live in `PageController::show` — home is the only page mixing a dynamic feed, and controller-side keeps the view dumb and the feed testable; the view special-cases on `$page->slug === 'home'` and renders the passed collections.**) Section headings "Berita Terbaru" and "Unduhan".

- [ ] **Step 4: README + .env.example + cleanup**

Write `README.md` (Indonesian): what it is, SP1 scope, stack, setup (php/composer/node, sqlite dev, `composer install`, `npm install && npm run build`, `cp .env.example .env`, `php artisan key:generate`, `migrate --seed`, admin creds note, `php artisan serve`), test (`php artisan test`), roadmap SP2–SP4 pointer to the spec. Complete `.env.example` with MySQL prod block + `FSBMM_ADMIN_*`. (**executed: `welcome.blade.php` was already removed in Task 6, when `/` moved to the DB home page — no references remain.**)

- [ ] **Step 5: Full suite green**

```bash
php artisan migrate:fresh --seed
php artisan test
```
Expected: ALL PASS (Tasks 3–8 tests).

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(seo): meta + sitemap + homepage composition + README"
```

---

## Final Verification

```bash
php artisan test                  # all feature tests pass
npm run build                     # production assets build clean
php artisan migrate:fresh --seed  # clean DB with demo content
php artisan serve                 # manual smoke: /, /berita, /sba, /e-resource, /e-learning, /tentang, /kontak, /admin/login
```

## Guard-count/scope bookkeeping (not applicable — new project)

SP1 ships **8 Eloquent-backed collections/pages** driven by DB: home/tentang/kontak pages (+blocks), articles (+categories), organizations, eresources, courses — plus Filament admin CRUD for each and role-gated panel login. Later sub-projects (SP2–SP4) are planned separately from this repo's spec.

## Non-Goals (from spec, repeated for executor clarity)

- No SBA logins, no tenant scoping, no member/PII data, no lessons/quizzes authoring, no public user registration, no automated SPMKB migration — all explicitly deferred to SP2–SP4.
- No page-builder blocks beyond the six types; no multi-language layer (Indonesian only).
