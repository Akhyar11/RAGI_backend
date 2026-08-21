<?php

use App\Http\Controllers\API\Spmb\AdminSeleksiController;
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
Route::get('pendaftar', [AdminSeleksiController::class, 'getPendaftar']);
Route::post('pendaftar/{id}/verifikasi', [AdminSeleksiController::class, 'verifikasi']);
Route::post('pendaftar/{id}/kelulusan', [AdminSeleksiController::class, 'tetapkanKelulusan']);

// Kuota Program Studi
Route::apiResource('kuota-prodi', SpmbKuotaProdiController::class);

// Daftar Ulang
Route::post('daftar-ulang/{pendaftaran_id}/generate-tagihan', [DaftarUlangController::class, 'generateTagihan']);
Route::post('daftar-ulang/{pendaftaran_id}/konfirmasi', [DaftarUlangController::class, 'konfirmasi']);

// Laporan & Export Data
Route::get('laporan/statistik', [LaporanSpmbController::class, 'statistik']);
Route::get('laporan/export-csv', [LaporanSpmbController::class, 'exportCsv']);