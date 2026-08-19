<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawaban peserta per soal. Append-only; upsert per (paket, soal).
 * `dinilai_at` terisi saat auto-grading berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jawaban_cat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paket_soal_id')->constrained('paket_soal_cat')->cascadeOnDelete();
            $table->foreignId('soal_id')->constrained('soal_cat')->cascadeOnDelete();
            $table->string('jawaban')->nullable();
            $table->boolean('ragu_ragu')->default(false);
            $table->timestamp('dinilai_at')->nullable();
            $table->timestamps();

            $table->unique(['paket_soal_id', 'soal_id'], 'jawaban_paket_soal_unique');
            $table->index('paket_soal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jawaban_cat');
    }
};
