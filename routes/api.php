<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\PasswordResetController;

/*
|--------------------------------------------------------------------------
| IAM Auth Routes (Publik)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);

    // Rate limited: maks 5 percobaan login per menit per IP
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    // Rate limited: maks 3 permintaan per 5 menit per IP
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
        ->middleware('throttle:forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

/*
|--------------------------------------------------------------------------
| SSO Routes
|--------------------------------------------------------------------------
| /verify dan /refresh tidak memerlukan Sanctum (dipanggil server-to-server)
| /token dan /revoke memerlukan Sanctum (user harus sudah login)
*/
Route::prefix('sso')->group(function () {
    // Rate limited: maks 60 verifikasi per menit (server-to-server)
    Route::post('/verify', [SsoController::class, 'verify'])
        ->middleware('throttle:sso-verify');
    Route::post('/refresh', [SsoController::class, 'refresh']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/token', [SsoController::class, 'token']);
        Route::post('/revoke', [SsoController::class, 'revoke']);
    });
});

/*
|--------------------------------------------------------------------------
| Users CRUD Routes (Admin Only)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', App\Http\Controllers\UserController::class);
});
