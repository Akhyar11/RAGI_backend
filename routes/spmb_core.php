<?php

use App\Http\Controllers\API\Spmb\CalonMahasiswaController;
use App\Http\Controllers\API\Spmb\DaftarUlangController;
use App\Http\Controllers\API\Spmb\LaporanSpmbController;
use App\Http\Controllers\API\Spmb\MasterSpmbController;
use App\Http\Controllers\API\Spmb\PendaftaranController;
use App\Http\Controllers\API\Spmb\SpmbKuotaProdiController;
use App\Http\Controllers\API\Spmb\SpmbSekolahMitraController;


/*
|--------------------------------------------------------------------------
| SPMB Core Routes — Alur Inti: Master -> Pendaftaran -> Verifikasi -> Daftar Ulang -> Konversi NIM
|--------------------------------------------------------------------------
| File ini dimuat oleh bootstrap/app.php dengan middleware auth:api dan prefix spmb.
| Agent pengembang: APPEND route baru di akhir file ini (jangan ubah route yang sudah ada).
|*/

// Master Data SPMB
Route::get('master-tipe-jalur', [MasterSpmbController::class, 'getMasterTipeJalur']);
Route::get('jalur', [MasterSpmbController::class, 'getJalurMasuk']);
Route::get('jalur/{id}', [MasterSpmbController::class, 'showJalurMasuk']);
Route::post('jalur', [MasterSpmbController::class, 'storeJalurMasuk']);
Route::put('jalur/{id}', [MasterSpmbController::class, 'updateJalurMasuk']);
Route::delete('jalur/{id}', [MasterSpmbController::class, 'destroyJalurMasuk']);

Route::get('sekolah-mitra', [SpmbSekolahMitraController::class, 'index']);
Route::post('sekolah-mitra', [SpmbSekolahMitraController::class, 'store']);

Route::get('gelombang', [MasterSpmbController::class, 'getGelombang']);
Route::get('gelombang/{id}', [MasterSpmbController::class, 'showGelombang']);
Route::post('gelombang', [MasterSpmbController::class, 'storeGelombang']);
Route::put('gelombang/{id}', [MasterSpmbController::class, 'updateGelombang']);
Route::delete('gelombang/{id}', [MasterSpmbController::class, 'destroyGelombang']);



// Pendaftaran SPMB
Route::get('pendaftaran', [PendaftaranController::class, 'index']);

// Calon Mahasiswa specific routes must be before {id} param route
Route::get('pendaftaran/me', [CalonMahasiswaController::class, 'myPendaftaran']);
Route::post('pendaftaran/biodata', [CalonMahasiswaController::class, 'storeBiodata']);
Route::post('pendaftaran/berkas', [CalonMahasiswaController::class, 'uploadBerkas']);
Route::post('pendaftaran/finalize', [CalonMahasiswaController::class, 'finalize']);
Route::post('pendaftaran/reissue-va', [CalonMahasiswaController::class, 'reissueVa']);
Route::post('pendaftaran/reset', [CalonMahasiswaController::class, 'resetPendaftaran']);

Route::get('pendaftaran/{id}', [PendaftaranController::class, 'show']);
Route::post('pendaftaran/{id}/status', [PendaftaranController::class, 'updateStatus']);
Route::post('pendaftaran/berkas/{id}/verify', [PendaftaranController::class, 'verifyBerkas']);

// Seleksi & Verifikasi (Admin SPMB)
// (Dikonsolidasikan ke PendaftaranController — endpoint /pendaftar lama dihapus)

// Kuota Program Studi
Route::apiResource('kuota-prodi', SpmbKuotaProdiController::class);

// Daftar Ulang
Route::post('daftar-ulang/{pendaftaran_id}/generate-tagihan', [DaftarUlangController::class, 'generateTagihan']);
Route::post('daftar-ulang/{pendaftaran_id}/konfirmasi', [DaftarUlangController::class, 'konfirmasi']);

// Laporan & Export Data
Route::get('laporan/statistik', [LaporanSpmbController::class, 'statistik']);
Route::get('laporan/export-csv', [LaporanSpmbController::class, 'exportCsv']);

// Referral (Kode Rujukan Mahasiswa Baru) — data milik pengguna yang login.
Route::get('referral/saya', [\App\Http\Controllers\API\Spmb\ReferralController::class, 'me']);
Route::get('referral/saya/usages', [\App\Http\Controllers\API\Spmb\ReferralController::class, 'usages']);
Route::post('referral/payout', [\App\Http\Controllers\API\Spmb\ReferralController::class, 'payout']);
Route::get('referral/payout/{payout}/download', [\App\Http\Controllers\API\Spmb\ReferralController::class, 'downloadPayout'])->whereNumber('payout');

// Laporan Referral (Admin/Panitia SPMB) — konsisten dengan modul laporan lain.
Route::middleware('can:spmb.laporan.read')->group(function () {
    Route::get('laporan/referral', [\App\Http\Controllers\API\Spmb\ReferralController::class, 'report']);
    Route::get('laporan/referral-summary', [\App\Http\Controllers\API\Spmb\ReferralController::class, 'summary']);
});

// Master Biaya SPMB (Dinamis)
Route::middleware('can:spmb.manage')->group(function () {
    Route::get('master/komponen-biaya', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'getKomponen']);
    Route::get('master/komponen-biaya/{id}', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'showKomponen']);
    Route::post('master/komponen-biaya', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'storeKomponen']);
    Route::put('master/komponen-biaya/{id}', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'updateKomponen']);
    Route::delete('master/komponen-biaya/{id}', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'destroyKomponen']);
    Route::post('master/komponen-biaya/{id}/restore', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'restoreKomponen']);

    Route::get('master/biaya', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'index']);
    Route::get('master/biaya/{id}', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'show']);
    Route::post('master/biaya', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'store']);
    Route::put('master/biaya/{id}', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'update']);
    Route::delete('master/biaya/{id}', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'destroy']);
    Route::post('master/biaya/{id}/restore', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'restore']);
    Route::post('master/biaya/batch', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'batchUpdate']);
    Route::post('master/biaya/copy-from-gelombang', [\App\Http\Controllers\API\Spmb\MasterBiayaSpmbController::class, 'copyFromGelombang']);
});