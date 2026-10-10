<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom jenis_pertemuan (Teori / Praktikum / referensi Jenis Pembelajaran)
     * dan sub_cpmk_ids (array/JSON jika mencentang lebih dari satu Sub-CPMK)
     * pada siakad_rps_mingguan.
     */
    public function up(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_rps_mingguan', 'jenis_pertemuan')) {
                $table->string('jenis_pertemuan', 100)->nullable()->after('minggu_ke');
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'sub_cpmk_ids')) {
                $table->json('sub_cpmk_ids')->nullable()->after('sub_cpmk_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_rps_mingguan', 'jenis_pertemuan')) {
                $table->dropColumn('jenis_pertemuan');
            }
            if (Schema::hasColumn('siakad_rps_mingguan', 'sub_cpmk_ids')) {
                $table->dropColumn('sub_cpmk_ids');
            }
        });
    }
};
