<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom penunjang section Penilaian pada RPS Sesi Pertemuan:
     * - komponen_evaluasi_id (FK siakad_rps_referensi tipe komponen)
     * - kriteria_penilaian_id (FK siakad_rps_referensi tipe kriteria)
     * - teknik_penilaian (Textarea teknik penilaian)
     */
    public function up(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_rps_mingguan', 'komponen_evaluasi_id')) {
                $table->foreignId('komponen_evaluasi_id')->nullable()->after('sub_cpmk_ids')->constrained('siakad_rps_referensi')->nullOnDelete();
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'kriteria_penilaian_id')) {
                $table->foreignId('kriteria_penilaian_id')->nullable()->after('indikator_penilaian')->constrained('siakad_rps_referensi')->nullOnDelete();
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'teknik_penilaian')) {
                $table->text('teknik_penilaian')->nullable()->after('kriteria_penilaian_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_rps_mingguan', 'komponen_evaluasi_id')) {
                $table->dropConstrainedForeignId('komponen_evaluasi_id');
            }
            if (Schema::hasColumn('siakad_rps_mingguan', 'kriteria_penilaian_id')) {
                $table->dropConstrainedForeignId('kriteria_penilaian_id');
            }
            if (Schema::hasColumn('siakad_rps_mingguan', 'teknik_penilaian')) {
                $table->dropColumn('teknik_penilaian');
            }
        });
    }
};
