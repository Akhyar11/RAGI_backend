<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konversi kolom status yang membutuhkan nilai baru di luar enum lama:
     * - pendaftaran_calon_mhs.status membutuhkan 'mahasiswa_baru' (hasil konversi NIM).
     * - dokumen_pendaftaran.jenis_dokumen mengikuti jenis_dokumen bebas dari berkas_requirement.
     * Nilai status kini dikelola lewat konstanta pada model (string column).
     */
    public function up(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->string('status', 50)->default('draft')->change();
        });

        Schema::table('spmb_dokumen_pendaftaran', function (Blueprint $table) {
            $table->string('jenis_dokumen', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'verified', 'lulus_administrasi', 'gagal_administrasi'])
                ->default('draft')->change();
        });

        Schema::table('spmb_dokumen_pendaftaran', function (Blueprint $table) {
            $table->enum('jenis_dokumen', ['ijazah', 'rapor', 'ktp', 'foto', 'lainnya'])->change();
        });
    }
};
