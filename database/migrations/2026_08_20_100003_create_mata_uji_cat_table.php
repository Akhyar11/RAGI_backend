<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mata uji CAT per gelombang (mis. TPA, TPS, Bahasa Inggris).
 * `bobot_persen` adalah bobot kontribusi mata uji terhadap nilai total seleksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_uji_cat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_id')->constrained('gelombang_penerimaan')->cascadeOnDelete();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->integer('durasi_menit')->default(60);
            $table->integer('jumlah_soal')->default(0); // 0 = semua soal bank yang aktif
            $table->decimal('bobot_persen', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('gelombang_id');
            $table->index(['gelombang_id', 'is_active'], 'mata_uji_gelombang_aktif_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_uji_cat');
    }
};
