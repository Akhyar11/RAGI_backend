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
        Schema::table('simpeg_riwayat_pendidikan_pegawai', function (Blueprint $table) {
            $table->string('gelar_akademik', 100)->nullable()->after('bidang_ilmu');
            $table->string('singkatan_gelar', 50)->nullable()->after('gelar_akademik');
        });

        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->string('gelar_depan', 30)->nullable()->after('nama_lengkap');
            $table->string('gelar_belakang', 50)->nullable()->after('gelar_depan');
        });

        Schema::table('spmb_master_program_studi', function (Blueprint $table) {
            $table->string('id_feeder', 50)->nullable()->after('kode_prodi_dikti');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_riwayat_pendidikan_pegawai', function (Blueprint $table) {
            $table->dropColumn(['gelar_akademik', 'singkatan_gelar']);
        });

        Schema::table('simpeg_pegawai', function (Blueprint $table) {
            $table->dropColumn(['gelar_depan', 'gelar_belakang']);
        });

        Schema::table('spmb_master_program_studi', function (Blueprint $table) {
            $table->dropColumn('id_feeder');
        });
    }
};
