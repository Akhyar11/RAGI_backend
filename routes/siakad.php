<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Siakad\FeederSyncController;
use App\Http\Controllers\Api\Siakad\MahasiswaController;
use App\Http\Controllers\Api\Siakad\AkademikController;
use App\Http\Controllers\Api\Siakad\PerkuliahanController;
use App\Http\Controllers\API\Siakad\ObeController;

/*
|--------------------------------------------------------------------------
| SIAKAD Module API Routes
|--------------------------------------------------------------------------
| Prefix: /api/v1/siakad
*/

// --- Dashboard & Summary ---
Route::get('/dashboard/summary', [AkademikController::class, 'dashboardSummary']);

// --- Neo Feeder Sync & Staging ---
Route::prefix('feeder-sync')->group(function () {
    Route::get('/config', [FeederSyncController::class, 'getConfig']);
    Route::post('/config', [FeederSyncController::class, 'saveConfig']);
    Route::get('/token', [FeederSyncController::class, 'getToken']);
    Route::post('/trigger', [FeederSyncController::class, 'triggerSync']);
    Route::get('/logs', [FeederSyncController::class, 'getLogs']);
    Route::get('/mappings', [FeederSyncController::class, 'getMappings']);
});

// --- Mahasiswa & Konversi CRUD ---
Route::prefix('mahasiswa')->group(function () {
    Route::get('/profil', [MahasiswaController::class, 'getProfil']);
    Route::put('/profil', [MahasiswaController::class, 'updateProfil']);
    Route::post('/profil/sync-feeder', [MahasiswaController::class, 'syncProfilToFeeder']);
    Route::get('/', [MahasiswaController::class, 'index']);
    Route::post('/', [MahasiswaController::class, 'store']);
    Route::post('/generate-nim', [MahasiswaController::class, 'generateNim']);
    Route::post('/generate-missing-nims', [MahasiswaController::class, 'generateMissingNims']);
    Route::post('/sync-from-spmb', [MahasiswaController::class, 'syncFromSpmb']);
    Route::get('/konversi', [MahasiswaController::class, 'listKonversi']);
    Route::post('/konversi', [MahasiswaController::class, 'storeKonversi']);
    Route::patch('/konversi/{id}/status', [MahasiswaController::class, 'updateKonversiStatus']);
    Route::delete('/konversi/{id}', [MahasiswaController::class, 'destroyKonversi']);
    Route::post('/bulk-assign-pa', [MahasiswaController::class, 'bulkAssignPa']);
    Route::get('/{id}', [MahasiswaController::class, 'show']);
    Route::put('/{id}', [MahasiswaController::class, 'update']);
    Route::delete('/{id}', [MahasiswaController::class, 'destroy']);
});

// --- Master Data Akademik CRUD (Fakultas, Prodi, Kurikulum, Matakuliah, Dosen, Tahun Akademik) ---
Route::prefix('akademik')->group(function () {
    Route::get('/tahun-akademik', [AkademikController::class, 'listTahunAkademik']);
    Route::post('/tahun-akademik', [AkademikController::class, 'storeTahunAkademik']);
    Route::patch('/tahun-akademik/{id}/set-active', [AkademikController::class, 'setActiveTahunAkademik']);
    Route::patch('/tahun-akademik/{id}/mode-penilaian', [AkademikController::class, 'updateModePenilaian']);

    Route::get('/fakultas', [AkademikController::class, 'listFakultas']);
    Route::post('/fakultas', [AkademikController::class, 'storeFakultas']);
    Route::put('/fakultas/{id}', [AkademikController::class, 'updateFakultas']);
    Route::delete('/fakultas/{id}', [AkademikController::class, 'destroyFakultas']);

    Route::get('/prodi', [AkademikController::class, 'listProgramStudi']);
    Route::post('/prodi', [AkademikController::class, 'storeProgramStudi']);
    Route::put('/prodi/{id}', [AkademikController::class, 'updateProgramStudi']);
    Route::delete('/prodi/{id}', [AkademikController::class, 'destroyProgramStudi']);

    Route::get('/kurikulum', [AkademikController::class, 'listKurikulum']);
    Route::post('/kurikulum', [AkademikController::class, 'storeKurikulum']);
    Route::put('/kurikulum/{id}', [AkademikController::class, 'updateKurikulum']);
    Route::delete('/kurikulum/{id}', [AkademikController::class, 'destroyKurikulum']);

    Route::get('/matakuliah', [AkademikController::class, 'listMataKuliah']);
    Route::post('/matakuliah', [AkademikController::class, 'storeMataKuliah']);
    Route::put('/matakuliah/{id}', [AkademikController::class, 'updateMataKuliah']);
    Route::delete('/matakuliah/{id}', [AkademikController::class, 'destroyMataKuliah']);

    Route::get('/dosen', [AkademikController::class, 'listDosen']);
    Route::post('/dosen', [AkademikController::class, 'storeDosen']);
    Route::post('/dosen/sync-from-simpeg', [AkademikController::class, 'syncDosenFromSimpeg']);
    Route::put('/dosen/{id}', [AkademikController::class, 'updateDosen']);
    Route::delete('/dosen/{id}', [AkademikController::class, 'destroyDosen']);
});

// --- Perkuliahan (Kelas, KRS, Nilai, Transkrip) ---
Route::prefix('perkuliahan')->group(function () {
    Route::get('/ref/ruangan', [PerkuliahanController::class, 'getRefRuanganSinapra']);
    Route::get('/kelas', [PerkuliahanController::class, 'listKelas']);
    Route::post('/kelas', [PerkuliahanController::class, 'storeKelas']);
    Route::put('/kelas/{id}', [PerkuliahanController::class, 'updateKelas']);
    Route::delete('/kelas/{id}', [PerkuliahanController::class, 'destroyKelas']);

    Route::get('/krs', [PerkuliahanController::class, 'listKrs']);
    Route::get('/krs/active', [PerkuliahanController::class, 'getActiveKrs']);
    Route::get('/krs/available-classes', [PerkuliahanController::class, 'getAvailableClasses']);
    Route::post('/krs/add-class', [PerkuliahanController::class, 'addClassToKrs']);
    Route::delete('/krs/drop-class/{detailId}', [PerkuliahanController::class, 'dropClassFromKrs']);
    Route::post('/krs/submit', [PerkuliahanController::class, 'submitKrs']);
    Route::post('/krs/reopen', [PerkuliahanController::class, 'reopenKrs']);
    Route::post('/krs/bulk-approve', [PerkuliahanController::class, 'bulkApproveKrs']);
    Route::patch('/krs/{id}/approve', [PerkuliahanController::class, 'approveKrs']);

    Route::get('/nilai', [PerkuliahanController::class, 'listNilai']);
    Route::put('/nilai/{id}', [PerkuliahanController::class, 'updateNilai']);
    Route::get('/transkrip', [PerkuliahanController::class, 'getTranskrip']);
    
    // Absensi Mahasiswa
    Route::get('/kelas/{kelasId}/pertemuan', [PerkuliahanController::class, 'listPertemuan']);
    Route::post('/kelas/{kelasId}/pertemuan', [PerkuliahanController::class, 'storePertemuan']);
    Route::get('/pertemuan/{pertemuanId}/absensi', [PerkuliahanController::class, 'listAbsensi']);
    Route::post('/pertemuan/{pertemuanId}/absensi', [PerkuliahanController::class, 'storeAbsensi']);
});

// --- OBE (Outcome-Based Education) Endpoints ---
Route::prefix('obe')->group(function () {
    Route::get('/dashboard', [ObeController::class, 'getObeDashboard']);

    Route::get('/cpl', [ObeController::class, 'getCpl']);
    Route::post('/cpl', [ObeController::class, 'storeCpl']);
    Route::get('/cpmk', [ObeController::class, 'getCpmk']);
    Route::post('/cpmk', [ObeController::class, 'storeCpmk']);

    // --- Profil Lulusan & Bahan Kajian ---
    Route::get('/profil-lulusan', [ObeController::class, 'getProfilLulusan']);
    Route::post('/profil-lulusan', [ObeController::class, 'storeProfilLulusan']);
    Route::delete('/profil-lulusan/{id}', [ObeController::class, 'deleteProfilLulusan']);
    Route::post('/profil-lulusan/cpl', [ObeController::class, 'mapProfilLulusanCpl']);

    Route::get('/bahan-kajian', [ObeController::class, 'getBahanKajian']);
    Route::post('/bahan-kajian', [ObeController::class, 'storeBahanKajian']);
    Route::delete('/bahan-kajian/{id}', [ObeController::class, 'deleteBahanKajian']);
    Route::post('/matakuliah/bahan-kajian', [ObeController::class, 'mapMataKuliahBahanKajian']);

    Route::get('/rps', [ObeController::class, 'listRps']);
    Route::get('/rps/{id}', [ObeController::class, 'showRps']);
    Route::post('/rps', [ObeController::class, 'storeRps']);
    Route::post('/rps/{id}/submit', [ObeController::class, 'submitRps']);
    Route::patch('/rps/{id}/approve', [ObeController::class, 'approveRps']);

    Route::get('/kelas/{kelasId}/komponen', [ObeController::class, 'getKelasKomponen']);
    Route::post('/kelas/{kelasId}/komponen', [ObeController::class, 'storeKelasKomponen']);
    Route::delete('/komponen/{id}', [ObeController::class, 'deleteKelasKomponen']);

    Route::get('/kelas/{kelasId}/nilai', [ObeController::class, 'getKelasNilaiObe']);
    Route::post('/kelas/{kelasId}/nilai', [ObeController::class, 'saveKelasNilaiObe']);
    Route::post('/kelas/{kelasId}/bulk-nilai', [ObeController::class, 'saveBulkNilaiObe']);

    Route::get('/mahasiswa/portofolio', [ObeController::class, 'getMahasiswaPortofolioObe']);
    Route::get('/mahasiswa/{mahasiswaId}/portofolio', [ObeController::class, 'getMahasiswaPortofolioObe']);
});
