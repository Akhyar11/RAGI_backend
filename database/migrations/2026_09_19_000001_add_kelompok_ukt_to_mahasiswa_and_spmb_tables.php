<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('siakad_mahasiswa') && !Schema::hasColumn('siakad_mahasiswa', 'kelompok_ukt')) {
            Schema::table('siakad_mahasiswa', function (Blueprint $table) {
                $table->unsignedTinyInteger('kelompok_ukt')->nullable()->default(3)->after('jalur_masuk');
            });
        }

        if (Schema::hasTable('spmb_pendaftaran_calon_mhs') && !Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kelompok_ukt')) {
            Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
                $table->unsignedTinyInteger('kelompok_ukt')->nullable()->default(3)->after('master_tipe_jalur_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('siakad_mahasiswa') && Schema::hasColumn('siakad_mahasiswa', 'kelompok_ukt')) {
            Schema::table('siakad_mahasiswa', function (Blueprint $table) {
                $table->dropColumn('kelompok_ukt');
            });
        }

        if (Schema::hasTable('spmb_pendaftaran_calon_mhs') && Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kelompok_ukt')) {
            Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
                $table->dropColumn('kelompok_ukt');
            });
        }
    }
};
