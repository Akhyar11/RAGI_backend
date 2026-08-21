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
            $table->dropColumn(['jenis_daftar', 'kelas']);
            $table->foreignId('master_tipe_jalur_id')->nullable()->after('program_studi_pilihan2_id')->constrained('master_tipe_jalur')->nullOnDelete();
            $table->foreignId('master_jalur_kelas_id')->nullable()->after('master_tipe_jalur_id')->constrained('master_jalur_kelas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->dropForeign(['master_tipe_jalur_id']);
            $table->dropForeign(['master_jalur_kelas_id']);
            $table->dropColumn(['master_tipe_jalur_id', 'master_jalur_kelas_id']);
            $table->string('jenis_daftar')->nullable()->after('program_studi_pilihan2_id');
            $table->string('kelas')->nullable()->after('jenis_daftar');
        });
    }
};
