<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan pengawas ujian per sesi.
 * `pegawai_id` adalah integer TANPA FK constraint (modul SIMPEG opsional).
 * `peran`: kepala | anggota (string, bukan enum).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengawas_ujian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujian_spmb')->cascadeOnDelete();
            $table->unsignedBigInteger('pegawai_id');
            $table->string('peran', 20)->default('anggota'); // kepala | anggota
            $table->timestamps();

            $table->unique(['jadwal_ujian_id', 'pegawai_id', 'peran'], 'pengawas_jadwal_pegawai_peran_unique');
            $table->index('pegawai_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengawas_ujian');
    }
};
