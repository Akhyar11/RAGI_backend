<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket soal per peserta per sesi CAT.
 * `soal_urutan` = JSON array id soal (urutan acak per peserta, anti-mencontek).
 * `status`: belum | berlangsung | selesai (string, bukan enum).
 * Satu peserta hanya memiliki satu paket (unique peserta_ujian_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paket_soal_cat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_ujian_id')->constrained('peserta_ujian_spmb')->cascadeOnDelete();
            $table->foreignId('sesi_id')->constrained('jadwal_ujian_spmb')->cascadeOnDelete();
            $table->json('soal_urutan');
            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('waktu_selesai')->nullable();
            $table->string('status', 20)->default('belum');
            $table->timestamps();

            $table->unique('peserta_ujian_id', 'paket_peserta_unique');
            $table->index('sesi_id');
            $table->index(['sesi_id', 'status'], 'paket_sesi_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paket_soal_cat');
    }
};
