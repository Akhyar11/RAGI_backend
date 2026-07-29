<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\MfaController;

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

    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/mfa/login-verify', [AuthController::class, 'mfaLoginVerify']);

    // Rate limited: maks 3 permintaan per 5 menit per IP
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
        ->middleware('throttle:forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

    // Endpoint terproteksi (Passport atau Sanctum)
    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        // MFA Routes
        Route::post('/mfa/setup', [MfaController::class, 'setup']);
        Route::post('/mfa/verify', [MfaController::class, 'verify']);
        Route::post('/mfa/disable', [MfaController::class, 'disable']);

        // Session & Devices Management
        Route::get('/sessions', [App\Http\Controllers\UserSessionController::class, 'index']);
        Route::delete('/sessions/others', [App\Http\Controllers\UserSessionController::class, 'destroyOthers']);
        Route::delete('/sessions/{id}', [App\Http\Controllers\UserSessionController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| OAuth2 Resource Server
|--------------------------------------------------------------------------
| Endpoint untuk aplikasi klien mengambil data user setelah dapat token
*/
Route::middleware('auth:api')->group(function () {
    Route::get('/auth/user', [OAuthController::class, 'user']);
});

/*
|--------------------------------------------------------------------------
| SSO Token Routes (Custom — kompatibilitas mundur untuk mobile/API client)
|--------------------------------------------------------------------------
| Dipertahankan untuk client yang belum mendukung OAuth2 redirect flow.
| Endpoint /verify dan /refresh tidak memerlukan auth (server-to-server).
*/
Route::prefix('sso')->group(function () {
    Route::post('/verify', [SsoController::class, 'verify'])
        ->middleware('throttle:sso-verify');
    Route::post('/refresh', [SsoController::class, 'refresh']);

    Route::middleware('auth:api')->group(function () {
        Route::post('/token', [SsoController::class, 'token']);
        Route::post('/revoke', [SsoController::class, 'revoke']);
    });
});

/*
|--------------------------------------------------------------------------
| RBAC & Users CRUD Routes (Terproteksi Policy)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->group(function () {
    Route::apiResource('users', App\Http\Controllers\UserController::class);
    Route::apiResource('roles', App\Http\Controllers\RoleController::class);
    Route::get('permissions', [App\Http\Controllers\PermissionController::class, 'index']);
    
    // Audit Logs
    Route::get('audit-logs', [App\Http\Controllers\AuditLogController::class, 'index']);
    Route::get('audit-logs/{id}', [App\Http\Controllers\AuditLogController::class, 'show']);
});
