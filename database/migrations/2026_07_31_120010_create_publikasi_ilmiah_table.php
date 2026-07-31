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
        Schema::create('publikasi_ilmiah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->nullable()->constrained('proposal_kegiatan')->nullOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->text('judul_artikel');
            $table->enum('jenis_publikasi', [
                'jurnal_internasional_bereputasi', 
                'jurnal_nasional_terakreditasi', 
                'prosiding_internasional', 
                'prosiding_nasional', 
                'jurnal_non_akreditasi'
            ]);
            $table->string('nama_jurnal_prosiding', 255);
            $table->enum('indexing', [
                'scopus_q1', 'scopus_q2', 'scopus_q3', 'scopus_q4', 
                'sinta_1', 'sinta_2', 'sinta_3', 'sinta_4', 'sinta_5', 'sinta_6', 
                'wos', 'lainnya'
            ])->default('lainnya');
            $table->string('volume_issue_tahun', 100);
            $table->string('doi', 150)->nullable();
            $table->string('url_artikel', 255)->nullable();
            $table->string('file_artikel', 255)->nullable();
            $table->boolean('is_verified_lppm')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publikasi_ilmiah');
    }
};
