<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('simpeg_presensi_periode', function (Blueprint $table) {
            $table->string('status_pembayaran', 30)->default('belum_diproses')->after('is_locked');
        });

        // Sinkronisasi status_pembayaran dari batch SIKEU yang sudah ada
        $paidPeriods = DB::table('sikeu_pengajuan_pencairan_kas')
            ->where('sumber_type', 'gaji_simpeg')
            ->where('status', 'dicairkan')
            ->pluck('sumber_id')
            ->filter()
            ->all();

        if (!empty($paidPeriods)) {
            DB::table('simpeg_presensi_periode')
                ->whereIn('bulan_tahun', $paidPeriods)
                ->update([
                    'status_pembayaran' => 'paid',
                    'is_locked' => true,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_presensi_periode', function (Blueprint $table) {
            $table->dropColumn('status_pembayaran');
        });
    }
};
