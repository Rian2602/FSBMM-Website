<?php

use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\OrganizationController;
use App\Http\Controllers\Public\PageController;
use Illuminate\Support\Facades\Route;

// Named entry pages (also reachable via the page slug catch-all below).
Route::get('/', fn () => app(PageController::class)->show('home'))->name('home');
Route::get('/tentang', fn () => app(PageController::class)->show('tentang'))->name('tentang');
Route::get('/kontak', fn () => app(PageController::class)->show('kontak'))->name('kontak');

// Collections — must stay above the page catch-all.
Route::get('/berita', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/berita/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('/sba', [OrganizationController::class, 'index'])->name('organizations.index');
Route::get('/sba/{organization:slug}', [OrganizationController::class, 'show'])->name('organizations.show');

// Page-builder catch-all. No ->whereIn() slug whitelist: a dynamic whitelist
// freezes when routes are cached (route:cache) and would 404 freshly
// published pages in production; the controller 404s unknown/unpublished
// slugs instead, and more specific routes above always win.
Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');
