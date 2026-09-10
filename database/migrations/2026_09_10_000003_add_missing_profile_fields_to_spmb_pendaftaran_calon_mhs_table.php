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
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            // Make nama_lengkap and nik nullable for initial draft saving (Step 1)
            $table->string('nama_lengkap', 255)->nullable()->change();
            $table->string('nik', 255)->nullable()->change();

            // Add missing profile & address fields
            if (!Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'no_hp')) {
                $table->string('no_hp', 30)->nullable()->after('kewarganegaraan');
            }
            if (!Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'provinsi')) {
                $table->string('provinsi', 255)->nullable()->after('alamat');
            }
            if (!Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kota_kabupaten')) {
                $table->string('kota_kabupaten', 255)->nullable()->after('provinsi');
            }
            if (!Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kecamatan')) {
                $table->string('kecamatan', 255)->nullable()->after('kota_kabupaten');
            }
            if (!Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kode_pos')) {
                $table->string('kode_pos', 20)->nullable()->after('kecamatan');
            }
            if (!Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'pekerjaan_ayah')) {
                $table->string('pekerjaan_ayah', 255)->nullable()->after('nama_ayah');
            }
            if (!Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'pekerjaan_ibu')) {
                $table->string('pekerjaan_ibu', 255)->nullable()->after('nama_ibu');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['no_hp', 'provinsi', 'kota_kabupaten', 'kecamatan', 'kode_pos', 'pekerjaan_ayah', 'pekerjaan_ibu'] as $col) {
                if (Schema::hasColumn('spmb_pendaftaran_calon_mhs', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
