<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quiz LMS Fase B (batch-loading ala Project/lms, OBE-sync ala tugas).
     * Soal quiz WAJIB mereferensikan master `siakad_bank_soal` (live, tanpa copy/duplikasi):
     * - bank_soal_id RESTRICT agar bank soal yang dipakai quiz aktif tidak bisa dihapus diam-diam
     *   (dihalangi ramah via guard 422 di ObeController::deleteSoal).
     * - Struktur quiz dikunci (422) setelah attempt pertama ada — menjaga integritas riwayat.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_quiz')) {
            Schema::create('lms_quiz', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pertemuan_id')->constrained('siakad_pertemuan')->cascadeOnDelete();
                // Link opsional ke OBE — bila diisi, nilai akhir attempt auto-sync ke OBE.
                $table->foreignId('komponen_penilaian_id')->nullable()
                    ->constrained('siakad_komponen_penilaian')->nullOnDelete();
                $table->string('judul');
                $table->longText('deskripsi')->nullable();
                $table->integer('durasi_menit')->nullable();
                $table->integer('max_attempt')->default(1);
                $table->boolean('acak_soal')->default(true);
                $table->boolean('acak_jawaban')->default(true);
                // NULL = pakai setting global lms_quiz_batch_size.
                $table->integer('batch_size')->nullable();
                $table->dateTime('dibuka_at')->nullable();
                $table->dateTime('ditutup_at')->nullable();
                $table->boolean('is_published')->default(false);
                $table->foreignId('dibuat_oleh')->nullable()->constrained('core_users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['pertemuan_id', 'is_published']);
            });
        }

        if (!Schema::hasTable('lms_quiz_soal')) {
            Schema::create('lms_quiz_soal', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_id')->constrained('lms_quiz')->cascadeOnDelete();
                $table->foreignId('bank_soal_id')->constrained('siakad_bank_soal')->restrictOnDelete();
                $table->integer('urutan')->default(0);
                $table->decimal('poin', 5, 2)->default(1);
                $table->timestamps();

                $table->unique(['quiz_id', 'bank_soal_id']);
                $table->index(['quiz_id', 'urutan']);
            });
        }

        if (!Schema::hasTable('lms_quiz_attempt')) {
            Schema::create('lms_quiz_attempt', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_id')->constrained('lms_quiz')->cascadeOnDelete();
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->integer('attempt_ke')->default(1);
                $table->enum('status', ['berlangsung', 'selesai'])->default('berlangsung');
                $table->dateTime('dimulai_at');
                $table->dateTime('disubmit_at')->nullable();
                $table->decimal('nilai_akhir', 5, 2)->nullable();
                $table->boolean('butuh_penilaian_manual')->default(false);
                $table->foreignId('dinilai_oleh')->nullable()->constrained('core_users')->nullOnDelete();
                $table->timestamp('dinilai_at')->nullable();
                $table->timestamps();

                $table->unique(['quiz_id', 'mahasiswa_id', 'attempt_ke']);
                $table->index(['quiz_id', 'status']);
            });
        }

        if (!Schema::hasTable('lms_quiz_attempt_jawaban')) {
            Schema::create('lms_quiz_attempt_jawaban', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attempt_id')->constrained('lms_quiz_attempt')->cascadeOnDelete();
                $table->foreignId('quiz_soal_id')->constrained('lms_quiz_soal')->cascadeOnDelete();
                $table->foreignId('bank_opsi_id')->nullable()->constrained('siakad_bank_soal_opsi')->nullOnDelete();
                $table->text('jawaban_teks')->nullable();
                // NULL = belum dinilai (uraian menunggu dosen).
                $table->boolean('is_benar')->nullable();
                $table->decimal('poin_didapat', 5, 2)->default(0);
                $table->timestamps();

                $table->unique(['attempt_id', 'quiz_soal_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_quiz_attempt_jawaban');
        Schema::dropIfExists('lms_quiz_attempt');
        Schema::dropIfExists('lms_quiz_soal');
        Schema::dropIfExists('lms_quiz');
    }
};
