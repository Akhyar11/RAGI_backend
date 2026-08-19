<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarif UKT per Program Studi + Tahun Akademik + Kelompok UKT (untuk Daftar Ulang).
     */
    public function up(): void
    {
        Schema::create('tarif_ukt_spmb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')->constrained('master_program_studi')->onDelete('restrict');
            $table->foreignId('tahun_akademik_id')->constrained('master_tahun_akademik')->onDelete('restrict');
            $table->string('kelompok_ukt', 20)->default('I');
            $table->decimal('nominal', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['program_studi_id', 'tahun_akademik_id', 'kelompok_ukt'], 'tarif_ukt_spmb_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_ukt_spmb');
    }
};
