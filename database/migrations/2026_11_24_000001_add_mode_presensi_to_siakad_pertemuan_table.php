<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mode presensi per pertemuan.
     *
     * Default `online` sengaja dipilih agar geofence TIDAK aktif untuk
     * kelas Zoom/daring maupun data lama. Dosen dapat memilih `offline`
     * saat membuka sesi tatap muka; geofence tetap baru dipaksa bila
     * administrator mengaktifkan `lms_presensi_geofence_wajib` dan
     * lokasi kampus (simpeg_office_locations) tersedia.
     */
    public function up(): void
    {
        Schema::table('siakad_pertemuan', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_pertemuan', 'mode_presensi')) {
                $table->string('mode_presensi', 20)
                    ->default('online')
                    ->after('presensi_closed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_pertemuan', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_pertemuan', 'mode_presensi')) {
                $table->dropColumn('mode_presensi');
            }
        });
    }
};
