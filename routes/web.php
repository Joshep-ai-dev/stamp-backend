<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminPageController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\LegacyImageController;
use App\Http\Controllers\WebsiteController;
use App\Http\Middleware\RequireAdminKey;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'index'])->name('website.home');
Route::get('/kroo-website', [WebsiteController::class, 'index'])->name('website.legacy');
Route::get('/images/{folder}/{filename}', [LegacyImageController::class, 'public'])
    ->where('folder', 'sights|users|collection|daily-destinations')
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('images.public');
Route::get('/storage/images/{filename}', [LegacyImageController::class, 'show'])->name('images.legacy');
Route::get('/admin', [AdminPageController::class, 'index'])->name('admin.page');
Route::middleware(RequireAdminKey::class)->prefix('/admin/api')->group(function (): void {
    Route::get('/meta', [AdminController::class, 'meta']);
    Route::get('/states', [AdminController::class, 'states']);
    Route::get('/cities', [AdminController::class, 'cities']);
    Route::post('/images', [AdminController::class, 'upload']);
    Route::get('/{type}', [AdminController::class, 'index'])->whereIn('type', ['sights', 'collections', 'collection-kinds', 'collection-lists', 'daily-destinations']);
    Route::post('/{type}', [AdminController::class, 'store'])->whereIn('type', ['sights', 'collections', 'collection-kinds', 'collection-lists', 'daily-destinations']);
    Route::put('/{type}/{id}', [AdminController::class, 'update'])->whereIn('type', ['sights', 'collections', 'collection-kinds', 'collection-lists', 'daily-destinations']);
    Route::delete('/{type}/{id}', [AdminController::class, 'destroy'])->whereIn('type', ['sights', 'collections', 'collection-kinds', 'collection-lists', 'daily-destinations']);
});
Route::get('/daily-destinations', [ContentController::class, 'dailyDestinations']);
Route::get('/api/collections/{id}', [ContentController::class, 'collection']);
Route::get('/api/countries/{code}', [ContentController::class, 'country']);
Route::get('/api/countries/{code}/cities', [ContentController::class, 'countryCities']);
Route::get('/api/cities/{id}', [ContentController::class, 'city']);
Route::get('/api/cities/{id}/sights', [ContentController::class, 'citySights']);
Route::get('/api/sights/{id}', [ContentController::class, 'sight']);
