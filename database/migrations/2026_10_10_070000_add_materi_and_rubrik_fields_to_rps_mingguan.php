<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom penunjang section Materi Pembelajaran & Rubrik pada RPS Sesi Pertemuan:
     * - topik_materi (text)
     * - sub_topik_materi (text)
     * - pustaka_ids (JSON array of id/referensi pustaka)
     * - rubrik_id (FK siakad_obe_rubrik)
     */
    public function up(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_rps_mingguan', 'topik_materi')) {
                $table->text('topik_materi')->nullable()->after('bahan_kajian');
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'sub_topik_materi')) {
                $table->text('sub_topik_materi')->nullable()->after('topik_materi');
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'pustaka_ids')) {
                $table->json('pustaka_ids')->nullable()->after('sub_topik_materi');
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'rubrik_id')) {
                $table->foreignId('rubrik_id')->nullable()->after('kriteria_penilaian_id')->constrained('siakad_obe_rubrik')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_rps_mingguan', 'rubrik_id')) {
                $table->dropConstrainedForeignId('rubrik_id');
            }
            foreach (['topik_materi', 'sub_topik_materi', 'pustaka_ids'] as $col) {
                if (Schema::hasColumn('siakad_rps_mingguan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
