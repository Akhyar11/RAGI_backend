<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_mahasiswa', function (Blueprint $table) {
            $table->string('nisn', 30)->nullable()->after('nik');
            $table->string('email', 100)->nullable()->after('telepon');
            $table->string('rt', 10)->nullable()->after('alamat');
            $table->string('rw', 10)->nullable()->after('rt');
            $table->string('dusun', 100)->nullable()->after('rw');
            $table->string('kelurahan', 100)->nullable()->after('dusun');
            $table->string('kecamatan', 100)->nullable()->after('kelurahan');
            $table->string('kota', 100)->nullable()->after('kecamatan');
            $table->string('provinsi', 100)->nullable()->after('kota');
            $table->string('kode_pos', 10)->nullable()->after('provinsi');
            $table->string('jenis_tinggal', 50)->nullable()->after('kode_pos');
            $table->string('alat_transportasi', 50)->nullable()->after('jenis_tinggal');
            $table->string('nama_ibu_kandung', 150)->nullable()->after('alat_transportasi');
            $table->string('nik_ibu', 30)->nullable()->after('nama_ibu_kandung');
            $table->string('nama_ayah', 150)->nullable()->after('nik_ibu');
            $table->string('nik_ayah', 30)->nullable()->after('nama_ayah');
            $table->string('nama_wali', 150)->nullable()->after('nik_ayah');
            $table->string('jalur_masuk', 50)->nullable()->default('Mandiri')->after('tanggal_masuk');
            $table->string('jenis_pendaftaran', 50)->nullable()->default('Peserta Didik Baru')->after('jalur_masuk');
            $table->string('id_feeder_biodata', 100)->nullable()->after('id_feeder');
            $table->string('id_feeder_riwayat', 100)->nullable()->after('id_feeder_biodata');
        });
    }

    public function down(): void
    {
        Schema::table('siakad_mahasiswa', function (Blueprint $table) {
            $table->dropColumn([
                'nisn',
                'email',
                'rt',
                'rw',
                'dusun',
                'kelurahan',
                'kecamatan',
                'kota',
                'provinsi',
                'kode_pos',
                'jenis_tinggal',
                'alat_transportasi',
                'nama_ibu_kandung',
                'nik_ibu',
                'nama_ayah',
                'nik_ayah',
                'nama_wali',
                'jalur_masuk',
                'jenis_pendaftaran',
                'id_feeder_biodata',
                'id_feeder_riwayat',
            ]);
        });
    }
};
