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
        // 1. Profil Lulusan (PL)
        Schema::create('siakad_profil_lulusan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')->constrained('master_program_studi')->cascadeOnDelete();
            $table->string('kode_pl', 50); // e.g. PL-01
            $table->string('nama');
            $table->text('deskripsi');
            $table->integer('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['program_studi_id', 'kode_pl']);
        });

        // 2. Bahan Kajian (BK)
        Schema::create('siakad_bahan_kajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')->constrained('master_program_studi')->cascadeOnDelete();
            $table->string('kode_bk', 50); // e.g. BK-01
            $table->string('nama_bk');
            $table->text('deskripsi')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['program_studi_id', 'kode_bk']);
        });

        // 3. Pivot Profil Lulusan ke CPL (profil_lulusan_cpl)
        Schema::create('siakad_profil_lulusan_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profil_lulusan_id')->constrained('siakad_profil_lulusan')->cascadeOnDelete();
            $table->foreignId('cpl_id')->constrained('siakad_cpl')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['profil_lulusan_id', 'cpl_id']);
        });

        // 4. Pivot Mata Kuliah ke Bahan Kajian (makulbk)
        Schema::create('siakad_mata_kuliah_bahan_kajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('siakad_mata_kuliah')->cascadeOnDelete();
            $table->foreignId('bahan_kajian_id')->constrained('siakad_bahan_kajian')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['mata_kuliah_id', 'bahan_kajian_id'], 'mk_bk_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_mata_kuliah_bahan_kajian');
        Schema::dropIfExists('siakad_profil_lulusan_cpl');
        Schema::dropIfExists('siakad_bahan_kajian');
        Schema::dropIfExists('siakad_profil_lulusan');
    }
};
