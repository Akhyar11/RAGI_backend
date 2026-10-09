<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('siakad_master_kelas')) {
            Schema::create('siakad_master_kelas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_studi_id')->constrained('siakad_program_studi')->cascadeOnDelete();
                $table->string('nama_kelas', 50); // Contoh: "25A", "25B", "Reguler A"
                $table->integer('tahun_angkatan')->nullable(); // Contoh: 2025
                $table->foreignId('dosen_pa_id')->nullable()->constrained('siakad_dosen')->nullOnDelete();
                $table->string('keterangan', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['program_studi_id', 'nama_kelas', 'tahun_angkatan', 'deleted_at'], 'siakad_master_kelas_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_master_kelas');
    }
};
