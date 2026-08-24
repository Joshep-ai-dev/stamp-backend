<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ContentController;
use App\Http\Middleware\RequireAdminKey;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $html = file_get_contents(public_path('kroo-website.html'));
    abort_if($html === false, 500, 'The Kroo website could not be loaded.');

    return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
});

Route::get('/storage/images/{filename}', function (string $filename) {
    abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $filename), 404);
    $file = storage_path('app/public/images/'.$filename);
    abort_unless(is_file($file), 404);

    return response()->file($file);
});
Route::get('/admin', fn () => response(file_get_contents(base_path('reference/server/admin.html')))->header('Content-Type', 'text/html; charset=UTF-8'));
Route::middleware(RequireAdminKey::class)->prefix('/admin/api')->group(function (): void {
    Route::get('/meta', [AdminController::class, 'meta']);
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
