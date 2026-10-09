<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemetaan Rumusan CPMK Program Studi (CPMK-PS) ke Mata Kuliah yang mengampunya.
 * Relasi many-to-many antara `siakad_cpmk_prodi` dan `siakad_mata_kuliah`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('siakad_cpmk_prodi_mata_kuliah')) {
            return;
        }

        Schema::create('siakad_cpmk_prodi_mata_kuliah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpmk_prodi_id')->constrained('siakad_cpmk_prodi')->cascadeOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('siakad_mata_kuliah')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cpmk_prodi_id', 'mata_kuliah_id'], 'cpmk_prodi_mk_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_cpmk_prodi_mata_kuliah');
    }
};
