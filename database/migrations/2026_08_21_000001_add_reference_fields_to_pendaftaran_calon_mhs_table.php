<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->string('status_sipil')->nullable()->after('agama');
            $table->string('jenis_daftar')->nullable()->after('program_studi_pilihan2_id');
            $table->string('kelas')->nullable()->after('jenis_daftar');
            $table->string('info_daftar')->nullable()->after('kelas');
            $table->string('ket_info_daftar')->nullable()->after('info_daftar');
            $table->string('nama_ortu')->nullable()->after('penghasilan_ortu');
            $table->text('alamat_ortu')->nullable()->after('nama_ortu');
            $table->string('telp_ortu')->nullable()->after('alamat_ortu');
            $table->text('alamat_sekolah')->nullable()->after('asal_sekolah');
        });
    }

    public function down(): void
    {
        Schema::table('pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->dropColumn([
                'status_sipil',
                'jenis_daftar',
                'kelas',
                'info_daftar',
                'ket_info_daftar',
                'nama_ortu',
                'alamat_ortu',
                'telp_ortu',
                'alamat_sekolah',
            ]);
        });
    }
};
