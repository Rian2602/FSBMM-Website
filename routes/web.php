<?php

use App\Http\Controllers\CardVerificationController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\CertificateVerificationController;
use App\Http\Controllers\Public\CourseController;
use App\Http\Controllers\Public\EresourceController;
use App\Http\Controllers\Public\ExportDownloadController;
use App\Http\Controllers\Public\OrganizationController;
use App\Http\Controllers\Public\PageController;
use App\Models\Article;
use App\Models\Organization;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

// robots.txt — declared as a route (not a static file) so the Sitemap directive
// uses an absolute, environment-correct URL (Sitemaps spec requires absolute).
Route::get('/robots.txt', function () {
    return response("User-agent: *\nDisallow:\n\nSitemap: " . url('/sitemap.xml'))
        ->header('Content-Type', 'text/plain');
});

// Sitemap — must stay above the page slug catch-all below.
Route::get('/sitemap.xml', function () {
    $entries = collect([
        ['loc' => url('/'), 'lastmod' => null],
        ['loc' => url('/tentang'), 'lastmod' => null],
        ['loc' => url('/berita'), 'lastmod' => null],
        ['loc' => url('/sba'), 'lastmod' => null],
        ['loc' => url('/e-resource'), 'lastmod' => null],
        ['loc' => url('/e-learning'), 'lastmod' => null],
        ['loc' => url('/kontak'), 'lastmod' => null],
    ]);

    $pages = Page::published()
        ->whereNotIn('slug', Page::STRUCTURAL_SLUGS)
        ->get()
        ->map(fn (Page $p) => [
            'loc' => url('/' . $p->slug),
            'lastmod' => optional($p->updated_at)->toDateString(),
        ]);

    $articles = Article::published()->get()->map(fn (Article $a) => [
        'loc' => url('/berita/' . $a->slug),
        'lastmod' => optional($a->published_at)->toDateString(),
    ]);

    $organizations = Organization::published()->get()->map(fn (Organization $o) => [
        'loc' => url('/sba/' . $o->slug),
        'lastmod' => optional($o->updated_at)->toDateString(),
    ]);

    $urls = $entries->concat($pages)->concat($articles)->concat($organizations);

    return response()
        ->view('sitemap', ['urls' => $urls])
        ->header('Content-Type', 'application/xml');
});

// Named entry pages (also reachable via the page slug catch-all below).
Route::get('/', fn () => app(PageController::class)->show('home'))->name('home');
Route::get('/tentang', fn () => app(PageController::class)->show('tentang'))->name('tentang');
Route::get('/kontak', fn () => app(PageController::class)->show('kontak'))->name('kontak');

// Collections — must stay above the page catch-all.
Route::get('/berita', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/berita/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('/sba', [OrganizationController::class, 'index'])->name('organizations.index');
Route::get('/sba/{organization:slug}', [OrganizationController::class, 'show'])->name('organizations.show');

Route::get('/e-resource', [EresourceController::class, 'index'])->name('eresources.index');
Route::get('/e-resource/{eresource:slug}/download', [EresourceController::class, 'download'])
    ->name('eresources.download')
    ->middleware('signed');

Route::get('/exports/download', [ExportDownloadController::class, 'download'])
    ->name('exports.download')
    ->middleware('signed');

Route::get('/e-learning', [CourseController::class, 'index'])->name('courses.index');

// Card verification — MUST stay above the page catch-all.
Route::get('/verifikasi/kartu/{token}', [CardVerificationController::class, 'verify'])
    ->name('cards.verify');

// Certificate verification — MUST stay above the page catch-all.
Route::get('/verifikasi/sertifikat/{token}', [CertificateVerificationController::class, 'verify'])
    ->name('certificates.verify');

// Page-builder catch-all (no whereIn: see Task 6 annotation).
Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');
