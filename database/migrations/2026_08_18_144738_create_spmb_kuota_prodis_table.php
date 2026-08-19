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
        Schema::create('spmb_kuota_prodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_akademik_id'); // Assuming from SIAKAD
            $table->foreignId('program_studi_id'); // Assuming from SIAKAD
            $table->integer('kuota_total')->default(0);
            $table->integer('kuota_terisi')->default(0);
            $table->timestamps();
            
            $table->unique(['tahun_akademik_id', 'program_studi_id'], 'kuota_ta_prodi_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spmb_kuota_prodi');
    }
};
