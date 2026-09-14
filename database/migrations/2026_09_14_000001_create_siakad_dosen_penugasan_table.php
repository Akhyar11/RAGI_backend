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
        Schema::create('siakad_dosen_penugasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosen_id')->constrained('siakad_dosen')->cascadeOnDelete();
            $table->foreignId('program_studi_id')->constrained('spmb_master_program_studi')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('spmb_master_tahun_akademik')->cascadeOnDelete();
            $table->string('nomor_surat_tugas', 100)->nullable();
            $table->date('tanggal_surat_tugas')->nullable();
            $table->date('tmt_surat_tugas')->nullable();
            $table->boolean('is_homebase')->default(true);
            $table->string('id_feeder', 100)->nullable()->comment('id_registrasi_dosen dari Neo Feeder');
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['dosen_id', 'program_studi_id', 'tahun_akademik_id'], 'penugasan_dosen_prodi_ta_unique');
            $table->index('sync_status');
            $table->index('id_feeder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_dosen_penugasan');
    }
};
