<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan `slug` pada `lms_forum_topik` sebagai kunci alami topik default kelas.
 *
 * Latar belakang (tech debt TD-3):
 * - Endpoint baca daftar topik sebelumnya membuat topik "Diskusi Umum" secara
 *   otomatis. Itu melanggar prinsip "GET tidak boleh menulis data" dan
 *   rawan membuat topik ganda bila dua tab membuka halaman bersamaan.
 * - Pembuatan topik default dipindah ke aksi eksplisit
 *   `POST /api/v1/lms/kelas/{kelasId}/forum/umum` (idempoten).
 *
 * `slug` dipakai sebagai penanda deterministik + unique per kelas sehingga dua
 * request bersamaan tidak menghasilkan dua topik "Diskusi Umum" untuk kelas
 * yang sama. Topik yang dibuat pengguna tetap `slug = NULL`; MySQL maupun
 * SQLite mengizinkan banyak NULL pada unique index, sehingga judul topik
 * biasa tidak ikut dibatasi.
 */
return new class extends Migration
{
    /**
     * Slug topik default kelas. Nilai domain tetap (bukan entitas master).
     */
    private const SLUG_TOPIK_DEFAULT = 'diskusi-umum';

    public function up(): void
    {
        if (!Schema::hasColumn('lms_forum_topik', 'slug')) {
            Schema::table('lms_forum_topik', function (Blueprint $table) {
                $table->string('slug', 60)->nullable()->after('judul');
            });
        }

        Schema::table('lms_forum_topik', function (Blueprint $table) {
            $table->unique(
                ['kelas_id', 'slug'],
                'lms_forum_topik_kelas_slug_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('lms_forum_topik', function (Blueprint $table) {
            $table->dropUnique('lms_forum_topik_kelas_slug_unique');
        });

        Schema::table('lms_forum_topik', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
