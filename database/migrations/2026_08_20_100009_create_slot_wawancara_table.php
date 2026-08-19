<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slot janji temu wawancara per peserta.
 * `status`: dijadwalkan | selesai | batal (string, bukan enum).
 * `pewawancara_id` merujuk pegawai (SIMPEG opsional, tanpa FK constraint).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_wawancara', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujian_spmb')->cascadeOnDelete();
            $table->foreignId('peserta_ujian_id')->constrained('peserta_ujian_spmb')->cascadeOnDelete();
            $table->unsignedBigInteger('pewawancara_id')->nullable();
            $table->timestamp('waktu_mulai')->nullable();
            $table->string('status', 20)->default('dijadwalkan');
            $table->timestamps();

            $table->index('jadwal_ujian_id');
            $table->index('peserta_ujian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_wawancara');
    }
};
