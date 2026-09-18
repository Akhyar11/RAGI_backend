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

// Master Referensi Global Endpoint (Bisa diakses seluruh modul)
Route::get('referensi/{tipe}', [App\Http\Controllers\System\MasterReferensiController::class, 'getByTipe']);
Route::get('v1/referensi/{tipe}', [App\Http\Controllers\System\MasterReferensiController::class, 'getByTipe']);
Route::get('tipe-referensi', [App\Http\Controllers\System\MasterTipeReferensiController::class, 'index']);
Route::get('v1/tipe-referensi', [App\Http\Controllers\System\MasterTipeReferensiController::class, 'index']);

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
    Route::put('users/{id}/password', [App\Http\Controllers\UserController::class, 'changePassword']);
    
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

    // System Settings
    Route::get('system-settings', [App\Http\Controllers\IAM\SystemSettingController::class, 'index']);
    Route::post('system-settings', [App\Http\Controllers\IAM\SystemSettingController::class, 'update']);
    Route::post('system-settings/test-smtp', [App\Http\Controllers\IAM\SystemSettingController::class, 'testSmtp']);
    Route::post('system-settings/test-r2', [App\Http\Controllers\IAM\SystemSettingController::class, 'testR2']);

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

    // Master Referensi (Admin Multi-Modul Management)
    Route::get('master-referensi', [App\Http\Controllers\System\MasterReferensiController::class, 'index']);
    Route::get('master-referensi/categories', [App\Http\Controllers\System\MasterReferensiController::class, 'getCategories']);
    Route::post('master-referensi', [App\Http\Controllers\System\MasterReferensiController::class, 'store']);
    Route::get('master-referensi/{id}', [App\Http\Controllers\System\MasterReferensiController::class, 'show']);
    Route::put('master-referensi/{id}', [App\Http\Controllers\System\MasterReferensiController::class, 'update']);
    Route::patch('master-referensi/{id}/toggle', [App\Http\Controllers\System\MasterReferensiController::class, 'toggleActive']);
    Route::delete('master-referensi/{id}', [App\Http\Controllers\System\MasterReferensiController::class, 'destroy']);

    // Master Tipe Referensi (Admin Management)
    Route::get('master-tipe-referensi', [App\Http\Controllers\System\MasterTipeReferensiController::class, 'index']);
    Route::post('master-tipe-referensi', [App\Http\Controllers\System\MasterTipeReferensiController::class, 'store']);
    Route::get('master-tipe-referensi/{id}', [App\Http\Controllers\System\MasterTipeReferensiController::class, 'show']);
    Route::put('master-tipe-referensi/{id}', [App\Http\Controllers\System\MasterTipeReferensiController::class, 'update']);
    Route::patch('master-tipe-referensi/{id}/toggle', [App\Http\Controllers\System\MasterTipeReferensiController::class, 'toggleActive']);
    Route::delete('master-tipe-referensi/{id}', [App\Http\Controllers\System\MasterTipeReferensiController::class, 'destroy']);
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
    // Dashboard Stats
    Route::get('dashboard-stats', [App\Http\Controllers\Simpeg\SimpegDashboardController::class, 'stats']);

    // Unit Kerja
    Route::apiResource('unit-kerja', App\Http\Controllers\Simpeg\UnitKerjaController::class);

    // Jabatan & Jabatan Fungsional
    Route::apiResource('jabatan', App\Http\Controllers\Simpeg\JabatanController::class);
    Route::get('jabatan-fungsional', [App\Http\Controllers\Simpeg\JabatanFungsionalController::class, 'index']);
    Route::post('jabatan-fungsional', [App\Http\Controllers\Simpeg\JabatanFungsionalController::class, 'store']);

    // Pegawai
    Route::get('pegawai/template', [App\Http\Controllers\Simpeg\PegawaiController::class, 'downloadTemplate']);
    Route::post('pegawai/import', [App\Http\Controllers\Simpeg\PegawaiController::class, 'import']);
    Route::get('pegawai/me', [App\Http\Controllers\Simpeg\PegawaiController::class, 'me']);
    Route::get('pegawai/roles', [App\Http\Controllers\Simpeg\PegawaiController::class, 'getRoles']);
    Route::apiResource('pegawai', App\Http\Controllers\Simpeg\PegawaiController::class);
    Route::post('pegawai/{id}/reset-face', [App\Http\Controllers\Simpeg\PegawaiController::class, 'resetFace']);

    // Riwayat Jabatan, Pendidikan & Portofolio Tridharma Terpadu
    Route::get('pegawai/{id}/tridharma-dossier', [App\Http\Controllers\Simpeg\TridharmaDossierController::class, 'getDossier']);
    Route::get('pegawai/{id}/riwayat-jabatan', [App\Http\Controllers\Simpeg\RiwayatController::class, 'getRiwayatJabatan']);
    Route::post('pegawai/{id}/riwayat-jabatan', [App\Http\Controllers\Simpeg\RiwayatController::class, 'storeRiwayatJabatan']);
    Route::get('pegawai/{id}/riwayat-pendidikan', [App\Http\Controllers\Simpeg\RiwayatController::class, 'getRiwayatPendidikan']);
    Route::post('pegawai/{id}/riwayat-pendidikan', [App\Http\Controllers\Simpeg\RiwayatController::class, 'storeRiwayatPendidikan']);

    // Enterprise SIMPEG Features
    Route::get('dokumen', [App\Http\Controllers\Simpeg\DokumenController::class, 'index']);
    Route::post('dokumen', [App\Http\Controllers\Simpeg\DokumenController::class, 'store']);
    Route::get('dokumen/{id}/secure-view', [App\Http\Controllers\Simpeg\DokumenController::class, 'getSecureView']);
    Route::get('dokumen/{id}/download', [App\Http\Controllers\Simpeg\DokumenController::class, 'downloadFile']);
    Route::delete('dokumen/{id}', [App\Http\Controllers\Simpeg\DokumenController::class, 'destroy']);

    // Master Jenis Izin & Cuti
    Route::apiResource('master-jenis-cuti', App\Http\Controllers\Simpeg\MasterJenisCutiController::class);

    Route::get('cuti', [App\Http\Controllers\Simpeg\CutiController::class, 'index']);
    Route::post('cuti', [App\Http\Controllers\Simpeg\CutiController::class, 'store']);
    Route::patch('cuti/{id}/status', [App\Http\Controllers\Simpeg\CutiController::class, 'updateStatus']);

    Route::get('presensi/today', [App\Http\Controllers\Simpeg\PresensiController::class, 'today']);
    Route::post('presensi/clock-in', [App\Http\Controllers\Simpeg\PresensiController::class, 'clockIn']);
    Route::post('presensi/clock-out', [App\Http\Controllers\Simpeg\PresensiController::class, 'clockOut']);
    Route::post('presensi/keterangan', [App\Http\Controllers\Simpeg\PresensiController::class, 'setKeterangan']);
    Route::get('presensi/recap', [App\Http\Controllers\Simpeg\PresensiController::class, 'recap']);
    Route::post('presensi/{id}/approve', [App\Http\Controllers\Simpeg\PresensiController::class, 'approve']);
    Route::get('presensi/{id}', [App\Http\Controllers\Simpeg\PresensiController::class, 'show'])->whereNumber('id');
    Route::get('presensi', [App\Http\Controllers\Simpeg\PresensiController::class, 'index']);
    Route::post('presensi', [App\Http\Controllers\Simpeg\PresensiController::class, 'store']);
    Route::post('presensi/upload-rekap', [App\Http\Controllers\Simpeg\PresensiController::class, 'uploadRekap']);
    Route::delete('presensi/reset', [App\Http\Controllers\Simpeg\PresensiController::class, 'resetData']);
    Route::delete('presensi/{id}', [App\Http\Controllers\Simpeg\PresensiController::class, 'destroy']);
    Route::post('presensi/{id}/payroll', [App\Http\Controllers\Simpeg\PresensiController::class, 'processPayroll']);
    Route::post('presensi/fingerprint/sync', [App\Http\Controllers\Simpeg\PresensiController::class, 'syncFingerprint']);
    Route::post('presensi/daily-cutoff', [App\Http\Controllers\Simpeg\PresensiController::class, 'runDailyCutoff']);
    Route::post('presensi/shift-assign-bulk', [App\Http\Controllers\Simpeg\PresensiController::class, 'assignShiftBulk']);
    Route::get('presensi/pegawai/{id}/office-locations', [App\Http\Controllers\Simpeg\PresensiController::class, 'pegawaiOfficeLocations'])->whereNumber('id');
    Route::put('presensi/pegawai/{id}/office-locations', [App\Http\Controllers\Simpeg\PresensiController::class, 'updatePegawaiOfficeLocations'])->whereNumber('id');
    Route::post('presensi/office-assign-bulk', [App\Http\Controllers\Simpeg\PresensiController::class, 'assignOfficesBulk']);

    // Master Pengaturan Presensi (Lokasi, Shift, Parameter, Hari Libur, Perangkat Fingerprint)
    Route::get('presensi/settings', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'getSettings']);
    Route::put('presensi/settings', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'updateSettings']);
    Route::get('presensi/office-locations', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'listOfficeLocations']);
    Route::post('presensi/office-locations', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'storeOfficeLocation']);
    Route::put('presensi/office-locations/{id}', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'updateOfficeLocation']);
    Route::delete('presensi/office-locations/{id}', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'destroyOfficeLocation']);
    Route::get('presensi/shift-templates', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'listShiftTemplates']);
    Route::post('presensi/shift-templates', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'storeShiftTemplate']);
    Route::put('presensi/shift-templates/{id}', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'updateShiftTemplate']);
    Route::delete('presensi/shift-templates/{id}', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'destroyShiftTemplate']);
    Route::get('presensi/national-holidays', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'listNationalHolidays']);
    Route::post('presensi/national-holidays/sync', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'syncNationalHolidays']);
    Route::post('presensi/national-holidays', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'storeNationalHoliday']);
    Route::put('presensi/national-holidays/{id}', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'updateNationalHoliday']);
    Route::delete('presensi/national-holidays/{id}', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'destroyNationalHoliday']);
    Route::get('presensi/fingerprint-devices', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'listFingerprintDevices']);
    Route::post('presensi/fingerprint-devices', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'storeFingerprintDevice']);
    Route::put('presensi/fingerprint-devices/{id}', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'updateFingerprintDevice']);
    Route::delete('presensi/fingerprint-devices/{id}', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'destroyFingerprintDevice']);
    Route::post('presensi/fingerprint-devices/{id}/test-connection', [App\Http\Controllers\Simpeg\PresensiMasterSettingController::class, 'testFingerprintDeviceConnection']);

    // Master Komponen Gaji Fleksibel
    Route::get('payroll/komponen', [App\Http\Controllers\Simpeg\PayrollController::class, 'indexKomponen']);
    Route::post('payroll/komponen', [App\Http\Controllers\Simpeg\PayrollController::class, 'storeKomponen']);
    Route::put('payroll/komponen/{id}', [App\Http\Controllers\Simpeg\PayrollController::class, 'updateKomponen']);
    Route::delete('payroll/komponen/{id}', [App\Http\Controllers\Simpeg\PayrollController::class, 'destroyKomponen']);

    // Master Skala Gaji Pokok (Masa Kerja & Golongan)
    Route::get('payroll/skala-gaji', [App\Http\Controllers\Simpeg\PayrollController::class, 'indexSkalaGaji']);
    Route::post('payroll/skala-gaji', [App\Http\Controllers\Simpeg\PayrollController::class, 'storeSkalaGaji']);
    Route::put('payroll/skala-gaji/{id}', [App\Http\Controllers\Simpeg\PayrollController::class, 'updateSkalaGaji']);
    Route::delete('payroll/skala-gaji/{id}', [App\Http\Controllers\Simpeg\PayrollController::class, 'destroySkalaGaji']);

    // Tunjangan Jabatan Fungsional Akademik (Dosen)
    Route::get('payroll/jafung-tunjangan', [App\Http\Controllers\Simpeg\PayrollController::class, 'indexJafungTunjangan']);
    Route::put('payroll/jafung-tunjangan/{id}', [App\Http\Controllers\Simpeg\PayrollController::class, 'updateJafungTunjangan']);

    // Master Bracket Pajak PPh 21 (TER)
    Route::get('payroll/bracket-pph21', [App\Http\Controllers\Simpeg\PayrollController::class, 'indexBracketPph21']);
    Route::put('payroll/bracket-pph21/{id}', [App\Http\Controllers\Simpeg\PayrollController::class, 'updateBracketPph21']);

    // Komponen Gaji Pegawai
    Route::get('payroll/pegawai/{pegawaiId}/komponen', [App\Http\Controllers\Simpeg\PayrollController::class, 'getPegawaiKomponen']);
    Route::post('payroll/pegawai/{pegawaiId}/komponen', [App\Http\Controllers\Simpeg\PayrollController::class, 'savePegawaiKomponen']);

    // Rekapan Payroll, Detail Slip & Eksekusi SIKEU
    Route::get('payroll', [App\Http\Controllers\Simpeg\PayrollController::class, 'index']);
    Route::get('payroll/{id}', [App\Http\Controllers\Simpeg\PayrollController::class, 'show']);
    Route::post('payroll/generate', [App\Http\Controllers\Simpeg\PayrollController::class, 'generatePayroll']);
    Route::post('payroll/submit-to-sikeu', [App\Http\Controllers\Simpeg\PayrollController::class, 'submitToSikeu']);
    Route::post('payroll/{id}/process-payment', [App\Http\Controllers\Simpeg\PayrollController::class, 'processPayment']);

    Route::get('usulan-jafung', [App\Http\Controllers\Simpeg\UsulanJafungController::class, 'index']);
    Route::post('usulan-jafung', [App\Http\Controllers\Simpeg\UsulanJafungController::class, 'store']);

    // Penilaian Kinerja & SKP Butir-per-Butir
    Route::get('penilaian-kinerja/masters', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'masters']);
    Route::get('penilaian-kinerja', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'index']);
    Route::post('penilaian-kinerja', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'store']);
    Route::get('penilaian-kinerja/{id}', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'show']);
    Route::put('penilaian-kinerja/{id}', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'update']);
    Route::delete('penilaian-kinerja/{id}', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'destroy']);
    Route::post('penilaian-kinerja/{id}/submit-target', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'submitTarget']);
    Route::post('penilaian-kinerja/{id}/approve-target', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'approveTarget']);
    Route::post('penilaian-kinerja/{id}/submit-realisasi', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'submitRealisasi']);
    Route::post('penilaian-kinerja/{id}/evaluate', [App\Http\Controllers\Simpeg\PenilaianKinerjaController::class, 'evaluate']);

    // PDDikti Feeder Integration
    Route::get('pddikti/status', [App\Http\Controllers\Simpeg\PddiktiSyncController::class, 'getStatus']);
    Route::post('pddikti/sync-all', [App\Http\Controllers\Simpeg\PddiktiSyncController::class, 'triggerSync']);

    // Kompetensi Dosen & Pegawai (Sertifikasi, Tes, Pelatihan)
    Route::get('kompetensi/masters', [App\Http\Controllers\Simpeg\KompetensiController::class, 'masters']);
    Route::get('kompetensi/pencarian', [App\Http\Controllers\Simpeg\KompetensiController::class, 'pencarianAdmin']);
    Route::get('kompetensi/sertifikasi', [App\Http\Controllers\Simpeg\KompetensiController::class, 'listSertifikasi']);
    Route::post('kompetensi/sertifikasi', [App\Http\Controllers\Simpeg\KompetensiController::class, 'storeSertifikasi']);
    Route::post('kompetensi/sertifikasi/{id}', [App\Http\Controllers\Simpeg\KompetensiController::class, 'updateSertifikasi']);
    Route::put('kompetensi/sertifikasi/{id}', [App\Http\Controllers\Simpeg\KompetensiController::class, 'updateSertifikasi']);
    Route::delete('kompetensi/sertifikasi/{id}', [App\Http\Controllers\Simpeg\KompetensiController::class, 'destroySertifikasi']);
    Route::get('kompetensi/tes', [App\Http\Controllers\Simpeg\KompetensiController::class, 'listTes']);
    Route::post('kompetensi/tes', [App\Http\Controllers\Simpeg\KompetensiController::class, 'storeTes']);
    Route::post('kompetensi/tes/{id}', [App\Http\Controllers\Simpeg\KompetensiController::class, 'updateTes']);
    Route::put('kompetensi/tes/{id}', [App\Http\Controllers\Simpeg\KompetensiController::class, 'updateTes']);
    Route::delete('kompetensi/tes/{id}', [App\Http\Controllers\Simpeg\KompetensiController::class, 'destroyTes']);
    Route::get('kompetensi/pelatihan', [App\Http\Controllers\Simpeg\KompetensiController::class, 'listPelatihan']);
    Route::post('kompetensi/pelatihan', [App\Http\Controllers\Simpeg\KompetensiController::class, 'storePelatihan']);
    Route::post('kompetensi/pelatihan/{id}', [App\Http\Controllers\Simpeg\KompetensiController::class, 'updatePelatihan']);
    Route::put('kompetensi/pelatihan/{id}', [App\Http\Controllers\Simpeg\KompetensiController::class, 'updatePelatihan']);
    Route::delete('kompetensi/pelatihan/{id}', [App\Http\Controllers\Simpeg\KompetensiController::class, 'destroyPelatihan']);

    // Surat Tugas & LPJ Dinas Luar
    Route::get('surat-tugas/masters', [App\Http\Controllers\Simpeg\SuratTugasController::class, 'masters']);
    Route::get('surat-tugas', [App\Http\Controllers\Simpeg\SuratTugasController::class, 'index']);
    Route::get('surat-tugas/{id}', [App\Http\Controllers\Simpeg\SuratTugasController::class, 'show']);
    Route::post('surat-tugas', [App\Http\Controllers\Simpeg\SuratTugasController::class, 'store']);
    Route::post('surat-tugas/{id}', [App\Http\Controllers\Simpeg\SuratTugasController::class, 'update']);
    Route::put('surat-tugas/{id}', [App\Http\Controllers\Simpeg\SuratTugasController::class, 'update']);
    Route::post('surat-tugas/{id}/approve', [App\Http\Controllers\Simpeg\SuratTugasController::class, 'approve']);
    Route::post('surat-tugas/{id}/lpj', [App\Http\Controllers\Simpeg\SuratTugasController::class, 'uploadLpj']);
    Route::delete('surat-tugas/{id}', [App\Http\Controllers\Simpeg\SuratTugasController::class, 'destroy']);

    // Izin Parsial Jam Kerja Pegawai
    Route::get('izin-kerja/masters', [App\Http\Controllers\Simpeg\IzinJamKerjaController::class, 'masters']);
    Route::get('izin-kerja', [App\Http\Controllers\Simpeg\IzinJamKerjaController::class, 'index']);
    Route::get('izin-kerja/{id}', [App\Http\Controllers\Simpeg\IzinJamKerjaController::class, 'show']);
    Route::post('izin-kerja', [App\Http\Controllers\Simpeg\IzinJamKerjaController::class, 'store']);
    Route::post('izin-kerja/{id}', [App\Http\Controllers\Simpeg\IzinJamKerjaController::class, 'update']);
    Route::put('izin-kerja/{id}', [App\Http\Controllers\Simpeg\IzinJamKerjaController::class, 'update']);
    Route::post('izin-kerja/{id}/approve', [App\Http\Controllers\Simpeg\IzinJamKerjaController::class, 'approve']);
    Route::delete('izin-kerja/{id}', [App\Http\Controllers\Simpeg\IzinJamKerjaController::class, 'destroy']);

    // Repositori & Arsip SK Pegawai Mandiri
    Route::get('sk-pegawai/masters', [App\Http\Controllers\Simpeg\SkPegawaiController::class, 'masters']);
    Route::get('sk-pegawai', [App\Http\Controllers\Simpeg\SkPegawaiController::class, 'index']);
    Route::get('sk-pegawai/{id}', [App\Http\Controllers\Simpeg\SkPegawaiController::class, 'show']);
    Route::post('sk-pegawai', [App\Http\Controllers\Simpeg\SkPegawaiController::class, 'store']);
    Route::post('sk-pegawai/{id}', [App\Http\Controllers\Simpeg\SkPegawaiController::class, 'update']);
    Route::put('sk-pegawai/{id}', [App\Http\Controllers\Simpeg\SkPegawaiController::class, 'update']);
    Route::post('sk-pegawai/{id}/verify', [App\Http\Controllers\Simpeg\SkPegawaiController::class, 'verify']);
    Route::delete('sk-pegawai/{id}', [App\Http\Controllers\Simpeg\SkPegawaiController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| SPMB (Sistem Penerimaan Mahasiswa Baru) Routes
|--------------------------------------------------------------------------
*/
Route::prefix('spmb')->group(function () {
    Route::get('prodi', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'getProgramStudi']);
    Route::get('jalur', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'getJalurMasuk']);
    Route::get('gelombang', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'getGelombang']);
    Route::get('tahun-akademik', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'getTahunAkademik']);
    Route::get('tarif', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'getTarifSpmb']);
    Route::get('master-tipe-jalur', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'getMasterTipeJalur']);
    Route::get('referensi/{tipe}', [App\Http\Controllers\System\MasterReferensiController::class, 'getByTipe']);
    Route::get('berkas-requirement', [\App\Http\Controllers\API\Spmb\BerkasRequirementController::class, 'index']);
    Route::get('master/berkas-requirement', [\App\Http\Controllers\API\Spmb\BerkasRequirementController::class, 'index']);
});

Route::middleware('auth:api')->prefix('spmb')->group(function () {
    Route::get('laporan/statistik', [App\Http\Controllers\API\Spmb\LaporanSpmbController::class, 'statistik']);
    Route::get('laporan/export', [App\Http\Controllers\API\Spmb\LaporanSpmbController::class, 'exportCsv']);
});

Route::middleware('auth:api')->prefix('spmb')->group(function () {
    Route::apiResource('master/berkas-requirement', \App\Http\Controllers\API\Spmb\BerkasRequirementController::class)->except(['index']);
    Route::apiResource('master/tarif-ukt', \App\Http\Controllers\API\Spmb\TarifUktSpmbController::class);
    Route::post('master-tipe-jalur', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'storeMasterTipeJalur']);
    Route::put('master-tipe-jalur/{id}', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'updateMasterTipeJalur']);
    Route::delete('master-tipe-jalur/{id}', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'destroyMasterTipeJalur']);
});

/*
|--------------------------------------------------------------------------
| SIPPM (Penelitian & PkM) Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->prefix('sippm')->group(function () {
    // Master Skema, Periode & Rubrik Indikator Penilaian
    Route::get('skema', [App\Http\Controllers\Sippm\MasterSippmController::class, 'indexSkema']);
    Route::post('skema', [App\Http\Controllers\Sippm\MasterSippmController::class, 'storeSkema']);
    Route::put('skema/{id}', [App\Http\Controllers\Sippm\MasterSippmController::class, 'updateSkema']);
    Route::delete('skema/{id}', [App\Http\Controllers\Sippm\MasterSippmController::class, 'destroySkema']);

    Route::get('periode', [App\Http\Controllers\Sippm\MasterSippmController::class, 'indexPeriode']);
    Route::post('periode', [App\Http\Controllers\Sippm\MasterSippmController::class, 'storePeriode']);
    Route::put('periode/{id}', [App\Http\Controllers\Sippm\MasterSippmController::class, 'updatePeriode']);
    Route::delete('periode/{id}', [App\Http\Controllers\Sippm\MasterSippmController::class, 'destroyPeriode']);
    Route::apiResource('rubrik', App\Http\Controllers\Sippm\RubrikIndikatorController::class);
    Route::apiResource('iku5-standards', App\Http\Controllers\Sippm\StandarIku5ProdiController::class);

    // Proposal Kegiatan
    Route::get('proposal', [App\Http\Controllers\Sippm\ProposalKegiatanController::class, 'index']);
    Route::get('proposal/{id}', [App\Http\Controllers\Sippm\ProposalKegiatanController::class, 'show']);
    Route::post('proposal', [App\Http\Controllers\Sippm\ProposalKegiatanController::class, 'store']);
    Route::put('proposal/{id}', [App\Http\Controllers\Sippm\ProposalKegiatanController::class, 'update']);
    Route::post('proposal/{id}/submit', [App\Http\Controllers\Sippm\ProposalKegiatanController::class, 'submit']);
    Route::post('proposal/{id}/assign-reviewer', [App\Http\Controllers\Sippm\ProposalKegiatanController::class, 'assignReviewer']);

    // Reference Endpoints (SIMPEG Pegawai & SIAKAD Mata Kuliah Integration)
    Route::get('ref/dosen', [App\Http\Controllers\Sippm\ProposalKegiatanController::class, 'getDosenReference']);
    Route::get('ref/tendik', [App\Http\Controllers\Sippm\ProposalKegiatanController::class, 'getTendikReference']);
    Route::get('ref/mahasiswa/{mahasiswaId}/mata-kuliah-aktif', [App\Http\Controllers\Sippm\ProposalKegiatanController::class, 'getActiveMataKuliahMahasiswa']);

    // Reviewer & Final Decision
    Route::get('reviewer/assigned', [App\Http\Controllers\Sippm\ReviewerKegiatanController::class, 'myAssignedProposals']);
    Route::post('reviewer/{id}/penilaian', [App\Http\Controllers\Sippm\ReviewerKegiatanController::class, 'submitPenilaian']);
    Route::post('proposal/{id}/finalize', [App\Http\Controllers\Sippm\ReviewerKegiatanController::class, 'finalizeDecision']);

    // Kontrak, Pencairan, & Monev/Laporan
    Route::get('kontrak', [App\Http\Controllers\Sippm\KontrakMonevController::class, 'indexKontrak']);
    Route::post('proposal/{id}/kontrak', [App\Http\Controllers\Sippm\KontrakMonevController::class, 'storeKontrak']);
    Route::post('kontrak/{id}/pencairan', [App\Http\Controllers\Sippm\KontrakMonevController::class, 'requestPencairan']);
    Route::post('kontrak/{id}/upload-spk-ttd', [App\Http\Controllers\Sippm\KontrakMonevController::class, 'uploadSpkTtdBasah']);
    Route::post('kontrak/{id}/approve-spk', [App\Http\Controllers\Sippm\KontrakMonevController::class, 'approveSpk']);
    Route::post('pencairan/{id}/upload-resi-sikeu', [App\Http\Controllers\Sippm\KontrakMonevController::class, 'uploadResiSikeu']);
    Route::post('kontrak/{id}/laporan', [App\Http\Controllers\Sippm\KontrakMonevController::class, 'submitLaporan']);

    // Portofolio Luaran (Publikasi & HKI)
    Route::get('luaran/publikasi', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'indexPublikasi']);
    Route::post('luaran/publikasi', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'storePublikasi']);
    Route::post('luaran/publikasi/{id}/verify', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'verifyPublikasi']);
    Route::post('luaran/fetch-external', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'fetchExternalPublikasi']);
    Route::post('luaran/import-external', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'importExternalPublikasi']);

    Route::get('luaran/hki', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'indexHki']);
    Route::post('luaran/hki', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'storeHki']);
    Route::post('luaran/hki/{id}/verify', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'verifyHki']);

    // Cross-Module Integration Endpoints (UPM IKU & SIKEU Callback)
    Route::get('integration/upm-iku-metrics', [App\Http\Controllers\Sippm\MasterSippmController::class, 'getUpmMetrics']);
    Route::post('integration/sikeu-disbursement-callback/{id}', [App\Http\Controllers\Sippm\MasterSippmController::class, 'processDisbursementCallback']);

    // Pengumuman & Periode Hibah Official Announcements
    Route::get('pengumuman/active', [App\Http\Controllers\Sippm\PengumumanSippmController::class, 'getActive']);
    Route::get('pengumuman', [App\Http\Controllers\Sippm\PengumumanSippmController::class, 'index']);
    Route::post('pengumuman', [App\Http\Controllers\Sippm\PengumumanSippmController::class, 'store']);
    Route::post('pengumuman/{id}/upload-signed', [App\Http\Controllers\Sippm\PengumumanSippmController::class, 'uploadSigned']);
    Route::post('pengumuman/{id}/upload-template', [App\Http\Controllers\Sippm\PengumumanSippmController::class, 'uploadTemplate']);
    Route::post('pengumuman/{id}/publish', [App\Http\Controllers\Sippm\PengumumanSippmController::class, 'publish']);
});

/*
|--------------------------------------------------------------------------
| SIKEU (Keuangan, Akuntansi, & Pajak) Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->prefix('v1/sikeu')->group(function () {
    // API Tagihan Eksternal (SPMB, SIAKAD, SIMPEG, SIPPM)
    Route::post('tagihan/external', [App\Http\Controllers\Sikeu\ExternalTagihanController::class, 'createExternalBill']);

    // Konfigurasi Payment Gateway
    Route::get('/payment-gateway', [App\Http\Controllers\Sikeu\PaymentGatewayConfigController::class, 'index']);
    Route::get('/payment-gateway/active', [App\Http\Controllers\Sikeu\PaymentGatewayConfigController::class, 'getActive']);
    Route::get('/payment-gateway/{gatewayName}/balance', [App\Http\Controllers\Sikeu\PaymentGatewayConfigController::class, 'balance']);
    Route::put('/payment-gateway/{gatewayName}', [App\Http\Controllers\Sikeu\PaymentGatewayConfigController::class, 'update']);

    // Unit Kas Master
    Route::get('master/unit-kas', [App\Http\Controllers\Sikeu\UnitKasController::class, 'index']);
    Route::post('master/unit-kas', [App\Http\Controllers\Sikeu\UnitKasController::class, 'store']);
    Route::put('master/unit-kas/{id}', [App\Http\Controllers\Sikeu\UnitKasController::class, 'update']);
    Route::delete('master/unit-kas/{id}', [App\Http\Controllers\Sikeu\UnitKasController::class, 'destroy']);

    // Pengajuan Kas
    Route::get('pengajuan-kas', [App\Http\Controllers\Sikeu\PengajuanKasController::class, 'index']);
    Route::post('pengajuan-kas', [App\Http\Controllers\Sikeu\PengajuanKasController::class, 'store']);
    Route::post('pengajuan-kas/{id}/approve', [App\Http\Controllers\Sikeu\PengajuanKasController::class, 'approve']);



    // Tagihan Mahasiswa List & Detail
    Route::get('tagihan', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'indexTagihan']);
    Route::get('tagihan/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'showTagihan']);
    Route::post('tagihan/{id}/potongan', [App\Http\Controllers\Sikeu\PembayaranKasirController::class, 'addAdHocPotonganTagihan']);
    Route::delete('tagihan/potongan/{potonganId}', [App\Http\Controllers\Sikeu\PembayaranKasirController::class, 'deleteAdHocPotonganTagihan']);

    // Master Tarif Gaji & Transport Pegawai (SIKEU)
    Route::get('master/gaji-pegawai', [App\Http\Controllers\Sikeu\MasterGajiPegawaiController::class, 'index']);
    Route::post('master/gaji-pegawai', [App\Http\Controllers\Sikeu\MasterGajiPegawaiController::class, 'store']);

    // Master Jalur Kelas
    Route::get('master/jalur-kelas', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'indexJalurKelas']);
    Route::post('master/jalur-kelas', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'storeJalurKelas']);
    Route::put('master/jalur-kelas/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'updateJalurKelas']);
    Route::delete('master/jalur-kelas/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'destroyJalurKelas']);

    // Master Tarif UKT Kelompok
    Route::get('master/tarif-ukt', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'indexTarifUkt']);
    Route::post('master/tarif-ukt', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'storeTarifUkt']);
    Route::put('master/tarif-ukt/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'updateTarifUkt']);
    Route::delete('master/tarif-ukt/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'destroyTarifUkt']);

    // Master Program Beasiswa
    Route::get('master/beasiswa', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'indexBeasiswa']);
    Route::post('master/beasiswa', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'storeBeasiswa']);
    Route::put('master/beasiswa/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'updateBeasiswa']);
    Route::delete('master/beasiswa/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'destroyBeasiswa']);

    // Mapping Mahasiswa Penerima Beasiswa & Potongan
    Route::get('master/mahasiswa-beasiswa', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'indexMahasiswaBeasiswa']);
    Route::post('master/mahasiswa-beasiswa', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'storeMahasiswaBeasiswa']);
    Route::put('master/mahasiswa-beasiswa/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'updateMahasiswaBeasiswa']);
    Route::delete('master/mahasiswa-beasiswa/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'destroyMahasiswaBeasiswa']);

    // Setting Potongan Khusus Mahasiswa (Di Luar Beasiswa)
    Route::get('master/potongan-mahasiswa', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'indexPotonganMahasiswa']);
    Route::post('master/potongan-mahasiswa', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'storePotonganMahasiswa']);
    Route::put('master/potongan-mahasiswa/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'updatePotonganMahasiswa']);
    Route::delete('master/potongan-mahasiswa/{id}', [App\Http\Controllers\Sikeu\SikeuExtendedMasterController::class, 'destroyPotonganMahasiswa']);

    // Master Jalur Kelas & Tipe Mahasiswa (Referensi SPMB)
    Route::get('master/referensi/{tipe}', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'getReferensi']);
    Route::get('master/tipe-jalur', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'getMasterTipeJalur']);


    // Master Biaya Pendidikan (sebelumnya Jenis Biaya)
    Route::get('master/master-biaya', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexMasterBiaya']);
    Route::post('master/master-biaya', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'storeMasterBiaya']);
    Route::put('master/master-biaya/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateMasterBiaya']);
    Route::delete('master/master-biaya/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'destroyMasterBiaya']);

    // Alias master/jenis-biaya for frontend compatibility
    Route::get('master/jenis-biaya', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexMasterBiaya']);
    Route::post('master/jenis-biaya', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'storeMasterBiaya']);
    Route::put('master/jenis-biaya/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateMasterBiaya']);
    Route::delete('master/jenis-biaya/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'destroyMasterBiaya']);



    // Penetapan & Integrasi Tipe Tagihan Mahasiswa (SPMB / SIAKAD / Admin Change)
    Route::get('master/student-billing-categories', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'getStudentBillingCategories']);
    Route::get('master/student-billing-types', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexStudentBillingTypes']);
    Route::post('master/assign-student-billing-type', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'assignStudentBillingType']);
    Route::put('master/update-student-billing-type/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateStudentBillingType']);
    Route::post('master/sync-students', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'syncStudentsFromSiakad']);

    // Pencarian Mahasiswa untuk Tagihan & Dispensasi
    Route::get('mahasiswa-search', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'searchMahasiswa']);

    // Portal Tagihan & Invoice Mahasiswa Mandiri
    Route::get('mahasiswa/payment-channels', [App\Http\Controllers\Sikeu\MahasiswaTagihanController::class, 'paymentChannels']);
    Route::get('mahasiswa/tagihan', [App\Http\Controllers\Sikeu\MahasiswaTagihanController::class, 'myBills']);
    Route::get('mahasiswa/invoice/{id}', [App\Http\Controllers\Sikeu\MahasiswaTagihanController::class, 'generateInvoice']);
    Route::post('mahasiswa/invoice-batch', [App\Http\Controllers\Sikeu\MahasiswaTagihanController::class, 'generateBatchInvoice']);
    Route::get('mahasiswa/riwayat-pembayaran', [App\Http\Controllers\Sikeu\MahasiswaTagihanController::class, 'myPaymentHistory']);
    Route::post('mahasiswa/pay-bills', [App\Http\Controllers\Sikeu\MahasiswaTagihanController::class, 'payBills']);

    // Piutang Mahasiswa & Export Excel
    Route::get('piutang', [App\Http\Controllers\Sikeu\PiutangMahasiswaController::class, 'index']);
    Route::get('piutang/export-excel', [App\Http\Controllers\Sikeu\PiutangMahasiswaController::class, 'exportExcel']);

    // Dispensasi Pembayaran & Cetak Bukti Resmi
    Route::get('dispensasi', [App\Http\Controllers\Sikeu\DispensasiTagihanController::class, 'index']);
    Route::post('dispensasi', [App\Http\Controllers\Sikeu\DispensasiTagihanController::class, 'store']);
    Route::get('dispensasi/{id}', [App\Http\Controllers\Sikeu\DispensasiTagihanController::class, 'show']);
    Route::get('dispensasi/{id}/cetak-bukti', [App\Http\Controllers\Sikeu\DispensasiTagihanController::class, 'cetakBukti']);

    // Riwayat Pembayaran Mahasiswa
    Route::get('pembayaran', [App\Http\Controllers\Sikeu\ExternalTagihanController::class, 'indexPembayaran']);

    // Approval Pimpinan (Tagihan & Dispensasi)
    Route::get('approvals', [App\Http\Controllers\Sikeu\TagihanApprovalController::class, 'index']);
    Route::post('approvals/tagihan/{id}/approve', [App\Http\Controllers\Sikeu\TagihanApprovalController::class, 'approveTagihan']);
    Route::post('approvals/tagihan/{id}/reject', [App\Http\Controllers\Sikeu\TagihanApprovalController::class, 'rejectTagihan']);
    Route::post('approvals/dispensasi/{id}/approve', [App\Http\Controllers\Sikeu\TagihanApprovalController::class, 'approveDispensasi']);
    Route::post('approvals/dispensasi/{id}/reject', [App\Http\Controllers\Sikeu\TagihanApprovalController::class, 'rejectDispensasi']);

    // Pemasukan Kampus (Hibah SIPPM, Donatur, Kerjasama)
    Route::get('pemasukan', [App\Http\Controllers\Sikeu\PemasukanKampusController::class, 'index']);
    Route::post('pemasukan/external', [App\Http\Controllers\Sikeu\PemasukanKampusController::class, 'storeExternalIncome']);

    // Akuntansi & COA
    Route::get('akuntansi/coa', [App\Http\Controllers\Sikeu\AkuntansiController::class, 'indexCoa']);
    Route::post('akuntansi/coa', [App\Http\Controllers\Sikeu\AkuntansiController::class, 'storeCoa']);
    Route::get('akuntansi/jurnal', [App\Http\Controllers\Sikeu\AkuntansiController::class, 'indexJurnal']);
    Route::post('akuntansi/jurnal', [App\Http\Controllers\Sikeu\AkuntansiController::class, 'storeJurnal']);
    Route::get('akuntansi/buku-besar', [App\Http\Controllers\Sikeu\AkuntansiController::class, 'bukuBesar']);
    Route::get('akuntansi/laporan', [App\Http\Controllers\Sikeu\AkuntansiController::class, 'laporanKeuangan']);

    // Master Tarif SPMB (Jalur & Gelombang)
    Route::get('master/tarif-spmb', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexTarifSpmb']);
    Route::post('master/tarif-spmb', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'storeTarifSpmb']);
    Route::put('master/tarif-spmb/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateTarifSpmb']);
    Route::delete('master/tarif-spmb/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'destroyTarifSpmb']);

    // Endpoint Integrasi SPMB (Get Tarif Real-Time)
    Route::get('spmb/tarif', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'getTarifSpmb']);

    // Setting Tarif per Angkatan/Prodi/Semester
    Route::get('master/setting-tarif', [App\Http\Controllers\Sikeu\SettingTarifController::class, 'index']);
    Route::post('master/setting-tarif', [App\Http\Controllers\Sikeu\SettingTarifController::class, 'store']);
    Route::put('master/setting-tarif/{id}', [App\Http\Controllers\Sikeu\SettingTarifController::class, 'update']);
    Route::delete('master/setting-tarif/{id}', [App\Http\Controllers\Sikeu\SettingTarifController::class, 'destroy']);
    Route::get('master/program-studi', [App\Http\Controllers\Sikeu\SettingTarifController::class, 'getProgramStudiList']);

    // Pengaturan On/Off Skema Golongan UKT
    Route::get('settings/golongan-ukt', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'getUktSetting']);
    Route::post('settings/golongan-ukt', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateUktSetting']);

    // Pembayaran Kasir Kampus (Offline) & Koreksi
    Route::get('mahasiswa/{id}/unpaid-bills', [App\Http\Controllers\Sikeu\PembayaranKasirController::class, 'getStudentUnpaidBills']);
    Route::post('pembayaran/kasir', [App\Http\Controllers\Sikeu\PembayaranKasirController::class, 'processPayment']);
    Route::post('pembayaran/direct-cashier', [App\Http\Controllers\Sikeu\PembayaranKasirController::class, 'directCashierPayment']);
    Route::post('pembayaran/{id}/koreksi', [App\Http\Controllers\Sikeu\PembayaranKasirController::class, 'koreksiPayment']);

    // Generate Tagihan Semester Masal
    Route::post('tagihan/generate-mass', [App\Http\Controllers\Sikeu\PembayaranKasirController::class, 'generateMassTagihan']);

    // SPMB Payment Callback / Webhook Integration
    Route::post('callback/spmb/{calonMahasiswaId}', [App\Http\Controllers\Sikeu\SpmBSikeuCallbackController::class, 'handleSpmbPaymentCallback']);
    Route::get('checkout/lookup-va', [App\Http\Controllers\Sikeu\SpmBSikeuCallbackController::class, 'lookupVa']);

    // Dashboard Executive Summary & Live Xendit Balance
    Route::get('dashboard-summary', [App\Http\Controllers\Sikeu\SikeuDashboardController::class, 'summary']);

    // Pengeluaran Kampus & Vendor / Petty Cash Operasional
    Route::get('pengeluaran', [App\Http\Controllers\Sikeu\PengeluaranKampusController::class, 'index']);
    Route::post('pengeluaran', [App\Http\Controllers\Sikeu\PengeluaranKampusController::class, 'store']);
    Route::get('pengeluaran/{id}', [App\Http\Controllers\Sikeu\PengeluaranKampusController::class, 'show']);

    // Pajak Kampus (PPh 21, PPh 23, PPN 11%) & Setor NTPN
    Route::get('pajak', [App\Http\Controllers\Sikeu\PajakKampusController::class, 'index']);
    Route::post('pajak/{id}/setor', [App\Http\Controllers\Sikeu\PajakKampusController::class, 'setorPajak']);
});

// Alias for direct non-v1 calls (backward compatibility with axios client baseURL)
Route::middleware('auth:api')->prefix('sikeu')->group(function () {
    Route::get('master/gaji-pegawai', [App\Http\Controllers\Sikeu\MasterGajiPegawaiController::class, 'index']);
    Route::post('master/gaji-pegawai', [App\Http\Controllers\Sikeu\MasterGajiPegawaiController::class, 'store']);
});

// Public Printable Document Route (Accessible directly via browser link)
Route::get('sippm/pengumuman/{id}/html-draft', [App\Http\Controllers\Sippm\PengumumanSippmController::class, 'renderDraftHtml']);

/*
|--------------------------------------------------------------------------
| SINAPRA (Sarana, Prasarana, & Aset) Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->prefix('sinapra')->group(function () {
    // Gedung & Ruangan
    Route::get('gedung', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'index']);
    Route::post('gedung', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'store']);
    Route::get('gedung/{gedung}', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'show']);
    Route::put('gedung/{gedung}', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'update']);
    Route::delete('gedung/{gedung}', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'destroy']);

    Route::get('ruangan', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'indexRuangan']);
    Route::post('ruangan', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'storeRuangan']);
    Route::post('ruangan/check-ketersediaan', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'checkKetersediaanRuangan']);
    Route::get('ruangan/{ruangan}', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'showRuangan']);
    Route::put('ruangan/{ruangan}', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'updateRuangan']);
    Route::delete('ruangan/{ruangan}', [App\Http\Controllers\Sinapra\GedungRuanganController::class, 'destroyRuangan']);

    // Kategori Aset & Aset
    Route::get('kategori-aset', [App\Http\Controllers\Sinapra\AsetController::class, 'indexKategori']);
    Route::post('kategori-aset', [App\Http\Controllers\Sinapra\AsetController::class, 'storeKategori']);
    Route::get('kategori-aset/{kategori}', [App\Http\Controllers\Sinapra\AsetController::class, 'showKategori']);
    Route::put('kategori-aset/{kategori}', [App\Http\Controllers\Sinapra\AsetController::class, 'updateKategori']);
    Route::delete('kategori-aset/{kategori}', [App\Http\Controllers\Sinapra\AsetController::class, 'destroyKategori']);

    Route::get('aset', [App\Http\Controllers\Sinapra\AsetController::class, 'index']);
    Route::post('aset', [App\Http\Controllers\Sinapra\AsetController::class, 'store']);
    Route::get('aset/{aset}', [App\Http\Controllers\Sinapra\AsetController::class, 'show']);
    Route::get('aset/{aset}/hitung-penyusutan', [App\Http\Controllers\Sinapra\AsetController::class, 'hitungPenyusutan']);
    Route::put('aset/{aset}', [App\Http\Controllers\Sinapra\AsetController::class, 'update']);
    Route::delete('aset/{aset}', [App\Http\Controllers\Sinapra\AsetController::class, 'destroy']);

    // Peminjaman Ruangan & Aset
    Route::get('peminjaman-ruangan', [App\Http\Controllers\Sinapra\PeminjamanController::class, 'indexRuangan']);
    Route::post('peminjaman-ruangan', [App\Http\Controllers\Sinapra\PeminjamanController::class, 'applyRuangan']);
    Route::get('peminjaman-ruangan/{peminjaman}', [App\Http\Controllers\Sinapra\PeminjamanController::class, 'showRuangan']);
    Route::post('peminjaman-ruangan/{peminjaman}/approve', [App\Http\Controllers\Sinapra\PeminjamanController::class, 'approveRuangan']);

    Route::get('peminjaman-aset', [App\Http\Controllers\Sinapra\PeminjamanController::class, 'indexAset']);
    Route::post('peminjaman-aset', [App\Http\Controllers\Sinapra\PeminjamanController::class, 'applyAset']);
    Route::get('peminjaman-aset/{peminjaman}', [App\Http\Controllers\Sinapra\PeminjamanController::class, 'showAset']);
    Route::post('peminjaman-aset/{peminjaman}/approve', [App\Http\Controllers\Sinapra\PeminjamanController::class, 'approveAset']);
    Route::post('peminjaman-aset/{peminjaman}/kembalikan', [App\Http\Controllers\Sinapra\PeminjamanController::class, 'kembalikanAset']);

    // Maintenance / Perawatan
    Route::get('maintenance', [App\Http\Controllers\Sinapra\MaintenanceController::class, 'index']);
    Route::post('maintenance', [App\Http\Controllers\Sinapra\MaintenanceController::class, 'store']);
    Route::get('maintenance/{maintenance}', [App\Http\Controllers\Sinapra\MaintenanceController::class, 'show']);
    Route::put('maintenance/{maintenance}', [App\Http\Controllers\Sinapra\MaintenanceController::class, 'update']);
    Route::delete('maintenance/{maintenance}', [App\Http\Controllers\Sinapra\MaintenanceController::class, 'destroy']);

    // Pengajuan Pengadaan Barang
    Route::get('pengadaan', [App\Http\Controllers\Sinapra\PengadaanController::class, 'index']);
    Route::post('pengadaan', [App\Http\Controllers\Sinapra\PengadaanController::class, 'store']);
    Route::get('pengadaan/{pengadaan}', [App\Http\Controllers\Sinapra\PengadaanController::class, 'show']);
    Route::patch('pengadaan/{pengadaan}/status', [App\Http\Controllers\Sinapra\PengadaanController::class, 'updateStatus']);
    Route::delete('pengadaan/{pengadaan}', [App\Http\Controllers\Sinapra\PengadaanController::class, 'destroy']);
});





/*
|--------------------------------------------------------------------------
| Mobile Attendance (Flutter Android) & Python Face Microservice Routes (v1)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    // 1. Publik / Auth Mobile App (Flutter)
    Route::post('/auth/login', [App\Http\Controllers\API\AuthController::class, 'login']);

    // 2. Microservice Python Face Recognition (Port 8001)
    Route::prefix('face')->group(function () {
        Route::get('/health', [App\Http\Controllers\API\FaceRecognitionController::class, 'health']);
        Route::post('/verify', [App\Http\Controllers\API\FaceRecognitionController::class, 'verify']);
        Route::post('/extract', [App\Http\Controllers\API\FaceRecognitionController::class, 'extract']);
        Route::post('/enroll', [App\Http\Controllers\API\FaceRecognitionController::class, 'enroll']);
    });

    // 3. Mobile Authenticated (Passport / Sanctum)
    Route::middleware(['auth:api'])->group(function () {
        // Autentikasi & Profil Karyawan
        Route::get('/auth/profile', [App\Http\Controllers\API\AuthController::class, 'profile']);
        Route::get('/auth/me', [App\Http\Controllers\API\AuthController::class, 'profile']);
        Route::post('/auth/consent', [App\Http\Controllers\API\AuthController::class, 'recordConsent']);
        Route::post('/auth/enroll-face', [App\Http\Controllers\API\AuthController::class, 'enrollFace']);
        Route::post('/auth/reset-face', [App\Http\Controllers\API\AuthController::class, 'resetFace']);
        Route::post('/auth/logout', [App\Http\Controllers\API\AuthController::class, 'logout']);

        // Presensi Mobile
        Route::get('/attendance/today', [App\Http\Controllers\API\AttendanceController::class, 'todayStatus']);
        Route::post('/attendance/clock-in', [App\Http\Controllers\API\AttendanceController::class, 'clockIn']);
        Route::post('/attendance/clock-out', [App\Http\Controllers\API\AttendanceController::class, 'clockOut']);
        Route::post('/attendance/keterangan', [App\Http\Controllers\API\AttendanceController::class, 'keterangan']);
        Route::get('/attendance/history', [App\Http\Controllers\API\AttendanceController::class, 'history']);
        Route::get('/attendance/recap', [App\Http\Controllers\API\AttendanceController::class, 'recap']);
    });

    // 4. Akses Integrasi Sistem Eksternal (API Key)
    Route::middleware('api.key')->prefix('integration')->group(function () {
        Route::get('/attendances', [App\Http\Controllers\API\AttendanceIntegrationController::class, 'index']);
        Route::get('/attendances/recap', [App\Http\Controllers\API\AttendanceIntegrationController::class, 'recap']);
        Route::get('/attendances/{id}', [App\Http\Controllers\API\AttendanceIntegrationController::class, 'show'])->whereNumber('id');
    });
});
