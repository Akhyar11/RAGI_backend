<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah kolom semester pada tagihan mahasiswa agar tagihan dapat
     * dibedakan per semester dan duplikasi penagihan dapat dicegah.
     * Backfill dari catatan_approval ("... Semester N").
     */
    public function up(): void
    {
        Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester')->nullable()->after('tahun_akademik_id');
            $table->index(['mahasiswa_id', 'semester', 'tahun_akademik_id'], 'tagihan_mhs_semester_ta_index');
        });

        DB::table('sikeu_tagihan_mahasiswa')
            ->whereNull('semester')
            ->whereNotNull('catatan_approval')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (preg_match('/semester\s+(\d{1,2})/i', (string) $row->catatan_approval, $m)) {
                        DB::table('sikeu_tagihan_mahasiswa')
                            ->where('id', $row->id)
                            ->update(['semester' => (int) $m[1]]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
            $table->dropIndex('tagihan_mhs_semester_ta_index');
            $table->dropColumn('semester');
        });
    }
};
