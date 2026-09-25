<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_pa_laporan', function (Blueprint $table) {
            $table->dropUnique(['dosen_id', 'tahun_akademik_id']);

            $table->date('tanggal')->nullable()->after('tahun_akademik_id');
            $table->string('kelas', 100)->nullable()->after('tanggal');
            $table->integer('mhs_aktif')->default(0)->after('kelas');
            $table->integer('mhs_nonaktif')->default(0)->after('mhs_aktif');
            $table->integer('mhs_cuti')->default(0)->after('mhs_nonaktif');
            $table->integer('mhs_keluar')->default(0)->after('mhs_cuti');
            $table->text('kondisi_mahasiswa')->nullable()->after('mhs_keluar');
            $table->text('penanganan_mahasiswa')->nullable()->after('kondisi_mahasiswa');
        });
    }

    public function down(): void
    {
        Schema::table('siakad_pa_laporan', function (Blueprint $table) {
            $table->dropColumn([
                'tanggal',
                'kelas',
                'mhs_aktif',
                'mhs_nonaktif',
                'mhs_cuti',
                'mhs_keluar',
                'kondisi_mahasiswa',
                'penanganan_mahasiswa',
            ]);

            $table->unique(['dosen_id', 'tahun_akademik_id']);
        });
    }
};
