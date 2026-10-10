<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom pendukung section Sesi Pertemuan pada dokumen RPS:
     * Sub-CPMK rujukan, kriteria & teknik penilaian, aktivitas
     * luring/daring, serta penugasan mahasiswa.
     */
    public function up(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_rps_mingguan', 'sub_cpmk_id')) {
                $table->foreignId('sub_cpmk_id')->nullable()->after('rps_id')->constrained('siakad_sub_cpmk')->nullOnDelete();
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'kriteria_teknik')) {
                $table->text('kriteria_teknik')->nullable()->after('indikator_penilaian');
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'bentuk_luring')) {
                $table->text('bentuk_luring')->nullable()->after('bentuk_metode');
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'bentuk_daring')) {
                $table->text('bentuk_daring')->nullable()->after('bentuk_luring');
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'penugasan_mahasiswa')) {
                $table->text('penugasan_mahasiswa')->nullable()->after('pengalaman_belajar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_rps_mingguan', 'sub_cpmk_id')) {
                $table->dropConstrainedForeignId('sub_cpmk_id');
            }
            foreach (['kriteria_teknik', 'bentuk_luring', 'bentuk_daring', 'penugasan_mahasiswa'] as $col) {
                if (Schema::hasColumn('siakad_rps_mingguan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
