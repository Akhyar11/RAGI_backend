<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sampel embedding multi-pose (tegak/nunduk/dongak) mengikuti fungsi
     * project Presensi Indonusa, supaya verifikasi best-match ke sampel
     * terdekat dan tetap cocok saat pose kepala bervariasi.
     * Kolom face_embedding (centroid) dipertahankan untuk kompatibilitas.
     */
    public function up(): void
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->text('face_embeddings')->nullable()->after('face_embedding');
        });
    }

    public function down(): void
    {
        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->dropColumn('face_embeddings');
        });
    }
};
