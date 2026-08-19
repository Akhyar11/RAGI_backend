<?php

use App\Http\Controllers\API\Spmb\JadwalUjianController;

/*
|--------------------------------------------------------------------------
| SPMB Ujian & Seleksi Routes — Jadwal, CAT, Pengawas, Nilai, Ranking, Pengumuman
|--------------------------------------------------------------------------
| File ini dimuat oleh bootstrap/app.php dengan middleware auth:api dan prefix spmb.
| Agent pengembang: APPEND route baru di akhir file ini (jangan ubah route yang sudah ada).
*/

// Ujian Masuk / CBT
Route::get('jadwal-ujian', [JadwalUjianController::class, 'index']);
Route::post('jadwal-ujian', [JadwalUjianController::class, 'store']);
Route::get('jadwal-ujian/{id}', [JadwalUjianController::class, 'show']);
Route::post('jadwal-ujian/{id}/assign-peserta', [JadwalUjianController::class, 'assignPeserta']);