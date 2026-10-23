<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Siakad\FeederSyncController;
use App\Http\Controllers\API\Siakad\MahasiswaController;
use App\Http\Controllers\API\Siakad\AkademikController;
use App\Http\Controllers\API\Siakad\PerkuliahanController;
use App\Http\Controllers\API\Siakad\ObeController;
use App\Http\Controllers\API\Siakad\PaController;
use App\Http\Controllers\API\Siakad\MahasiswaBeasiswaController;
use App\Http\Controllers\API\Siakad\StatusAkademikController;
use App\Http\Controllers\API\Siakad\KelulusanController;
use App\Http\Controllers\API\Siakad\PaketKrsController;
use App\Http\Controllers\API\Siakad\KartuUjianController;
use App\Http\Controllers\API\Siakad\DokumenController;

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
    Route::get('/konversi/{id}', [MahasiswaController::class, 'showKonversi']);
    Route::post('/konversi', [MahasiswaController::class, 'storeKonversi']);
    Route::put('/konversi/{id}', [MahasiswaController::class, 'updateKonversi']);
    Route::patch('/konversi/{id}/status', [MahasiswaController::class, 'updateKonversiStatus']);
    Route::post('/konversi/bulk-status', [MahasiswaController::class, 'bulkUpdateKonversiStatus']);
    Route::delete('/konversi/{id}', [MahasiswaController::class, 'destroyKonversi']);
    Route::post('/bulk-assign-pa', [MahasiswaController::class, 'bulkAssignPa']);
    Route::post('/assign-pa-kelas', [MahasiswaController::class, 'assignPaKelas']);
    Route::post('/bulk-status', [MahasiswaController::class, 'bulkUpdateStatus']);
    Route::post('/auto-distribute-pa', [MahasiswaController::class, 'autoDistributePa']);
    Route::get('/{id}', [MahasiswaController::class, 'show']);
    Route::put('/{id}', [MahasiswaController::class, 'update']);
    Route::delete('/{id}', [MahasiswaController::class, 'destroy']);
});

// --- Pindah Program Studi (pemilik mutasi: SIAKAD; SIKEU mendengar via event) ---
Route::prefix('pindah-prodi')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\Siakad\PindahProdiController::class, 'index']);
    Route::get('/dampak', [\App\Http\Controllers\API\Siakad\PindahProdiController::class, 'dampak']);
    Route::post('/', [\App\Http\Controllers\API\Siakad\PindahProdiController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\API\Siakad\PindahProdiController::class, 'show'])->whereNumber('id');
    Route::post('/{id}/cek-keuangan', [\App\Http\Controllers\API\Siakad\PindahProdiController::class, 'cekKeuangan'])->whereNumber('id');
    Route::post('/{id}/approve', [\App\Http\Controllers\API\Siakad\PindahProdiController::class, 'approve'])->whereNumber('id');
    Route::post('/{id}/reject', [\App\Http\Controllers\API\Siakad\PindahProdiController::class, 'reject'])->whereNumber('id');
    Route::post('/{id}/apply', [\App\Http\Controllers\API\Siakad\PindahProdiController::class, 'apply'])->whereNumber('id');
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

    // Admin OBE Homebase Prodi CRUD & Role Menu Plotting
    Route::get('/admin-prodi', [\App\Http\Controllers\API\Siakad\AdminProdiController::class, 'index']);
    Route::post('/admin-prodi', [\App\Http\Controllers\API\Siakad\AdminProdiController::class, 'store']);
    Route::get('/admin-prodi/{id}', [\App\Http\Controllers\API\Siakad\AdminProdiController::class, 'show']);
    Route::put('/admin-prodi/{id}', [\App\Http\Controllers\API\Siakad\AdminProdiController::class, 'update']);
    Route::delete('/admin-prodi/{id}', [\App\Http\Controllers\API\Siakad\AdminProdiController::class, 'destroy']);
    Route::post('/admin-prodi/{id}/impersonate', [\App\Http\Controllers\API\Siakad\AdminProdiController::class, 'impersonate']);
    Route::get('/admin-obe-roles', [\App\Http\Controllers\API\Siakad\AdminProdiController::class, 'getAdminObeEligibleRoles']);
    Route::get('/admin-obe-role-menus/{roleId}', [\App\Http\Controllers\API\Siakad\AdminProdiController::class, 'getAdminObeRoleMenus']);
    Route::post('/admin-obe-role-menus/{roleId}', [\App\Http\Controllers\API\Siakad\AdminProdiController::class, 'assignAdminObeRoleMenus']);

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
    Route::post('/krs/ambil-paket', [PaketKrsController::class, 'ambilPaket']);
    Route::get('/krs/paket-saya', [PaketKrsController::class, 'paketSaya']);
    Route::get('/krs/hakakses', [PaketKrsController::class, 'listHakakses']);
    Route::post('/krs/hakakses', [PaketKrsController::class, 'setHakakses']);
    Route::post('/krs/hakakses-rombel', [PaketKrsController::class, 'setHakaksesRombel']);
    Route::delete('/krs/drop-class/{detailId}', [PerkuliahanController::class, 'dropClassFromKrs']);
    Route::post('/krs/submit', [PerkuliahanController::class, 'submitKrs']);
    Route::post('/krs/reopen', [PerkuliahanController::class, 'reopenKrs']);
    Route::post('/krs/bulk-approve', [PerkuliahanController::class, 'bulkApproveKrs']);
    Route::patch('/krs/{id}/approve', [PerkuliahanController::class, 'approveKrs']);

    Route::get('/nilai', [PerkuliahanController::class, 'listNilai']);
    Route::put('/nilai/{id}', [PerkuliahanController::class, 'updateNilai']);
    Route::get('/transkrip', [PerkuliahanController::class, 'getTranskrip']);

    // Paket MK per rombel (BAAK/Kaprodi menetapkan MK yang disediakan per rombel)
    Route::get('/paket-mk/riwayat-rombel', [PaketKrsController::class, 'getMataKuliahByRombelHistory']);
    Route::get('/paket-mk', [PaketKrsController::class, 'index']);
    Route::post('/paket-mk', [PaketKrsController::class, 'store']);
    Route::post('/paket-mk/bulk-sync', [PaketKrsController::class, 'bulkSync']);
    Route::get('/paket-mk/{id}', [PaketKrsController::class, 'show']);
    Route::put('/paket-mk/{id}', [PaketKrsController::class, 'update']);
    Route::delete('/paket-mk/{id}', [PaketKrsController::class, 'destroy']);
    
    // Absensi Mahasiswa
    Route::get('/kelas/{kelasId}/pertemuan', [PerkuliahanController::class, 'listPertemuan']);
    Route::post('/kelas/{kelasId}/pertemuan', [PerkuliahanController::class, 'storePertemuan']);
    Route::get('/pertemuan/{pertemuanId}/absensi', [PerkuliahanController::class, 'listAbsensi']);
    Route::post('/pertemuan/{pertemuanId}/absensi', [PerkuliahanController::class, 'storeAbsensi']);
    Route::get('/kelas/{kelasId}/rekap-absensi', [StatusAkademikController::class, 'rekapAbsensi']);
    Route::get('/khs', [StatusAkademikController::class, 'listKhs']);
    Route::post('/khs/bulk-lock', [StatusAkademikController::class, 'bulkLockKhs']);
    Route::patch('/khs/{id}/lock', [StatusAkademikController::class, 'lockKhs']);

    // Dokumen resmi KHS/Transkrip + QR validasi publik
    Route::post('/dokumen/terbitkan', [DokumenController::class, 'terbitkan']);
    Route::get('/dokumen', [DokumenController::class, 'index']);

    // Kartu ujian UTS/UAS (syarat lunas SIKEU + QR validasi publik)
    Route::get('/kartu-ujian', [KartuUjianController::class, 'cetak']);
    Route::get('/kartu-ujian/monitoring', [KartuUjianController::class, 'monitoring']);
    Route::patch('/kartu-ujian/{id}/nonaktifkan', [KartuUjianController::class, 'nonaktifkan']);
});

// --- Bimbingan PA (catatan, rekap, laporan aktivitas SIMPA) ---
Route::prefix('bimbingan')->group(function () {
    Route::get('/my-pa', [PaController::class, 'myPa']);
    Route::get('/rekap', [PaController::class, 'rekap']);
    Route::get('/advisees', [PaController::class, 'advisees']);
    Route::get('/kehadiran', [PaController::class, 'kehadiran']);
    Route::get('/prestasi', [PaController::class, 'prestasi']);
    Route::get('/risiko', [PaController::class, 'risiko']);
    Route::get('/alerts', [PaController::class, 'alerts']);
    // Fase 2 — Jadwal & Konsultasi
    Route::get('/jadwal', [PaController::class, 'listJadwal']);
    Route::post('/jadwal', [PaController::class, 'storeJadwal']);
    Route::put('/jadwal/{id}', [PaController::class, 'updateJadwal']);
    Route::delete('/jadwal/{id}', [PaController::class, 'destroyJadwal']);
    Route::get('/booking', [PaController::class, 'listBooking']);
    Route::patch('/booking/{id}/proses', [PaController::class, 'prosesBooking']);
    Route::get('/my-jadwal', [PaController::class, 'myJadwalPa']);
    Route::post('/jadwal/{jadwalId}/ajukan', [PaController::class, 'ajukanBooking']);
    // Fase 2 — Status PKL/PL/Magang
    Route::get('/pkl', [PaController::class, 'listPkl']);
    Route::post('/pkl', [PaController::class, 'storePkl']);
    Route::put('/pkl/{id}', [PaController::class, 'updatePkl']);
    Route::delete('/pkl/{id}', [PaController::class, 'destroyPkl']);
    // Fase 2 — Insiden (kekerasan/pelecehan) + konseling + eskalasi
    Route::get('/insiden', [PaController::class, 'listInsiden']);
    Route::post('/insiden', [PaController::class, 'storeInsiden']);
    Route::patch('/insiden/{id}/eskalasi', [PaController::class, 'eskalasiInsiden']);
    Route::patch('/insiden/{id}/konseling', [PaController::class, 'rujukKonseling']);
    Route::put('/insiden/{id}', [PaController::class, 'updateInsiden']);
    // Fase 2 — Laporan monitoring bulanan
    Route::get('/laporan-bulanan', [PaController::class, 'laporanBulanan']);
    Route::get('/catatan', [PaController::class, 'listCatatan']);
    Route::post('/catatan', [PaController::class, 'storeCatatan']);
    Route::put('/catatan/{id}', [PaController::class, 'updateCatatan']);
    Route::delete('/catatan/{id}', [PaController::class, 'destroyCatatan']);
    Route::get('/laporan', [PaController::class, 'getLaporan']);
    Route::post('/laporan', [PaController::class, 'storeLaporan']);
    // SIMPA Indonusa Aktivitas Bimbingan Per Kelas
    Route::get('/aktivitas', [PaController::class, 'getLaporanAktivitas']);
    Route::get('/aktivitas/komposisi', [PaController::class, 'hitungKomposisiKelas']);
    Route::post('/aktivitas', [PaController::class, 'storeLaporanAktivitas']);
    Route::delete('/aktivitas/{id}', [PaController::class, 'destroyLaporanAktivitas']);
    Route::get('/aktivitas/cetak', [PaController::class, 'cetakLaporanPdf']);
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
    Route::get('/settings', [ObeController::class, 'getObeSettings']);
    Route::post('/settings', [ObeController::class, 'updateObeSettings']);

    Route::get('/cpl', [ObeController::class, 'getCpl']);
    Route::post('/cpl', [ObeController::class, 'storeCpl']);
    Route::put('/cpl/{id}', [ObeController::class, 'updateCpl']);
    Route::delete('/cpl/{id}', [ObeController::class, 'destroyCpl']);
    // Rumusan CPMK Program Studi (CPMK-PS): terpisah dari CPMK per Mata Kuliah.
    Route::get('/cpmk-prodi', [ObeController::class, 'getCpmkProdi']);
    Route::post('/cpmk-prodi', [ObeController::class, 'storeCpmkProdi']);
    Route::put('/cpmk-prodi/{id}', [ObeController::class, 'updateCpmkProdi']);
    Route::delete('/cpmk-prodi/{id}', [ObeController::class, 'destroyCpmkProdi']);

    // Pemetaan CPL-CPMK-MK (Distribusi Rumusan CPMK Prodi ke Mata Kuliah)
    Route::get('/pemetaan-cpl-cpmk-mk', [ObeController::class, 'getPemetaanCplCpmkMk']);
    Route::post('/pemetaan-cpl-cpmk-mk/sync', [ObeController::class, 'syncCpmkProdiMataKuliah']);

    Route::get('/cpmk', [ObeController::class, 'getCpmk']);
    Route::post('/cpmk', [ObeController::class, 'storeCpmk']);
    Route::get('/sub-cpmk', [ObeController::class, 'getSubCpmk']);
    Route::post('/sub-cpmk', [ObeController::class, 'storeSubCpmk']);
    Route::delete('/sub-cpmk/{id}', [ObeController::class, 'deleteSubCpmk']);

    // --- Master OBE Tambahan (Rumpun MK, Jenis CPL, Rubrik, Distribusi MK, Master Kelas) ---
    Route::get('/master-kelas', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'listMasterKelas']);
    Route::post('/master-kelas', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'storeMasterKelas']);
    Route::put('/master-kelas/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'updateMasterKelas']);
    Route::delete('/master-kelas/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'destroyMasterKelas']);
    Route::get('/master-kelas/mahasiswa-pemetaan', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'listMahasiswaForPemetaan']);
    Route::post('/master-kelas/assign-mahasiswa', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'assignMahasiswaToKelas']);

    Route::get('/rumpun-mk', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'listRumpunMk']);
    Route::post('/rumpun-mk', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'storeRumpunMk']);
    Route::put('/rumpun-mk/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'updateRumpunMk']);
    Route::delete('/rumpun-mk/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'destroyRumpunMk']);

    Route::get('/jenis-cpl', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'listJenisCpl']);
    Route::post('/jenis-cpl', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'storeJenisCpl']);
    Route::put('/jenis-cpl/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'updateJenisCpl']);
    Route::delete('/jenis-cpl/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'destroyJenisCpl']);

    Route::get('/profesi-karir', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'listProfesiKarir']);
    Route::post('/profesi-karir', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'storeProfesiKarir']);
    Route::put('/profesi-karir/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'updateProfesiKarir']);
    Route::delete('/profesi-karir/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'destroyProfesiKarir']);

    Route::get('/rubrik', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'listRubrik']);
    Route::get('/rubrik/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'showRubrik']);
    Route::post('/rubrik', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'storeRubrik']);
    Route::put('/rubrik/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'updateRubrik']);
    Route::delete('/rubrik/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'destroyRubrik']);

    Route::get('/distribusi-mengajar', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'listDistribusiMengajar']);
    Route::post('/distribusi-mengajar', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'storeDistribusiMengajar']);
    Route::put('/distribusi-mengajar/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'updateDistribusiMengajar']);
    Route::delete('/distribusi-mengajar/{id}', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'destroyDistribusiMengajar']);
    Route::get('/distribusi-matakuliah', [\App\Http\Controllers\API\Siakad\ObeMasterController::class, 'getDistribusiMataKuliah']);

    // --- Profil Lulusan & Bahan Kajian ---
    Route::get('/profil-lulusan', [ObeController::class, 'getProfilLulusan']);
    Route::post('/profil-lulusan', [ObeController::class, 'storeProfilLulusan']);
    Route::delete('/profil-lulusan/{id}', [ObeController::class, 'deleteProfilLulusan']);
    Route::post('/profil-lulusan/cpl', [ObeController::class, 'mapProfilLulusanCpl']);

    Route::get('/bahan-kajian', [ObeController::class, 'getBahanKajian']);
    Route::post('/bahan-kajian', [ObeController::class, 'storeBahanKajian']);
    Route::put('/bahan-kajian/{id}', [ObeController::class, 'updateBahanKajian']);
    Route::delete('/bahan-kajian/{id}', [ObeController::class, 'deleteBahanKajian']);
    Route::get('/bahan-kajian/matrix/cpl', [ObeController::class, 'getMatrixCplBahanKajian']);
    Route::post('/cpl/bahan-kajian', [ObeController::class, 'syncCplBahanKajian']);
    Route::get('/bahan-kajian/matrix/mata-kuliah', [ObeController::class, 'getMatrixBahanKajianMataKuliah']);
    Route::post('/bahan-kajian/mata-kuliah', [ObeController::class, 'syncBahanKajianMataKuliah']);
    Route::post('/matakuliah/bahan-kajian', [ObeController::class, 'mapMataKuliahBahanKajian']);

    // Pemetaan CPL-MK: hanya sel yang sudah punya jalur CPL -> BK -> MK yang boleh dicentang.
    Route::get('/matrix/cpl-mata-kuliah', [ObeController::class, 'getMatrixCplMataKuliah']);
    Route::post('/cpl/mata-kuliah', [ObeController::class, 'toggleMatrixCplMataKuliah']);

    // Pemetaan CPL-BK-MK: laporan read-only hasil komposisi CPL -> BK -> MK.
    Route::get('/matrix/cpl-bahan-kajian-mata-kuliah', [ObeController::class, 'getMatrixCplBahanKajianMataKuliah']);

    // Referensi RPS (Bentuk, Metode, Kriteria, Komponen)
    Route::get('/rps-referensi', [ObeController::class, 'getRpsReferensi']);
    Route::post('/rps-referensi', [ObeController::class, 'storeRpsReferensi']);
    Route::put('/rps-referensi/{id}', [ObeController::class, 'updateRpsReferensi']);
    Route::delete('/rps-referensi/{id}', [ObeController::class, 'destroyRpsReferensi']);

    Route::get('/rps', [ObeController::class, 'listRps']);
    Route::get('/rps/{id}', [ObeController::class, 'showRps']);
    Route::post('/rps', [ObeController::class, 'storeRps']);
    Route::delete('/rps/{id}', [ObeController::class, 'destroyRps']);
    Route::get('/rps/{id}/sesi', [ObeController::class, 'listRpsSesi']);
    Route::post('/rps/{id}/sesi', [ObeController::class, 'storeRpsSesi']);
    Route::delete('/rps-sesi/{sesiId}', [ObeController::class, 'deleteRpsSesi']);
    Route::patch('/rps/{id}/toggle-dosen-edit', [ObeController::class, 'toggleDosenBisaEditRps']);
    Route::post('/rps/{id}/duplicate', [ObeController::class, 'duplicateRps']);
    Route::get('/soal', [ObeController::class, 'listSoal']);
    Route::post('/soal', [ObeController::class, 'storeSoal']);
    Route::get('/soal/import/template', [ObeController::class, 'downloadTemplateSoal']);
    Route::post('/soal/import', [ObeController::class, 'importSoal']);
    Route::post('/soal/bulk-delete', [ObeController::class, 'bulkDeleteSoal']);
    Route::post('/soal/bulk-move', [ObeController::class, 'bulkMoveSoal']);
    Route::get('/soal/{id}', [ObeController::class, 'showSoal']);
    Route::put('/soal/{id}', [ObeController::class, 'updateSoal']);
    Route::delete('/soal/{id}', [ObeController::class, 'deleteSoal']);
    Route::get('/soal-kategori', [ObeController::class, 'listKategoriSoal']);
    Route::post('/soal-kategori', [ObeController::class, 'storeKategoriSoal']);
    Route::delete('/soal-kategori/{id}', [ObeController::class, 'deleteKategoriSoal']);
    Route::post('/soal/{soalId}/opsi', [ObeController::class, 'storeOpsiSoal']);
    Route::delete('/soal/{soalId}/opsi/{opsiId}', [ObeController::class, 'deleteOpsiSoal']);
    Route::get('/kelas/{kelasId}/rekap-xlsx', [ObeController::class, 'rekapKelasXlsx']);
    Route::post('/rps/{id}/submit', [ObeController::class, 'submitRps']);
    Route::patch('/rps/{id}/approve', [ObeController::class, 'approveRps']);

    Route::get('/kelas/{kelasId}/komponen', [ObeController::class, 'getKelasKomponen']);
    Route::post('/kelas/{kelasId}/komponen', [ObeController::class, 'storeKelasKomponen']);
    Route::post('/kelas/{kelasId}/sync-komponen-obe', [ObeController::class, 'syncKelasKomponenFromObe']);
    Route::delete('/komponen/{id}', [ObeController::class, 'deleteKelasKomponen']);

    Route::get('/kelas/{kelasId}/nilai', [ObeController::class, 'getKelasNilaiObe']);
    Route::post('/kelas/{kelasId}/nilai', [ObeController::class, 'saveKelasNilaiObe']);
    Route::post('/kelas/{kelasId}/bulk-nilai', [ObeController::class, 'saveBulkNilaiObe']);

    // Pemetaan CPL-MK kini dilayani oleh /matrix/cpl-mata-kuliah (menu "Mata Kuliah & SKS").

    // Pemantauan & Audit Pemetaan OBE
    Route::get('/audit-pemetaan', [ObeController::class, 'getAuditPemetaan']);
    Route::get('/grafik-cpl', [ObeController::class, 'getGrafikCpl']);
    Route::get('/grafik-cpmk', [ObeController::class, 'getGrafikCpmk']);

    // Pemantauan Ketertiban Nilai Dosen (Kinerja SIMPEG)
    Route::get('/dosen-kepatuhan-nilai', [ObeController::class, 'getDosenKepatuhanNilai']);

    Route::get('/mahasiswa/portofolio', [ObeController::class, 'getMahasiswaPortofolioObe']);
    Route::get('/mahasiswa/{mahasiswaId}/portofolio', [ObeController::class, 'getMahasiswaPortofolioObe']);
});

// --- LMS dipisah ke modul standalone: lihat routes/lms.php (prefix /api/v1/lms) ---
// Grup lama Route::prefix('lms') dihapus dari file ini agar menu & API SIAKAD tidak menumpuk.
