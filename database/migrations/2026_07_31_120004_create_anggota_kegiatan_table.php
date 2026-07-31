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
        Schema::create('anggota_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposal_kegiatan')->cascadeOnDelete();
            $table->enum('jenis_anggota', ['dosen', 'mahasiswa', 'eksternal']);
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->unsignedBigInteger('mahasiswa_id')->nullable(); // FK to SIAKAD mahasiswa
            $table->string('nama_eksternal', 255)->nullable();
            $table->string('instansi_eksternal', 255)->nullable();
            $table->enum('peran', ['ketua', 'anggota', 'penanggung_jawab_lapangan'])->default('anggota');
            $table->text('tugas_kegiatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggota_kegiatan');
    }
};
