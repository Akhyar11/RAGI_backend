<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil auto-grading per peserta per mata uji.
 * skor_akhir = skor_mentah / skor_maksimal * bobot_persen (sudah berbobot 0-100).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_cat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_ujian_id')->constrained('peserta_ujian_spmb')->cascadeOnDelete();
            $table->foreignId('mata_uji_id')->constrained('mata_uji_cat')->cascadeOnDelete();
            $table->integer('jumlah_benar')->default(0);
            $table->decimal('skor_mentah', 8, 2)->default(0);
            $table->decimal('skor_akhir', 8, 2)->default(0);
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();

            $table->unique(['peserta_ujian_id', 'mata_uji_id'], 'hasil_peserta_mata_uji_unique');
            $table->index('mata_uji_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_cat');
    }
};
