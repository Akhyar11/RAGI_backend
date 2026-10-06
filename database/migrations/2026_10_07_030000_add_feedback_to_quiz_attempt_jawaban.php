<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Respon manual dosen untuk jawaban isian/uraian quiz.
     * Kolom nullable agar baris auto-grade (PG/isian) tetap valid tanpa feedback.
     */
    public function up(): void
    {
        Schema::table('lms_quiz_attempt_jawaban', function (Blueprint $table) {
            if (!Schema::hasColumn('lms_quiz_attempt_jawaban', 'feedback_dosen')) {
                $table->text('feedback_dosen')->nullable()->after('poin_didapat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lms_quiz_attempt_jawaban', function (Blueprint $table) {
            if (Schema::hasColumn('lms_quiz_attempt_jawaban', 'feedback_dosen')) {
                $table->dropColumn('feedback_dosen');
            }
        });
    }
};
