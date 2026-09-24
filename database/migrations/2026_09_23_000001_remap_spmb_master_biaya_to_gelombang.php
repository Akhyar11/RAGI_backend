<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master biaya SPMB dipetakan ke master gelombang (bukan ke jalur
     * masuk), dan kolom tahun_akademik_id tidak lagi diperlukan karena
     * gelombang sudah membawa tahun akademik + jalur masuk.
     */
    public function up(): void
    {
        Schema::table('spmb_master_biaya', function (Blueprint $table) {
            $table->dropUnique('spmb_master_biaya_unique_idx');
            $table->dropIndex(['tahun_akademik_id']);
            $table->dropIndex(['jalur_masuk_id']);
            $table->foreignId('gelombang_id')->after('program_studi_id')->constrained('spmb_gelombang_penerimaan')->cascadeOnDelete();
            $table->dropColumn(['tahun_akademik_id', 'jalur_masuk_id']);
            $table->unique(['gelombang_id', 'program_studi_id'], 'spmb_master_biaya_gelombang_prodi_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spmb_master_biaya', function (Blueprint $table) {
            $table->dropUnique('spmb_master_biaya_gelombang_prodi_unique');
            $table->dropConstrainedForeignId('gelombang_id');
            $table->unsignedBigInteger('tahun_akademik_id')->index();
            $table->unsignedBigInteger('jalur_masuk_id')->nullable()->index();
            $table->unique(['tahun_akademik_id', 'program_studi_id', 'jalur_masuk_id'], 'spmb_master_biaya_unique_idx');
        });
    }
};
