<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Siakad\ForumController;
use App\Http\Controllers\API\Siakad\LmsController;
use App\Http\Controllers\API\Siakad\LmsNotifikasiController;
use App\Http\Controllers\API\Siakad\LmsPresensiController;
use App\Http\Controllers\API\Siakad\QuizController;

/*
|--------------------------------------------------------------------------
| LMS Module API Routes (standalone)
|--------------------------------------------------------------------------
| Prefix: /api/v1/lms (didaftarkan di bootstrap/app.php)
|
| Modul LMS dipisah dari SIAKAD agar menu tidak menumpuk. Tabel lms_*
| tetap berelasi ke siakad_kelas / siakad_pertemuan / KRS / OBE.
| Otorisasi memakai Gate siakad.kelas.* & siakad.nilai.manage agar role
| dosen/mahasiswa existing tidak putus akses. Pengecualian: Forum Diskusi memakai
| permission miliknya sendiri (lms.forum.*) karena aksi baca dan aksi tulisnya
| tidak boleh tercampur dalam satu izin.
*/

// --- LMS & Absensi Terintegrasi Perkuliahan ---
// Hak Akses Baca & Partisipasi Mahasiswa/Dosen
Route::middleware(['can:siakad.kelas.read'])->group(function () {
    Route::get('/dashboard/overview', [LmsController::class, 'dashboardOverview']);
    Route::get('/kelas/my', [LmsController::class, 'getMyKelas']);
    Route::get('/tugas/my', [LmsController::class, 'getMyAllTugas']);
    Route::get('/kelas/{kelasId}/overview', [LmsController::class, 'getOverview']);
    Route::get('/kelas/{kelasId}/setting', [LmsController::class, 'getSetting']);
    Route::get('/kelas/{kelasId}/rekap-absensi', [LmsController::class, 'getRekapAbsensi']);
    Route::get('/pertemuan/{id}', [LmsController::class, 'getPertemuan']);
    Route::get('/download/{type}/{id}', [LmsController::class, 'downloadFile']);
    Route::post('/tugas/{tugasId}/kumpul', [LmsController::class, 'kumpulkanTugas']);
    // Perbarui & tarik kembali pengumpulan tugas (pemilik pengumpulan saja).
    Route::put('/tugas/{tugasId}/kumpulan', [LmsController::class, 'updatePengumpulan']);
    Route::delete('/tugas/{tugasId}/kumpulan', [LmsController::class, 'hapusPengumpulan']);
    Route::post('/pertemuan/{pertemuanId}/input-token', [LmsController::class, 'inputToken']);
    Route::post('/pertemuan/{pertemuanId}/izin', [LmsController::class, 'ajukanIzin']);
    // Quiz — sisi mahasiswa
    Route::get('/quiz/{quizId}', [QuizController::class, 'showMahasiswa']);
    Route::post('/quiz/{quizId}/start', [QuizController::class, 'start']);
    Route::get('/quiz/{quizId}/soal', [QuizController::class, 'batchSoal']);
    Route::post('/attempt/{attemptId}/autosave', [QuizController::class, 'autosave']);
    Route::post('/attempt/{attemptId}/submit', [QuizController::class, 'submit']);
    // Tryout — daftar per kelas (dosen & mahasiswa terdaftar)
    Route::get('/kelas/{kelasId}/tryout', [QuizController::class, 'listTryout']);
    // Alur baru: matriks rekap + ketercapaian MK (controller melakukan scoping:
    // mahasiswa hanya melihat baris/nilai miliknya sendiri).
    Route::get('/kelas/{kelasId}/rekap-matrix', [LmsController::class, 'rekapMatrix']);
    Route::get('/kelas/{kelasId}/ketercapaian', [LmsController::class, 'ketercapaian']);

    // --- Agregat level modul (mendukung halaman /lms/* di sidebar) ---
    // Tanpa kelasId: menggabungkan seluruh kelas yang boleh diakses user.
    Route::get('/pertemuan', [LmsController::class, 'listPertemuanSaya']);
    Route::get('/tryout', [QuizController::class, 'listTryoutSaya']);
    Route::get('/pengaturan', [LmsController::class, 'indexPengaturan']);
});

// --- Pengaturan Level Modul LMS (`lms_*` pada core_system_settings) ---
// Berbeda dengan `/pengaturan` (rekap pengaturan per kelas), endpoint ini
// mengelola konfigurasi global modul. Permission khusus admin/superadmin.
Route::middleware(['can:lms.pengaturan.read'])->group(function () {
    Route::get('/pengaturan/modul', [LmsController::class, 'showPengaturanModul']);
});

Route::middleware(['can:lms.pengaturan.manage'])->group(function () {
    Route::put('/pengaturan/modul', [LmsController::class, 'updatePengaturanModul']);
});

// --- Forum Diskusi Kelas ---
// Forum memakai permission milik modul LMS sendiri (`lms.forum.*`), bukan
// permission SIAKAD, agar aksi baca dan aksi tulis tidak tercampur dalam satu
// izin. Route-level guard memakai slug yang sama dengan Policy/FormRequest di
// bawahnya; pengecekan tingkat resource tetap milik ForumPostPolicy.
Route::middleware(['can:lms.forum.read'])->group(function () {
    // Agregat level modul (halaman /lms/forum di sidebar) — read-only.
    Route::get('/forum', [ForumController::class, 'listTopikSaya']);
    Route::get('/kelas/{kelasId}/forum', [ForumController::class, 'listTopik']);
    Route::get('/forum/{topikId}/post', [ForumController::class, 'listPost']);

    // Kirim pesan/balasan — mutasi data, butuh permission `create`.
    Route::post('/forum/{topikId}/post', [ForumController::class, 'storePost'])
        ->middleware('can:lms.forum.create');

    // Pastikan topik default "Diskusi Umum" ada (idempoten). Dipisah dari
    // endpoint baca supaya GET tidak pernah menulis data. Semua anggota kelas
    // yang boleh berdiskusi boleh memanggilnya.
    Route::post('/kelas/{kelasId}/forum/umum', [ForumController::class, 'ensureTopikUmum'])
        ->middleware('can:lms.forum.create');

    // Hapus pesan — pemilik pesan (dengan `lms.forum.create`) atau pengelola
    // kelas (`lms.forum.manage`) ditentukan ForumPostPolicy::delete().
    Route::delete('/forum-post/{postId}', [ForumController::class, 'destroyPost']);

    // Edit pesan — pemilik pesan atau pengelola kelas (ForumPostPolicy::update()).
    Route::put('/forum-post/{postId}', [ForumController::class, 'updatePost']);
});

// Forum — kelola topik (dosen pengampu / kaprodi / admin, tanpa mahasiswa)
Route::middleware(['can:lms.forum.manage'])->group(function () {
    Route::post('/kelas/{kelasId}/forum', [ForumController::class, 'storeTopik']);
    Route::put('/forum/{topikId}', [ForumController::class, 'updateTopik']);
    Route::delete('/forum/{topikId}', [ForumController::class, 'destroyTopik']);
});

// Hak Akses Kelola Kelas & Pembelajaran (Dosen Pengajar / Kaprodi / Admin)
Route::middleware(['can:siakad.kelas.manage'])->group(function () {
    Route::put('/kelas/{kelasId}/setting', [LmsController::class, 'updateSetting']);
    Route::post('/pertemuan/{pertemuanId}/materi', [LmsController::class, 'storeMateri']);
    Route::put('/materi/{materiId}', [LmsController::class, 'updateMateri']);
    Route::delete('/materi/{materiId}', [LmsController::class, 'destroyMateri']);
    Route::post('/materi/{materiId}/file', [LmsController::class, 'uploadMateriFile']);
    Route::delete('/materi-file/{fileId}', [LmsController::class, 'destroyMateriFile']);
    Route::post('/pertemuan/{pertemuanId}/tugas', [LmsController::class, 'storeTugas']);
    Route::put('/tugas/{tugasId}', [LmsController::class, 'updateTugas']);
    Route::delete('/tugas/{tugasId}', [LmsController::class, 'destroyTugas']);
    Route::post('/pertemuan/{pertemuanId}/token', [LmsController::class, 'generateToken']);
    Route::post('/pertemuan/{pertemuanId}/token/rotate', [LmsController::class, 'rotateToken']);
    Route::post('/pertemuan/{pertemuanId}/tutup-presensi', [LmsController::class, 'tutupPresensi']);
    // Quiz — sisi dosen pengelola
    Route::post('/pertemuan/{pertemuanId}/quiz', [QuizController::class, 'store']);
    Route::get('/quiz/{quizId}/manage', [QuizController::class, 'showManage']);
    Route::put('/quiz/{quizId}', [QuizController::class, 'update']);
    Route::delete('/quiz/{quizId}', [QuizController::class, 'destroy']);
    Route::post('/quiz/{quizId}/soal', [QuizController::class, 'attachSoal']);
    Route::delete('/quiz-soal/{quizSoalId}', [QuizController::class, 'detachSoal']);
    Route::get('/quiz/{quizId}/attempts', [QuizController::class, 'listAttempts']);
    Route::post('/attempt/{attemptId}/reset', [QuizController::class, 'resetAttempt']);
    Route::get('/attempt/{attemptId}/detail', [QuizController::class, 'attemptDetail']);
    Route::get('/quiz/{quizId}/preview', [QuizController::class, 'preview']);
    // Tryout — buat & kelola peserta (level kelas)
    Route::post('/kelas/{kelasId}/tryout', [QuizController::class, 'storeTryout']);
    Route::post('/quiz/{quizId}/peserta', [QuizController::class, 'addPeserta']);
    Route::post('/quiz/{quizId}/peserta-kelas', [QuizController::class, 'addPesertaByKelas']);
    Route::post('/quiz/{quizId}/peserta-copy', [QuizController::class, 'copyPeserta']);
    Route::post('/quiz/{quizId}/soal/bulk-delete', [QuizController::class, 'bulkDeleteSoal']);
    Route::get('/mahasiswa/search', [QuizController::class, 'searchMahasiswa']);
    Route::delete('/tryout-peserta/{pesertaId}', [QuizController::class, 'removePeserta']);
    // Manajemen Pertemuan — ubah & hapus
    Route::put('/pertemuan/{pertemuanId}', [LmsController::class, 'updatePertemuan']);
    Route::delete('/pertemuan/{pertemuanId}', [LmsController::class, 'destroyPertemuan']);
    Route::post('/pertemuan/{pertemuanId}/bulk-absensi', [LmsController::class, 'bulkAbsensi']);
    Route::patch('/izin/{izinId}/proses', [LmsController::class, 'prosesIzin']);
    // Alur LMS baru: import materi antar-kelas (mutasi, tetap manage)
    Route::post('/kelas/{kelasId}/import-materi', [LmsController::class, 'importMateri']);
    // Quiz — kolaborator dosen (pengawas/pemantau/penginput soal)
    Route::get('/quiz/{quizId}/kolaborator', [QuizController::class, 'listKolaborator']);
    Route::post('/quiz/{quizId}/kolaborator', [QuizController::class, 'addKolaborator']);
    Route::delete('/quiz-kolaborator/{id}', [QuizController::class, 'removeKolaborator']);
    // Export laporan kelas (CSV/XLSX) — rekap absensi & pengajuan izin.
    Route::get('/kelas/{kelasId}/export/presensi', [LmsController::class, 'exportPresensi']);
    Route::get('/kelas/{kelasId}/export/absensi-izin', [LmsController::class, 'exportIzin']);
});

// --- Notifikasi In-App LMS ---
// Notifikasi bersifat personal (per `user_id`), sehingga tidak memakai
// permission modul: setiap user hanya dapat membaca serta mengubah status
// notifikasi miliknya sendiri. Endpoint ini dipakai lonceng notifikasi pada
// navbar (tersedia untuk seluruh pengguna, bukan hanya peserta LMS).
Route::get('/notifikasi', [LmsNotifikasiController::class, 'index']);
Route::get('/notifikasi/unread-count', [LmsNotifikasiController::class, 'unreadCount']);
Route::post('/notifikasi/read-all', [LmsNotifikasiController::class, 'markAllRead']);
Route::post('/notifikasi/{id}/read', [LmsNotifikasiController::class, 'markRead']);

// --- Panel Administrasi Integrasi Presensi (PresensiV2) ---
// Monitoring & sinkronisasi dua arah LMS <-> PresensiV2. Permission granular
// modul LMS: `lms.presensi.read` (baca rekap & log) dan `lms.presensi.manage`
// (sinkron ulang, tarik riwayat, export). Kedua permission hanya diberikan
// kepada admin/superadmin karena panel ini memantau seluruh kampus.
Route::middleware(['can:lms.presensi.read'])->group(function () {
    Route::get('/admin/presensi/status', [LmsPresensiController::class, 'status']);
    Route::get('/admin/presensi/kelas', [LmsPresensiController::class, 'kelas']);
    Route::get('/admin/presensi/rekap', [LmsPresensiController::class, 'rekap']);
    Route::get('/admin/presensi/log', [LmsPresensiController::class, 'log']);
});

Route::middleware(['can:lms.presensi.manage'])->group(function () {
    Route::get('/admin/presensi/export', [LmsPresensiController::class, 'export']);
    Route::post('/admin/presensi/sync', [LmsPresensiController::class, 'sync']);
    Route::post('/admin/presensi/tarik', [LmsPresensiController::class, 'tarik']);
});

// Hak Akses Penilaian Akademik (OBE Sync)
Route::middleware(['can:siakad.nilai.manage'])->group(function () {
    Route::put('/pengumpulan/{pengumpulanId}/nilai', [LmsController::class, 'beriNilaiTugas']);
    Route::put('/attempt-jawaban/{attemptJawabanId}/nilai', [QuizController::class, 'beriNilaiManual']);
    // Export laporan nilai (CSV/XLSX) — rekap nilai kelas, pengumpulan tugas,
    // hasil quiz & hasil tryout.
    Route::get('/kelas/{kelasId}/export/nilai', [LmsController::class, 'exportNilai']);
    Route::get('/tugas/{tugasId}/export/pengumpulan', [LmsController::class, 'exportPengumpulan']);
    Route::get('/quiz/{quizId}/export/hasil', [QuizController::class, 'exportHasil']);
    Route::get('/tryout/{quizId}/export/hasil', [QuizController::class, 'exportHasilTryout']);
});
