<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('siakad_absensi_mahasiswa') || !Schema::hasColumn('siakad_absensi_mahasiswa', 'status')) {
            return;
        }

        Schema::table('siakad_absensi_mahasiswa', function (Blueprint $table) {
            $table->string('status', 50)->default('hadir')->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('siakad_absensi_mahasiswa') || !Schema::hasColumn('siakad_absensi_mahasiswa', 'status')) {
            return;
        }

        if (DB::table('siakad_absensi_mahasiswa')->whereRaw('LENGTH(status) > 20')->exists()) {
            throw new \RuntimeException('Tidak dapat rollback status presensi: terdapat kode status yang panjangnya melebihi 20 karakter.');
        }

        Schema::table('siakad_absensi_mahasiswa', function (Blueprint $table) {
            $table->string('status', 20)->default('hadir')->change();
        });
    }
};
