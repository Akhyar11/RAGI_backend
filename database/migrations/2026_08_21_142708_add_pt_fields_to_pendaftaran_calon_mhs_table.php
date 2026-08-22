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
        Schema::table('pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->string('kewarganegaraan')->nullable()->after('agama');
            $table->enum('asal_lulusan', ['sekolah', 'pt'])->default('sekolah')->after('kewarganegaraan');
            $table->string('npsn_sekolah')->nullable()->after('asal_sekolah');
            $table->string('asal_pt')->nullable()->after('npsn_sekolah');
            $table->string('jenis_pt')->nullable()->after('asal_pt');
            $table->string('alamat_pt')->nullable()->after('jenis_pt');
            $table->string('jenjang_pt')->nullable()->after('alamat_pt');
            $table->string('progdi_pt')->nullable()->after('jenjang_pt');
            $table->string('ipk_pt')->nullable()->after('progdi_pt');
            $table->string('nim_pt')->nullable()->after('ipk_pt');
            $table->string('tahun_lulus_pt')->nullable()->after('nim_pt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->dropColumn([
                'kewarganegaraan',
                'npsn_sekolah',
                'asal_lulusan',
                'asal_pt',
                'jenis_pt',
                'alamat_pt',
                'jenjang_pt',
                'progdi_pt',
                'ipk_pt',
                'nim_pt',
                'tahun_lulus_pt'
            ]);
        });
    }
};
