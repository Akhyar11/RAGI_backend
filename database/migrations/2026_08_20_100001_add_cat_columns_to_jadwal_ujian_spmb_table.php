<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan kolom untuk mendukung sesi CAT (Computer Assisted Test).
 *
 * `tipe_ujian` pada tabel jadwal_ujian_spmb adalah DB enum
 * ('tulis','praktik','wawancara') sehingga TIDAK diubah. Tipe 'cat'
 * ditampung di kolom varchar baru `tipe_ujian_v2` (nullable).
 * Aplikasi memakai `tipe_ujian_v2 ?? tipe_ujian` sebagai tipe efektif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_ujian_spmb', function (Blueprint $table) {
            $table->string('kode_sesi', 50)->nullable()->after('nama_sesi');
            $table->string('tipe_ujian_v2', 20)->nullable()->after('tipe_ujian');
            $table->index(['gelombang_id', 'tipe_ujian_v2'], 'jadwal_gelombang_tipe_v2_index');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_ujian_spmb', function (Blueprint $table) {
            $table->dropIndex('jadwal_gelombang_tipe_v2_index');
            $table->dropColumn(['tipe_ujian_v2', 'kode_sesi']);
        });
    }
};
