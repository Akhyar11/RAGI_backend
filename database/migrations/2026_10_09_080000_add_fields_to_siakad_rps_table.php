<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom `kode_rps`, `tanggal_penyusunan`, `dosen_bisa_edit` pada tabel `siakad_rps`
 * sesuai format form Pembuatan Dokumen RPS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_rps', function (Blueprint $table) {
            $table->string('kode_rps', 100)->nullable()->after('mata_kuliah_id');
            $table->date('tanggal_penyusunan')->nullable()->after('semester');
            $table->boolean('dosen_bisa_edit')->default(true)->after('kaprodi_id');
        });
    }

    public function down(): void
    {
        Schema::table('siakad_rps', function (Blueprint $table) {
            $table->dropColumn(['kode_rps', 'tanggal_penyusunan', 'dosen_bisa_edit']);
        });
    }
};
