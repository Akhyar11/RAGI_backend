<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai ambang kelulusan tryout/kuis.
     *
     * NULL = tidak ada ambang (status kelulusan tidak ditampilkan).
     * Skala mengikuti `lms_quiz_attempt.nilai_akhir` (0–100, dua desimal).
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_quiz') || Schema::hasColumn('lms_quiz', 'passing_score')) {
            return;
        }

        Schema::table('lms_quiz', function (Blueprint $table) {
            $table->decimal('passing_score', 5, 2)->nullable()->after('max_attempt');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lms_quiz') || !Schema::hasColumn('lms_quiz', 'passing_score')) {
            return;
        }

        Schema::table('lms_quiz', function (Blueprint $table) {
            $table->dropColumn('passing_score');
        });
    }
};
