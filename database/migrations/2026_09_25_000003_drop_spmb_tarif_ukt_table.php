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
        Schema::dropIfExists('spmb_tarif_ukt');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('spmb_tarif_ukt')) {
            return;
        }

        if (!Schema::hasTable('sikeu_master_biaya')) {
            return;
        }

        Schema::create('spmb_tarif_ukt', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->nullable();
            $table->text('deskripsi')->nullable();
            $table->foreignId('master_program_studi_id')->nullable()->constrained('siakad_program_studi')->onDelete('restrict');
            $table->foreignId('master_sikeu_biaya_id')->nullable()->constrained('sikeu_master_biaya')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['master_program_studi_id', 'master_sikeu_biaya_id'], 'biaya_daftar_ulang_unique_idx');
        });
    }
};
