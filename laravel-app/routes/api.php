<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Website Builder — API Routes
|--------------------------------------------------------------------------
*/

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Protected routes — require Sanctum token
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // Website CRUD + generation
    Route::prefix('websites')->group(function () {
        Route::get('/',            [WebsiteController::class, 'index']);
        Route::post('/generate',   [WebsiteController::class, 'generate']);
        Route::get('/history',     [WebsiteController::class, 'history']);
        Route::get('/stats',       [WebsiteController::class, 'stats']);
        Route::get('/{id}',        [WebsiteController::class, 'show']);
        Route::put('/{id}',        [WebsiteController::class, 'update']);
        Route::delete('/{id}',     [WebsiteController::class, 'destroy']);
    });
});
