# AGENTS.md

Laravel 12 + Filament 3 + Tailwind v4 public site for a trade-union federation
(FSBMM). Fully admin data-driven (Filament panel at `/admin`); public pages have
no hardcoded content. Indonesian locale/timezone (`APP_LOCALE=id`,
`Asia/Jakarta`).

See `README.md` for setup, stack, and structure that stays canonical.

## Multi-phase roadmap

This is **SP1**. Design + implementation plan live in
`docs/superpowers/specs/` and `docs/superpowers/plans/`. Future phases (SP2–SP4:
SBA accounts, member data, e-learning authoring) should follow the same pattern
and update the README scope table. Read the specs before touching these areas.

The SP1 plan records every deviation from spec/snippets as inline
`(** executed: ... **)` annotations (see plan lines 434, 497, 647, 724, 737,
794, 798). Preserve/append these when you change spec behavior.

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
  seeder. Never commit a real password or forget to change it in production.
- Local DB is SQLite (`database/database.sqlite`); production is MySQL via the
  commented block in `.env.example` — switching means `DB_CONNECTION=mysql` + fill
  the block. Don't leave SQLite config in prod.
- Queue, cache, and session drivers are `database`-based.
- Filament public assets are regenerated during `composer install`
  (`post-autoload-dump`) and not committed; run `php artisan filament:assets`
  manually if they go missing.

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

`resources/views/layouts/public.blade.php` uses placeholder Swiss-Brutalist-Green
token colors — not final brand. Swap when official assets arrive; don't treat
them as a design decision.

## Security conventions

- RichEditor content (articles/pages) is **trusted HTML** — authored only by
  authenticated staff, rendered raw. Escape all non-staff input; only these
  fields may bypass escaping.
- E-resource downloads use signed URLs (`middleware('signed')`) + per-download
  counting; deleted files return a clean 404.
- Structural pages (`home`, `tentang`, `kontak`) are protected from delete and
  slug-change at model + UI level. Don't bypass these guards.

## Verify before completion

Run `php artisan test` after non-trivial changes. Feature suite covers auth,
articles/scheduled publishing, e-resource library, organizations, pages, and SEO.
