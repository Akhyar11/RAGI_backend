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

/*
|--------------------------------------------------------------------------
| SPMB (Sistem Penerimaan Mahasiswa Baru) Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->prefix('spmb')->group(function () {
    // Master SPMB (Admin Only)
    Route::get('jalur-masuk', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'getJalurMasuk']);
    Route::post('jalur-masuk', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'storeJalurMasuk']);
    Route::put('jalur-masuk/{id}', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'updateJalurMasuk']);
    Route::delete('jalur-masuk/{id}', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'destroyJalurMasuk']);
    
    Route::get('gelombang', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'getGelombang']);
    Route::post('gelombang', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'storeGelombang']);
    Route::put('gelombang/{id}', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'updateGelombang']);
    Route::delete('gelombang/{id}', [App\Http\Controllers\API\Spmb\MasterSpmbController::class, 'destroyGelombang']);

    // Pendaftaran (Calon Mahasiswa)
    Route::get('pendaftaran/me', [App\Http\Controllers\API\Spmb\CalonMahasiswaController::class, 'myPendaftaran']);
    Route::post('pendaftaran/biodata', [App\Http\Controllers\API\Spmb\CalonMahasiswaController::class, 'storeBiodata']);
    Route::post('pendaftaran/finalize', [App\Http\Controllers\API\Spmb\CalonMahasiswaController::class, 'finalize']);

    // Seleksi & Verifikasi (Admin SPMB)
    Route::get('pendaftar', [App\Http\Controllers\API\Spmb\AdminSeleksiController::class, 'getPendaftar']);
    Route::post('pendaftar/{id}/verifikasi', [App\Http\Controllers\API\Spmb\AdminSeleksiController::class, 'verifikasi']);
    Route::post('pendaftar/{id}/kelulusan', [App\Http\Controllers\API\Spmb\AdminSeleksiController::class, 'tetapkanKelulusan']);
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
    Route::get('periode', [App\Http\Controllers\Sippm\MasterSippmController::class, 'indexPeriode']);
    Route::post('periode', [App\Http\Controllers\Sippm\MasterSippmController::class, 'storePeriode']);
    Route::apiResource('rubrik', App\Http\Controllers\Sippm\RubrikIndikatorController::class);

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
    Route::post('kontrak/{id}/laporan', [App\Http\Controllers\Sippm\KontrakMonevController::class, 'submitLaporan']);

    // Portofolio Luaran (Publikasi & HKI)
    Route::get('luaran/publikasi', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'indexPublikasi']);
    Route::post('luaran/publikasi', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'storePublikasi']);
    Route::post('luaran/publikasi/{id}/verify', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'verifyPublikasi']);

    Route::get('luaran/hki', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'indexHki']);
    Route::post('luaran/hki', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'storeHki']);
    Route::post('luaran/hki/{id}/verify', [App\Http\Controllers\Sippm\LuaranSippmController::class, 'verifyHki']);

    // Cross-Module Integration Endpoints (UPM IKU & SIKEU Callback)
    Route::get('integration/upm-iku-metrics', [App\Http\Controllers\Sippm\MasterSippmController::class, 'getUpmMetrics']);
    Route::post('integration/sikeu-disbursement-callback/{id}', [App\Http\Controllers\Sippm\MasterSippmController::class, 'processDisbursementCallback']);
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

    // Master Tarif UKT per Angkatan & Jalur Kelas
    Route::get('master/tarif-ukt', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexTarif']);
    Route::post('master/tarif-ukt', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'storeTarif']);
    Route::put('master/tarif-ukt/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateTarif']);
    Route::delete('master/tarif-ukt/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'destroyTarif']);

    // Master Jalur Kelas & Tipe Mahasiswa
    Route::get('master/jalur-kelas', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexJalurKelas']);
    Route::post('master/jalur-kelas', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'storeJalurKelas']);
    Route::put('master/jalur-kelas/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateJalurKelas']);
    Route::delete('master/jalur-kelas/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'destroyJalurKelas']);

    // Master Jenis Biaya Pendidikan
    Route::get('master/jenis-biaya', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexJenisBiaya']);
    Route::post('master/jenis-biaya', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'storeJenisBiaya']);
    Route::put('master/jenis-biaya/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateJenisBiaya']);

    // Master & Mapping Beasiswa Mahasiswa
    Route::get('master/beasiswa', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexBeasiswa']);
    Route::post('master/beasiswa', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'storeBeasiswa']);
    Route::put('master/beasiswa/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateBeasiswa']);
    Route::get('master/mahasiswa-beasiswa', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexMahasiswaBeasiswa']);
    Route::post('master/mahasiswa-beasiswa', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'assignMahasiswaBeasiswa']);

    // Penetapan & Integrasi Tipe Tagihan Mahasiswa (SPMB / SIAKAD / Admin Change)
    Route::get('master/student-billing-categories', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'getStudentBillingCategories']);
    Route::get('master/student-billing-types', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexStudentBillingTypes']);
    Route::post('master/assign-student-billing-type', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'assignStudentBillingType']);
    Route::put('master/update-student-billing-type/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateStudentBillingType']);

    // Pencarian Mahasiswa untuk Tagihan & Dispensasi
    Route::get('mahasiswa-search', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'searchMahasiswa']);

    // Portal Tagihan & Invoice Mahasiswa Mandiri
    Route::get('mahasiswa/tagihan', [App\Http\Controllers\Sikeu\MahasiswaTagihanController::class, 'myBills']);
    Route::get('mahasiswa/invoice/{id}', [App\Http\Controllers\Sikeu\MahasiswaTagihanController::class, 'generateInvoice']);

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

    // Master Tarif SPMB (Jalur & Gelombang)
    Route::get('master/tarif-spmb', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'indexTarifSpmb']);
    Route::post('master/tarif-spmb', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'storeTarifSpmb']);
    Route::put('master/tarif-spmb/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'updateTarifSpmb']);
    Route::delete('master/tarif-spmb/{id}', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'destroyTarifSpmb']);

    // Endpoint Integrasi SPMB (Get Tarif Real-Time)
    Route::get('spmb/tarif', [App\Http\Controllers\Sikeu\SikeuMasterController::class, 'getTarifSpmb']);

    // SPMB Payment Callback / Webhook Integration
    Route::post('callback/spmb/{calonMahasiswaId}', [App\Http\Controllers\Sikeu\SpmBSikeuCallbackController::class, 'handleSpmbPaymentCallback']);
});



