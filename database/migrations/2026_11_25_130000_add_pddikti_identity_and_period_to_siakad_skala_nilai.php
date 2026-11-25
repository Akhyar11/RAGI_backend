<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skala nilai per prodi dari Neo Feeder (GetListSkalaNilaiProdi): id_bobot_nilai sebagai identitas unik
 * dan masa berlaku tercatat. Baris tanpa id_feeder (skala standar lokal, program_studi_id NULL) tidak tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('siakad_skala_nilai')) {
            return;
        }

        Schema::table('siakad_skala_nilai', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_skala_nilai', 'id_feeder')) {
                $table->string('id_feeder', 100)->nullable()->after('program_studi_id')->unique('siakad_skala_nilai_id_feeder_unique');
            }
            if (!Schema::hasColumn('siakad_skala_nilai', 'tanggal_mulai_efektif')) {
                $table->date('tanggal_mulai_efektif')->nullable()->after('keterangan');
            }
            if (!Schema::hasColumn('siakad_skala_nilai', 'tanggal_akhir_efektif')) {
                $table->date('tanggal_akhir_efektif')->nullable()->after('tanggal_mulai_efektif');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('siakad_skala_nilai')) {
            return;
        }

        Schema::table('siakad_skala_nilai', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_skala_nilai', 'id_feeder')) {
                $table->dropUnique('siakad_skala_nilai_id_feeder_unique');
                $table->dropColumn('id_feeder');
            }
            foreach (['tanggal_mulai_efektif', 'tanggal_akhir_efektif'] as $column) {
                if (Schema::hasColumn('siakad_skala_nilai', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
