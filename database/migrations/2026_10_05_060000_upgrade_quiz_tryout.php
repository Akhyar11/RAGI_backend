<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tryout Fase C: quiz naik level — tipe tryout hidup di level KELAS
     * (bukan pertemuan), dengan kode akses opsional, arsip, dan peserta eksplisit.
     * Seluruh mesin attempt/autosave/submit/grading/OBE dipakai ulang (QuizService).
     */
    public function up(): void
    {
        Schema::table('lms_quiz', function (Blueprint $table) {
            if (!Schema::hasColumn('lms_quiz', 'kelas_id')) {
                $table->foreignId('kelas_id')->nullable()->after('pertemuan_id')
                    ->constrained('siakad_kelas')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('lms_quiz', 'tipe')) {
                $table->enum('tipe', ['kuis', 'tryout'])->default('kuis')->after('kelas_id');
            }
            if (!Schema::hasColumn('lms_quiz', 'kode_akses')) {
                $table->string('kode_akses', 20)->nullable()->after('is_published');
            }
            if (!Schema::hasColumn('lms_quiz', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('kode_akses');
            }
        });

        // Kuis existing wajib menunjuk pertemuan (tryout menunjuk kelas).
        DB::statement("UPDATE lms_quiz SET tipe = 'kuis' WHERE tipe IS NULL");

        Schema::table('lms_quiz', function (Blueprint $table) {
            $table->index(['kelas_id', 'tipe', 'is_published'], 'lms_quiz_kelas_tipe_pub_idx');
        });

        if (!Schema::hasTable('lms_tryout_peserta')) {
            Schema::create('lms_tryout_peserta', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_id')->constrained('lms_quiz')->cascadeOnDelete();
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->foreignId('ditambah_oleh')->nullable()->constrained('core_users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['quiz_id', 'mahasiswa_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_tryout_peserta');

        Schema::table('lms_quiz', function (Blueprint $table) {
            $table->dropIndex('lms_quiz_kelas_tipe_pub_idx');
            foreach (['is_archived', 'kode_akses', 'tipe', 'kelas_id'] as $column) {
                if (Schema::hasColumn('lms_quiz', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
