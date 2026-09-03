<?php

use App\Http\Controllers\Public\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sba', [OrganizationController::class, 'index'])->name('organizations.index');
Route::get('/sba/{organization:slug}', [OrganizationController::class, 'show'])->name('organizations.show');
