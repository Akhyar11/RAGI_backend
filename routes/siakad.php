<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Siakad\FeederSyncController;
use App\Http\Controllers\API\Siakad\MahasiswaController;
use App\Http\Controllers\API\Siakad\AkademikController;
use App\Http\Controllers\API\Siakad\PerkuliahanController;
use App\Http\Controllers\API\Siakad\ObeController;
use App\Http\Controllers\API\Siakad\MahasiswaBeasiswaController;
use App\Http\Controllers\API\Siakad\StatusAkademikController;
use App\Http\Controllers\API\Siakad\KelulusanController;

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
    Route::get('/export-nim', [MahasiswaController::class, 'exportNimData']);
    Route::get('/export-buku-induk', [MahasiswaController::class, 'exportBukuInduk']);
    Route::patch('/{id}/status', [MahasiswaController::class, 'updateStatus']);
    Route::post('/import-nim', [MahasiswaController::class, 'importNimData']);
    Route::post('/sync-from-spmb', [MahasiswaController::class, 'syncFromSpmb']);
    Route::get('/konversi', [MahasiswaController::class, 'listKonversi']);
    Route::post('/konversi', [MahasiswaController::class, 'storeKonversi']);
    Route::patch('/konversi/{id}/status', [MahasiswaController::class, 'updateKonversiStatus']);
    Route::delete('/konversi/{id}', [MahasiswaController::class, 'destroyKonversi']);
    Route::post('/bulk-assign-pa', [MahasiswaController::class, 'bulkAssignPa']);
    Route::post('/bulk-status', [MahasiswaController::class, 'bulkUpdateStatus']);
    Route::post('/auto-distribute-pa', [MahasiswaController::class, 'autoDistributePa']);
    Route::get('/{id}', [MahasiswaController::class, 'show']);
    Route::put('/{id}', [MahasiswaController::class, 'update']);
    Route::delete('/{id}', [MahasiswaController::class, 'destroy']);
});

// --- Penerima Beasiswa Mahasiswa (Kelolaan BAAK) ---
Route::prefix('civitas/beasiswa')->group(function () {
    Route::get('/options', [MahasiswaBeasiswaController::class, 'getBeasiswaOptions']);
    Route::get('/', [MahasiswaBeasiswaController::class, 'index']);
    Route::post('/', [MahasiswaBeasiswaController::class, 'store']);
    Route::get('/{id}', [MahasiswaBeasiswaController::class, 'show']);
    Route::put('/{id}', [MahasiswaBeasiswaController::class, 'update']);
    Route::delete('/{id}', [MahasiswaBeasiswaController::class, 'destroy']);
});

// --- Master Data Akademik CRUD (Fakultas, Prodi, Kurikulum, Matakuliah, Dosen, Tahun Akademik) ---
Route::prefix('akademik')->group(function () {
    Route::get('/tahun-akademik', [AkademikController::class, 'listTahunAkademik']);
    Route::post('/tahun-akademik', [AkademikController::class, 'storeTahunAkademik']);
    Route::put('/tahun-akademik/{id}', [AkademikController::class, 'updateTahunAkademik']);
    Route::patch('/tahun-akademik/{id}/set-active', [AkademikController::class, 'setActiveTahunAkademik']);
    Route::patch('/tahun-akademik/{id}/mode-penilaian', [AkademikController::class, 'updateModePenilaian']);

    // Skala Nilai / Grading Scale CRUD
    Route::get('/skala-nilai', [AkademikController::class, 'listSkalaNilai']);
    Route::post('/skala-nilai', [AkademikController::class, 'storeSkalaNilai']);
    Route::put('/skala-nilai/{id}', [AkademikController::class, 'updateSkalaNilai']);
    Route::delete('/skala-nilai/{id}', [AkademikController::class, 'destroySkalaNilai']);

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
    Route::put('/dosen/{id}', [AkademikController::class, 'updateDosen']);
    Route::delete('/dosen/{id}', [AkademikController::class, 'destroyDosen']);

    Route::get('/prasyarat-mk', [AkademikController::class, 'listPrasyaratMk']);
    Route::post('/prasyarat-mk', [AkademikController::class, 'storePrasyaratMk']);
    Route::delete('/prasyarat-mk/{id}', [AkademikController::class, 'destroyPrasyaratMk']);

    Route::get('/referensi-options', [AkademikController::class, 'listReferensiOptions']);
});

// --- Perkuliahan (Kelas, KRS, Nilai, Transkrip) ---
Route::prefix('perkuliahan')->group(function () {
    Route::get('/ref/ruangan', [PerkuliahanController::class, 'getRefRuanganSinapra']);
    Route::get('/kelas', [PerkuliahanController::class, 'listKelas']);
    Route::post('/kelas', [PerkuliahanController::class, 'storeKelas']);
    Route::get('/kelas/{id}', [PerkuliahanController::class, 'showKelas']);
    Route::put('/kelas/{id}', [PerkuliahanController::class, 'updateKelas']);
    Route::delete('/kelas/{id}', [PerkuliahanController::class, 'destroyKelas']);

    Route::get('/krs', [PerkuliahanController::class, 'listKrs']);
    Route::get('/krs/monitoring', [PerkuliahanController::class, 'monitoringKrsProdi']);
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
    Route::get('/kelas/{kelasId}/rekap-absensi', [StatusAkademikController::class, 'rekapAbsensi']);
    Route::get('/khs', [StatusAkademikController::class, 'listKhs']);
    Route::patch('/khs/{id}/lock', [StatusAkademikController::class, 'lockKhs']);
});

// --- Status Akademik & Cuti ---
Route::prefix('status')->group(function () {
    Route::get('/cuti', [StatusAkademikController::class, 'listCuti']);
    Route::post('/cuti', [StatusAkademikController::class, 'storeCuti']);
    Route::patch('/cuti/{id}/proses', [StatusAkademikController::class, 'prosesCuti']);
    Route::get('/log', [StatusAkademikController::class, 'listStatusLog']);
});

// --- Kelulusan / Yudisium ---
Route::prefix('kelulusan')->group(function () {
    Route::get('/', [KelulusanController::class, 'index']);
    Route::get('/sensing', [KelulusanController::class, 'sensing']);
    Route::get('/cek-syarat/{mahasiswaId}', [KelulusanController::class, 'cekSyarat']);
    Route::post('/', [KelulusanController::class, 'store']);
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
    Route::post('/kelas/{kelasId}/sync-komponen-obe', [ObeController::class, 'syncKelasKomponenFromObe']);
    Route::delete('/komponen/{id}', [ObeController::class, 'deleteKelasKomponen']);

    Route::get('/kelas/{kelasId}/nilai', [ObeController::class, 'getKelasNilaiObe']);
    Route::post('/kelas/{kelasId}/nilai', [ObeController::class, 'saveKelasNilaiObe']);
    Route::post('/kelas/{kelasId}/bulk-nilai', [ObeController::class, 'saveBulkNilaiObe']);

    Route::get('/matrix-cpl-mk', [ObeController::class, 'getMatrixCplMk']);
    Route::post('/matrix-cpl-mk/toggle', [ObeController::class, 'toggleMatrixCplMk']);

    // Pemantauan & Audit Pemetaan OBE
    Route::get('/audit-pemetaan', [ObeController::class, 'getAuditPemetaan']);

    // Pemantauan Ketertiban Nilai Dosen (Kinerja SIMPEG)
    Route::get('/dosen-kepatuhan-nilai', [ObeController::class, 'getDosenKepatuhanNilai']);

    Route::get('/mahasiswa/portofolio', [ObeController::class, 'getMahasiswaPortofolioObe']);
    Route::get('/mahasiswa/{mahasiswaId}/portofolio', [ObeController::class, 'getMahasiswaPortofolioObe']);
});
