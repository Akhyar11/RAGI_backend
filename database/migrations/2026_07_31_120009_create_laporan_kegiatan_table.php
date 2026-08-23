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
        Schema::create('sippm_laporan_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kontrak_id')->constrained('sippm_kontrak_kegiatan')->cascadeOnDelete();
            $table->enum('jenis_laporan', ['kemajuan', 'akhir']);
            $table->string('file_laporan', 255);
            $table->string('file_logbook', 255)->nullable();
            $table->string('file_penggunaan_anggaran', 255)->nullable();
            $table->integer('persentase_capaian')->default(0);
            $table->enum('status_verifikasi', ['draft', 'diajukan', 'revisi', 'disetujui', 'ditolak'])->default('draft');
            $table->text('catatan_lppm')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sippm_laporan_kegiatan');
    }
};
