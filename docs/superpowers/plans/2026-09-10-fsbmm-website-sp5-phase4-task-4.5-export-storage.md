# Task 4.5: Export Storage — Implementation Plan (Resumed)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended).
> Steps use checkbox (`- [ ]`) syntax. Narrative: plan originally crafted, then Claude reviewed & hardened
> it (PII-guard) and wrote the test rewrite (uncommitted). This file records the final adjusted plan.

**Goal:** Rework exports (4.1–4.4) from direct-stream to spec §8.4: `generate (sync, lazy cursor) →
temporary private storage (disk `local`, TTL 1 jam) → authorized download via signed URL (plus
auth + org-match guard for PII) → delete`.

**Architecture:** `ReportExport::generate()` (replaces `streamFor()`) writes `storage/app/private/exports/
{prefix}-{org}-{Y-m-d}.{ext}` and returns `URL::temporarySignedRoute('exports.download', +1h,
['file' => 'exports/'.$name, 'org' => $organizationId])`. Panel routes 302-redirect to that URL.
`ExportDownloadController` (rute publik `/exports/download` + `middleware('signed')`, di ATAS catch-all
`{page:slug}`) enforces: signed (middleware) → authenticated → org match (PII hardening, deviation from
e-resource pattern) → path traversal guard → exists → TTL → `response()->download()->deleteFileAfterSend(true)`.

**Tech Stack:** PHP 8.3+, Laravel 12 `Storage::disk('local')` (root `app/private`, no config change),
`URL::temporarySignedRoute`, `middleware('signed')` (existing e-resource pattern), fputcsv/OpenSpout kept.

## Global Constraints
- `/exports/download` MUST be declared ABOVE the `{page:slug}` catch-all in `routes/web.php`.
- `local` disk root already `storage_path('app/private')` — NO new disk/config.
- Filename stays NIK-free `{prefix}-{org}-{Y-m-d}.{ext}`; same day same org overwrites (single file,
  TTL sweep reclaims next-day).
- Tenancy from `auth()->user()->organization_id` at generate; download additionally requires the
  authenticated user's org to equal the signed `org` param (Claude hardening) → 403 for anonymous and
  cross-org. Download route has NO auth middleware (403, not 302).
- Queue job for large datasets DEFERRED (annotation; per-SBA datasets small, `cursor()` lazy).
- WIP Phase-2 untouched; provider carries WIP → precise staging (blob + `hash-object`/`update-index`)
  + working-tree sync (lessons 4.3/4.4).
- pint clean final; watch `no_unused_imports` across moved `BinaryFileResponse`/`RedirectResponse`.
- `deleteFileAfterSend` untestable in features → TTL/sweep tested via `touch()` backdate.
- Baseline: HEAD `6c67021`; ExportTest red **24 failed / 3 passed** (Claude rewrite, uncommitted).
  Target: ExportTest **27 passed**; suite **314 passed / 1 failed** (Phase-6 placeholder only).

## Task 1: Commit Claude's test rewrite (no impl)
- [x] Claude left dirty: `tests/Feature/ExportTest.php` (27 tests, followExport helper, 7 new storage +
  PII-guard tests) and `tests/Feature/Sp5SecurityTest.php` (`test_sba_a_cannot_export_sba_b_data`
  follows 302 hop; plus pre-existing Phase-2 WIP in same file).
- [ ] Commit both as ONE test commit:
  `git add tests/Feature/ExportTest.php tests/Feature/Sp5SecurityTest.php`
  `git commit -m "test(sp5): export storage signed-url flow with auth+org guard tests"`
- [ ] Verify `ExportTest` still **24 failed / 3 passed** (red baseline, not regression).

## Task 2: Implementation (feat commit)
- [ ] `app/Exports/ReportExport.php`: remove `streamFor()` (+ unused `BinaryFileResponse`/`Str`);
  imports `Storage`, `URL`; add `generate()` (verbatim below) + `sweepExpired()`.

```php
public static function generate(int $organizationId, array $filters, string $format = 'csv'): string
{
    $name = sprintf('%s-%s-%s.%s', static::filenamePrefix(), $organizationId, now()->format('Y-m-d'), $format);
    $disk = Storage::disk('local');
    $disk->makeDirectory('exports');
    static::sweepExpired($disk); // ponytail: lazy sweep per generate; hourly-command only if files accumulate
    $query = static::scopedQuery($organizationId, $filters);
    $format === 'xlsx'
        ? static::writeXlsx($query, $disk->path('exports/'.$name), $filters)
        : static::writeCsv($query, $disk->path('exports/'.$name), $filters);

    return URL::temporarySignedRoute('exports.download', now()->addHour(), ['file' => 'exports/'.$name, 'org' => $organizationId]);
}

private static function sweepExpired($disk): void
{
    foreach ($disk->files('exports') as $file) {
        if ($disk->lastModified($file) < now()->subHour()->timestamp) {
            $disk->delete($file);
        }
    }
}
```

- [ ] Create `app/Http/Controllers/Public/ExportDownloadController.php` (verbatim below).

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportDownloadController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        abort_unless(Auth::check(), 403);
        abort_unless((int) Auth::user()->organization_id === (int) $request->query('org'), 403);

        $disk = Storage::disk('local');
        $path = (string) $request->query('file');

        $real = realpath($disk->path($path));
        $root = realpath($disk->path(''));
        abort_unless(
            $real !== false && $root !== false && str_starts_with($real, $root.DIRECTORY_SEPARATOR),
            404,
        );
        abort_unless($disk->exists($path), 404, 'File tidak ditemukan.');
        abort_unless($disk->lastModified($path) >= now()->subHour()->timestamp, 404, 'File telah kedaluwarsa.');

        return response()->download($disk->path($path), basename($path))->deleteFileAfterSend(true);
    }
}
```

- [ ] `routes/web.php`: add `use App\Http\Controllers\Public\ExportDownloadController;` + below the
  e-resource block, above the catch-all:
```php
Route::get('/exports/download', [ExportDownloadController::class, 'download'])
    ->name('exports.download')
    ->middleware('signed');
```
- [ ] `SbaPanelProvider.php` precise staging + working-tree sync: swap `BinaryFileResponse` →
  `Illuminate\Http\RedirectResponse`; each of the 4 export closures returns
  `redirect()->to(<Exporter>::generate(auth()->user()->organization_id, request()->only([...]), request()->string('format', 'csv')->toString()))` (filter arrays per exporter as in current routes). Blob from HEAD + python-replace → update-index; then apply identical edits to working tree; verify no leftover in `git diff HEAD`.
- [ ] Commit: `git add app/Exports/ReportExport.php app/Http/Controllers/Public/ExportDownloadController.php routes/web.php` then `git commit -m "feat(sp5): export assets to private disk served via signed urls with auth+org guard"`.
- [ ] Verify: `ExportTest` **27 passed**; pint clean; provider diff clean of task lines.

## Task 3: Docs + full verify (docs commit)
- [ ] Tick 4 items of Task 4.5 in `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md`.
- [ ] Append `(** executed @2026-09-10 ... **)` annotation: test/feat/docs hashes; §8.4 mapping;
  hardening deviation (signed alone insufficient for PII → auth + `org` param check, 403, no auth
  middleware to avoid 302); `test_export_stored_in_private_disk_not_public` clean Storage assertions;
  queue-for-large-datasets deferred; ExportTest 27; suite numbers.
- [ ] `composer test` → **314 passed / 1 failed** (placeholder only); `vendor/bin/pint` clean;
  `npm run build` success.
- [ ] Commit: `git add docs/... && git commit -m "docs(sp5): task 4.5 export storage annotations + checklist"`.