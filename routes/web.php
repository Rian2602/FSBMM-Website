<?php

use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\CourseController;
use App\Http\Controllers\Public\EresourceController;
use App\Http\Controllers\Public\OrganizationController;
use App\Http\Controllers\Public\PageController;
use Illuminate\Support\Facades\Route;

// Sitemap — must stay above the page slug catch-all below.
Route::get('/sitemap.xml', function () {
    $urls = collect(['/', '/tentang', '/berita', '/sba', '/e-resource', '/e-learning', '/kontak'])
        ->map(fn (string $url) => url($url));

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

Route::get('/e-learning', [CourseController::class, 'index'])->name('courses.index');

// Page-builder catch-all (no whereIn: see Task 6 annotation).
Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');
