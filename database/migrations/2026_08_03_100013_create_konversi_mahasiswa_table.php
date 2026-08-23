<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_konversi_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('spmb_pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->nullable(); // Assuming nullable if created simultaneously or later
            $table->string('nim_diterbitkan')->unique()->nullable();
            $table->foreignId('diproses_oleh')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_konversi_mahasiswa');
    }
};
