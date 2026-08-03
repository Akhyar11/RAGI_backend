<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peserta_ujian_spmb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujian_spmb')->cascadeOnDelete();
            $table->string('no_peserta')->unique();
            $table->string('nomor_kursi')->nullable();
            $table->boolean('hadir')->default(false);
            $table->timestamps(); // ERD only mentioned created_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peserta_ujian_spmb');
    }
};
