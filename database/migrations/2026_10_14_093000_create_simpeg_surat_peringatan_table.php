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
        Schema::create('simpeg_surat_peringatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->cascadeOnDelete();
            $table->foreignId('gaji_pegawai_id')->nullable()->constrained('simpeg_gaji_pegawai')->nullOnDelete();
            $table->foreignId('presensi_periode_id')->nullable()->constrained('simpeg_presensi_periode')->nullOnDelete();
            $table->string('nomor_sp')->unique();
            $table->string('jenis_sp')->default('SP 1');
            $table->string('judul');
            $table->string('periode_bulan_tahun', 7); // e.g. 2026-10
            $table->integer('total_terlambat')->default(0);
            $table->integer('batas_maksimal')->default(5);
            $table->text('keterangan')->nullable();
            $table->date('tanggal_terbit');
            $table->string('status', 30)->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pegawai_id', 'periode_bulan_tahun']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_surat_peringatan');
    }
};
