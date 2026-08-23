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
        Schema::create('sippm_standar_iku5_prodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kerja_id')->constrained('simpeg_unit_kerja')->onDelete('cascade');
            $table->string('tahun_akademik', 10)->default('2025/2026')->index();
            $table->integer('target_publikasi_scopus')->default(5);
            $table->integer('target_publikasi_sinta')->default(10);
            $table->integer('target_hki_paten')->default(4);
            $table->integer('target_buku_isbn')->default(3);
            $table->timestamps();

            $table->unique(['unit_kerja_id', 'tahun_akademik']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sippm_standar_iku5_prodi');
    }
};
