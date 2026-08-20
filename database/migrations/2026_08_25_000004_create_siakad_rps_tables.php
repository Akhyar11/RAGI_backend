<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for SIAKAD RPS & Kurikulum OBE Approval.
     */
    public function up(): void
    {
        // 1. Header RPS (Rencana Pembelajaran Semester)
        Schema::create('siakad_rps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('siakad_mata_kuliah')->cascadeOnDelete();
            $table->string('tahun_ajaran', 20)->default('2026/2027');
            $table->integer('semester')->default(1);
            $table->foreignId('dosen_pengembang_id')->nullable()->constrained('siakad_dosen')->nullOnDelete();
            $table->foreignId('koordinator_rmk_id')->nullable()->constrained('siakad_dosen')->nullOnDelete();
            $table->foreignId('kaprodi_id')->nullable()->constrained('siakad_dosen')->nullOnDelete();
            $table->text('deskripsi_singkat')->nullable();
            $table->text('pustaka_utama')->nullable();
            $table->text('pustaka_pendukung')->nullable();
            $table->text('media_pembelajaran_software')->nullable();
            $table->text('media_pembelajaran_hardware')->nullable();
            $table->enum('status', ['draft', 'diajukan', 'disetujui', 'revisi'])->default('draft');
            $table->text('catatan_revisi')->nullable();
            $table->timestamp('disetujui_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Rincian Mingguan RPS (Pertemuan 1 - 16)
        Schema::create('siakad_rps_mingguan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rps_id')->constrained('siakad_rps')->cascadeOnDelete();
            $table->integer('minggu_ke'); // 1 - 16
            $table->text('kemampuan_akhir'); // Sub-CPMK
            $table->text('bahan_kajian'); // Materi
            $table->string('bentuk_metode')->default('Kuliah, Diskusi & Problem-Based Learning');
            $table->string('estimasi_waktu')->default('2x50 Menit');
            $table->text('pengalaman_belajar')->nullable();
            $table->text('indikator_penilaian')->nullable();
            $table->decimal('bobot_penilaian', 5, 2)->default(5.00); // %
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_rps_mingguan');
        Schema::dropIfExists('siakad_rps');
    }
};
