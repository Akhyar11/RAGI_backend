<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rumusan CPMK Program Studi (CPMK-PS).
 *
 * Berbeda dengan `siakad_cpmk` yang merupakan CPMK per Mata Kuliah, tabel ini
 * memuat rumusan CPMK pada level program studi: terikat kurikulum dan dipetakan
 * ke satu CPL, tanpa terikat mata kuliah. Inilah sumber dari mana CPMK per mata
 * kuliah pada tahap berikutnya diturunkan.
 *
 * Kolom `indikator` sengaja tidak dibuat: indikator merupakan ciri Sub-CPMK
 * (`siakad_sub_cpmk`), bukan rumusan CPMK program studi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('siakad_cpmk_prodi')) {
            return;
        }

        Schema::create('siakad_cpmk_prodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained('siakad_kurikulum')->cascadeOnDelete();
            $table->foreignId('cpl_id')->constrained('siakad_cpl')->cascadeOnDelete();
            $table->string('kode_cpmk', 50);
            $table->text('deskripsi');
            $table->timestamps();
            $table->softDeletes();

            // Kode CPMK unik per kurikulum, mengikuti pola kode CPL unik per prodi.
            $table->unique(['kurikulum_id', 'kode_cpmk'], 'cpmk_prodi_kurikulum_kode_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_cpmk_prodi');
    }
};