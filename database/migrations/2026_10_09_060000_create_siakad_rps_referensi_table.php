<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel referensi RPS: Bentuk Pembelajaran, Metode Pembelajaran, Kriteria Penilaian, Komponen Evaluasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('siakad_rps_referensi')) {
            return;
        }

        Schema::create('siakad_rps_referensi', function (Blueprint $table) {
            $table->id();
            $table->string('tipe', 50); // jenis_pembelajaran, bentuk, metode, kriteria, komponen
            $table->string('kode', 50)->nullable();
            $table->string('nama', 255);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipe', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_rps_referensi');
    }
};
