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
        Schema::create('sippm_anggota_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('sippm_proposal_kegiatan')->cascadeOnDelete();
            $table->enum('jenis_tim', ['dosen', 'tendik', 'mahasiswa', 'dosen_eksternal', 'eksternal'])->default('dosen');
            $table->foreignId('pegawai_id')->nullable()->constrained('simpeg_pegawai')->nullOnDelete(); // Dosen or Tendik from SIMPEG
            $table->unsignedBigInteger('mahasiswa_id')->nullable(); // FK to SIAKAD mahasiswa
            $table->unsignedBigInteger('mata_kuliah_id')->nullable(); // FK to SIAKAD mata_kuliah for Grade Conversion (Konversi Nilai)
            $table->string('nama_eksternal', 255)->nullable();
            $table->string('instansi_eksternal', 255)->nullable();
            $table->string('nidn_eksternal', 50)->nullable();
            $table->string('peran_dalam_tim', 100)->default('Anggota');
            $table->text('tugas_kegiatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sippm_anggota_kegiatan');
    }
};
