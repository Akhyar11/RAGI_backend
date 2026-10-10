<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom JSON terstruktur untuk aktivitas Luring dan Daring pada RPS Sesi Pertemuan:
     * - aktivitas_luring (JSON array of { bentuk, metode, waktu_menit })
     * - aktivitas_daring (JSON array of { bentuk, metode, waktu_menit })
     */
    public function up(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_rps_mingguan', 'aktivitas_luring')) {
                $table->json('aktivitas_luring')->nullable()->after('bentuk_luring');
            }
            if (!Schema::hasColumn('siakad_rps_mingguan', 'aktivitas_daring')) {
                $table->json('aktivitas_daring')->nullable()->after('bentuk_daring');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_rps_mingguan', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_rps_mingguan', 'aktivitas_luring')) {
                $table->dropColumn('aktivitas_luring');
            }
            if (Schema::hasColumn('siakad_rps_mingguan', 'aktivitas_daring')) {
                $table->dropColumn('aktivitas_daring');
            }
        });
    }
};
