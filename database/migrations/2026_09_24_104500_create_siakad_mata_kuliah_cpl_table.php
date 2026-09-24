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
        // Pivot Mata Kuliah ke CPL (siakad_mata_kuliah_cpl) untuk Matriks Checklist CPL-MK
        if (!Schema::hasTable('siakad_mata_kuliah_cpl')) {
            Schema::create('siakad_mata_kuliah_cpl', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mata_kuliah_id')->constrained('siakad_mata_kuliah')->cascadeOnDelete();
                $table->foreignId('cpl_id')->constrained('siakad_cpl')->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['mata_kuliah_id', 'cpl_id'], 'siakad_mk_cpl_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_mata_kuliah_cpl');
    }
};
