<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\API\MenuController;
use App\Http\Controllers\API\RoleMenuController;
use App\Http\Controllers\API\ModuleController;

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
    Route::post('/refresh', [AuthController::class, 'refresh']);

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
Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::apiResource('users', App\Http\Controllers\UserController::class);
    Route::patch('users/{id}/status', [App\Http\Controllers\UserController::class, 'toggleStatus']);
    
    Route::apiResource('roles', App\Http\Controllers\RoleController::class);
    Route::apiResource('permissions', App\Http\Controllers\PermissionController::class);
    
    // Role & Permission Assignment
    Route::post('users/{id}/roles', [App\Http\Controllers\RoleAssignmentController::class, 'assignRoles']);
    Route::post('roles/{id}/permissions', [App\Http\Controllers\RoleAssignmentController::class, 'assignPermissions']);
    Route::get('user-roles', [App\Http\Controllers\RoleAssignmentController::class, 'getUserRoles']);
    Route::get('role-permissions', [App\Http\Controllers\RoleAssignmentController::class, 'getRolePermissions']);

    // Admin Session Management
    Route::get('sessions', [App\Http\Controllers\UserSessionController::class, 'adminIndex']);
    Route::delete('sessions/{id}', [App\Http\Controllers\UserSessionController::class, 'adminDestroy']);
    
    // Audit Logs
    Route::get('audit-logs', [App\Http\Controllers\AuditLogController::class, 'index']);
    Route::get('audit-logs/{id}', [App\Http\Controllers\AuditLogController::class, 'show']);

    // Menus (Admin Management)
    Route::get('menus', [MenuController::class, 'index']);
    Route::post('menus', [MenuController::class, 'store']);
    Route::put('menus/{menu}', [MenuController::class, 'update']);
    Route::delete('menus/{menu}', [MenuController::class, 'destroy']);
    Route::put('menus/{menu}/toggle', [MenuController::class, 'toggleActive']);

    // Role-Menus Assignment
    Route::get('role-menus/{roleId}', [RoleMenuController::class, 'getRoleMenus']);
    Route::post('role-menus/{roleId}', [RoleMenuController::class, 'assignMenusToRole']);

    // Modules (Admin Management)
    Route::get('modules', [ModuleController::class, 'index']);
    Route::post('modules', [ModuleController::class, 'store']);
    Route::put('modules/{module}', [ModuleController::class, 'update']);
    Route::delete('modules/{module}', [ModuleController::class, 'destroy']);
    Route::put('modules/{module}/toggle', [ModuleController::class, 'toggleActive']);
});

/*
|--------------------------------------------------------------------------
| Common Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->group(function () {
    Route::get('menus/my-menus', [MenuController::class, 'getMyMenus']);
});

/*
|--------------------------------------------------------------------------
| SIMPEG (Sistem Informasi Kepegawaian) Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->prefix('simpeg')->group(function () {
    // Unit Kerja
    Route::apiResource('unit-kerja', App\Http\Controllers\Simpeg\UnitKerjaController::class);

    // Jabatan & Jabatan Fungsional
    Route::apiResource('jabatan', App\Http\Controllers\Simpeg\JabatanController::class);
    Route::get('jabatan-fungsional', [App\Http\Controllers\Simpeg\JabatanFungsionalController::class, 'index']);
    Route::post('jabatan-fungsional', [App\Http\Controllers\Simpeg\JabatanFungsionalController::class, 'store']);

    // Pegawai
    Route::get('pegawai/me', [App\Http\Controllers\Simpeg\PegawaiController::class, 'me']);
    Route::apiResource('pegawai', App\Http\Controllers\Simpeg\PegawaiController::class);

    // Riwayat Jabatan & Pendidikan
    Route::get('pegawai/{id}/riwayat-jabatan', [App\Http\Controllers\Simpeg\RiwayatController::class, 'getRiwayatJabatan']);
    Route::post('pegawai/{id}/riwayat-jabatan', [App\Http\Controllers\Simpeg\RiwayatController::class, 'storeRiwayatJabatan']);
    Route::get('pegawai/{id}/riwayat-pendidikan', [App\Http\Controllers\Simpeg\RiwayatController::class, 'getRiwayatPendidikan']);
    Route::post('pegawai/{id}/riwayat-pendidikan', [App\Http\Controllers\Simpeg\RiwayatController::class, 'storeRiwayatPendidikan']);

    // Enterprise SIMPEG Features
    Route::get('dokumen', [App\Http\Controllers\Simpeg\DokumenController::class, 'index']);
    Route::post('dokumen', [App\Http\Controllers\Simpeg\DokumenController::class, 'store']);
    Route::get('dokumen/{id}/secure-view', [App\Http\Controllers\Simpeg\DokumenController::class, 'getSecureView']);
    Route::delete('dokumen/{id}', [App\Http\Controllers\Simpeg\DokumenController::class, 'destroy']);

    Route::get('cuti', [App\Http\Controllers\Simpeg\CutiController::class, 'index']);
    Route::post('cuti', [App\Http\Controllers\Simpeg\CutiController::class, 'store']);
    Route::patch('cuti/{id}/status', [App\Http\Controllers\Simpeg\CutiController::class, 'updateStatus']);

    Route::get('presensi', [App\Http\Controllers\Simpeg\PresensiController::class, 'index']);
    Route::post('presensi', [App\Http\Controllers\Simpeg\PresensiController::class, 'store']);

    Route::get('payroll', [App\Http\Controllers\Simpeg\PayrollController::class, 'index']);
    Route::post('payroll', [App\Http\Controllers\Simpeg\PayrollController::class, 'store']);

    Route::get('usulan-jafung', [App\Http\Controllers\Simpeg\UsulanJafungController::class, 'index']);
    Route::post('usulan-jafung', [App\Http\Controllers\Simpeg\UsulanJafungController::class, 'store']);

    Route::get('penilaian-kinerja', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'index']);
    Route::post('penilaian-kinerja', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'store']);

    // PDDikti Feeder Integration
    Route::get('pddikti/status', [App\Http\Controllers\Simpeg\PddiktiSyncController::class, 'getStatus']);
    Route::post('pddikti/sync-all', [App\Http\Controllers\Simpeg\PddiktiSyncController::class, 'triggerSync']);
});

