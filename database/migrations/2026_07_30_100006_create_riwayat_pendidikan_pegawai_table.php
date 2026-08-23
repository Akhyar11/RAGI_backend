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
        Schema::create('simpeg_riwayat_pendidikan_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->cascadeOnDelete();
            $table->enum('jenjang', ['sma', 'd3', 'd4', 's1', 's2', 's3']);
            $table->string('nama_institusi');
            $table->string('program_studi')->nullable();
            $table->string('bidang_ilmu')->nullable();
            $table->integer('tahun_masuk')->nullable();
            $table->integer('tahun_lulus')->nullable();
            $table->string('nomor_ijazah')->nullable();
            $table->string('file_ijazah')->nullable();
            $table->boolean('is_pendidikan_terakhir')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_riwayat_pendidikan_pegawai');
    }
};
