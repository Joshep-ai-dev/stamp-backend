<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ContentController;
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
        Route::post('/profile/image', [ProfileController::class, 'uploadImage']);
        Route::apiResource('visits', VisitController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/me/sync/visits', [VisitController::class, 'sync']);
        Route::get('/me/travel-state', [TravelStateController::class, 'show']);
        Route::post('/me/sync/travel-state', [TravelStateController::class, 'sync']);
        Route::get('/me/home', [HomeController::class, 'show']);
        Route::put('/me/completions/{sightId}', [TravelStateController::class, 'completion']);
        Route::put('/me/wishlist/{targetId}', [TravelStateController::class, 'wishlist']);
        Route::put('/me/plan', [TravelStateController::class, 'plan']);
        Route::get('/collections', [TravelStateController::class, 'collections']);
        Route::put('/me/collections/{collectionId}', [TravelStateController::class, 'updateCollection']);
        Route::get('/me/community/leaderboard', [CommunityController::class, 'leaderboard']);
        Route::get('/me/friend-code', [CommunityController::class, 'friendCode']);
        Route::post('/me/friends/scan', [CommunityController::class, 'scan']);
    });
    Route::middleware('throttle:catalog')->group(function (): void {
        Route::get('/countries', [CountryController::class, 'index']);
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/cities/{geonameId}', [CityController::class, 'show']);
    Route::get('/catalog/version', [CatalogController::class, 'version']);
    Route::get('/catalog/nearby', [ContentController::class, 'nearby']);
    });
    Route::get('/daily-destinations', [ContentController::class, 'dailyDestinations']);
    Route::get('/community/leaderboard', [CommunityController::class, 'leaderboard']);
    Route::get('/catalog/countries/{code}', [ContentController::class, 'country']);
    Route::get('/catalog/countries/{code}/cities', [ContentController::class, 'countryCities']);
    Route::get('/catalog/countries/{code}/states', [ContentController::class, 'countryStates']);
    Route::get('/catalog/countries/{code}/states/{state}', [ContentController::class, 'state']);
    Route::get('/catalog/countries/{code}/states/{state}/cities', [ContentController::class, 'stateCities']);
    Route::get('/catalog/countries/{code}/states/{state}/airports', [ContentController::class, 'stateAirports']);
    Route::get('/collections/{id}', [ContentController::class, 'collection']);
    Route::get('/sights/{id}', [ContentController::class, 'sight']);
    Route::get('/catalog/cities/{id}', [ContentController::class, 'city']);
    Route::get('/catalog/cities/{id}/sights', [ContentController::class, 'citySights']);
    Route::get('/catalog/cities/{id}/airports', [ContentController::class, 'cityAirports']);
});
