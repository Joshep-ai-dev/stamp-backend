<?php

use App\Http\Controllers\AdminAiController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminPageController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\LegacyImageController;
use App\Http\Controllers\WebsiteController;
use App\Http\Middleware\RequireAdminKey;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'index'])->name('website.home');
Route::get('/kroo-website', [WebsiteController::class, 'index'])->name('website.legacy');
Route::get('/privacy', [WebsiteController::class, 'privacy'])->name('website.privacy');
Route::get('/assets/{filename}', [WebsiteController::class, 'asset'])
    ->where('filename', '[A-Za-z0-9_-]+\.(?:png|svg)')
    ->name('website.asset');
Route::get('/images/page/{filename}', [WebsiteController::class, 'pageImage'])
    ->where('filename', '(?:[1-5]\.jpg|phone\.png|stamp[1-3]\.png|apple\.png|google\.png)')
    ->name('website.page-image');
Route::get('/images/{folder}/{filename}', [LegacyImageController::class, 'public'])
    ->where('folder', 'sights|users|collection|daily-destinations|countries|states|cities')
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('images.public');
Route::get('/storage/images/{filename}', [LegacyImageController::class, 'show'])->name('images.legacy');
Route::get('/admin', [AdminPageController::class, 'index'])->name('admin.page');
Route::middleware(RequireAdminKey::class)->prefix('/admin/api')->group(function (): void {
    Route::get('/ai', [AdminAiController::class, 'index']);
    Route::post('/ai', [AdminAiController::class, 'start']);
    Route::post('/ai/image', [AdminAiController::class, 'generateImage']);
    Route::post('/ai/text', [AdminAiController::class, 'generateText']);
    Route::post('/ai/recover-rate-limits', [AdminAiController::class, 'recoverRateLimits']);
    Route::delete('/ai/{id}/items/{itemId}', [AdminAiController::class, 'removeItem']);
    Route::put('/ai/{id}/content/{target}', [AdminAiController::class, 'updateContent']);
    Route::delete('/ai/{id}/content/{target}', [AdminAiController::class, 'removeContent']);
    Route::get('/ai/{id}/results', [AdminAiController::class, 'results']);
    Route::get('/ai/{id}', [AdminAiController::class, 'show']);
    Route::post('/ai/{id}/fill-missing', [AdminAiController::class, 'fillMissing']);
    Route::post('/ai/{id}/process', [AdminAiController::class, 'process']);
    Route::post('/ai/{id}/pause', [AdminAiController::class, 'pause']);
    Route::post('/ai/{id}/resume', [AdminAiController::class, 'resume']);
    Route::get('/meta', [AdminController::class, 'meta']);
    Route::get('/states', [AdminController::class, 'states']);
    Route::post('/states', [AdminController::class, 'storeState']);
    Route::get('/us-states', [AdminController::class, 'stateList']);
    Route::put('/us-states/{id}', [AdminController::class, 'updateState']);
    Route::get('/cities', [AdminController::class, 'cities']);
    Route::post('/images', [AdminController::class, 'upload']);
    Route::get('/{type}', [AdminController::class, 'index'])->whereIn('type', ['countries', 'cities', 'sights', 'collections', 'collection-kinds', 'collection-lists', 'daily-destinations']);
    Route::post('/{type}', [AdminController::class, 'store'])->whereIn('type', ['cities', 'sights', 'collections', 'collection-kinds', 'collection-lists', 'daily-destinations']);
    Route::put('/{type}/{id}', [AdminController::class, 'update'])->whereIn('type', ['countries', 'cities', 'sights', 'collections', 'collection-kinds', 'collection-lists', 'daily-destinations']);
    Route::delete('/{type}/{id}', [AdminController::class, 'destroy'])->whereIn('type', ['cities', 'sights', 'collections', 'collection-kinds', 'collection-lists', 'daily-destinations']);
});
Route::get('/daily-destinations', [ContentController::class, 'dailyDestinations']);
Route::get('/api/collections/{id}', [ContentController::class, 'collection']);
Route::get('/api/countries/{code}', [ContentController::class, 'country']);
Route::get('/api/countries/{code}/cities', [ContentController::class, 'countryCities']);
Route::get('/api/cities/{id}', [ContentController::class, 'city']);
Route::get('/api/cities/{id}/sights', [ContentController::class, 'citySights']);
Route::get('/api/sights/{id}', [ContentController::class, 'sight']);
