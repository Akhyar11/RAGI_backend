<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan `program_studi_id` pada `siakad_rps_referensi` agar data referensi
 * (Bentuk, Metode, Kriteria, Komponen) terisolasi per Program Studi dan tidak bocor lintas prodi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_rps_referensi', function (Blueprint $table) {
            $table->foreignId('program_studi_id')
                ->nullable()
                ->after('tipe')
                ->constrained('siakad_program_studi')
                ->cascadeOnDelete();

            $table->index(['program_studi_id', 'tipe', 'is_active'], 'rps_ref_prodi_tipe_idx');
        });
    }

    public function down(): void
    {
        Schema::table('siakad_rps_referensi', function (Blueprint $table) {
            $table->dropForeign(['program_studi_id']);
            $table->dropIndex('rps_ref_prodi_tipe_idx');
            $table->dropColumn('program_studi_id');
        });
    }
};
