<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TravelStateController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::put('/auth/password', [AuthController::class, 'password']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::apiResource('visits', VisitController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/me/travel-state', [TravelStateController::class, 'show']);
        Route::get('/me/home', [HomeController::class, 'show']);
        Route::put('/me/completions/{sightId}', [TravelStateController::class, 'completion']);
        Route::put('/me/wishlist/{targetId}', [TravelStateController::class, 'wishlist']);
        Route::put('/me/plan', [TravelStateController::class, 'plan']);
        Route::get('/collections', [TravelStateController::class, 'collections']);
        Route::put('/me/collections/{collectionId}', [TravelStateController::class, 'updateCollection']);
    });
    Route::middleware('throttle:catalog')->group(function (): void {
        Route::get('/countries', [CountryController::class, 'index']);
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/cities/{geonameId}', [CityController::class, 'show']);
        Route::get('/catalog/version', [CatalogController::class, 'version']);
    });
});
