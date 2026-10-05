<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presensi hardening Fase A (anti titip-hadir ala Project/lms):
     * - window_menit: jendela sesi presensi per pertemuan (menit).
     * - token_rotated_at: penanda terakhir token diputar ulang (rotasi cepat).
     * - presensi_closed_at: sesi presensi ditutup dosen — input token ditolak.
     */
    public function up(): void
    {
        Schema::table('siakad_pertemuan', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_pertemuan', 'window_menit')) {
                $table->integer('window_menit')->default(30)->after('token_expired_at');
            }
            if (!Schema::hasColumn('siakad_pertemuan', 'token_rotated_at')) {
                $table->timestamp('token_rotated_at')->nullable()->after('window_menit');
            }
            if (!Schema::hasColumn('siakad_pertemuan', 'presensi_closed_at')) {
                $table->timestamp('presensi_closed_at')->nullable()->after('token_rotated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_pertemuan', function (Blueprint $table) {
            foreach (['presensi_closed_at', 'token_rotated_at', 'window_menit'] as $column) {
                if (Schema::hasColumn('siakad_pertemuan', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
