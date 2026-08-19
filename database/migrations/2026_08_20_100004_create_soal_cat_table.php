<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank soal CAT.
 * - tipe: pg | bener_salah | isian (string, bukan enum)
 * - opsi: JSON daftar pilihan jawaban (untuk pg)
 * - kunci: untuk pg/bener_salah disimpan sebagai HASH (sha256, lowercase),
 *   untuk isian disimpan plain text (pencocokan case-insensitive).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soal_cat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_uji_id')->constrained('mata_uji_cat')->cascadeOnDelete();
            $table->string('tipe', 20); // pg | bener_salah | isian
            $table->text('pertanyaan');
            $table->json('opsi')->nullable();
            $table->text('kunci');
            $table->decimal('bobot', 5, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('mata_uji_id');
            $table->index(['mata_uji_id', 'is_active'], 'soal_mata_uji_aktif_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soal_cat');
    }
};
