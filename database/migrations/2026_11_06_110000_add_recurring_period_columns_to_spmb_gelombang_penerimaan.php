<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->char('tanggal_buka_siklus', 5)->nullable()->after('tanggal_buka');
            $table->char('tanggal_tutup_siklus', 5)->nullable()->after('tanggal_tutup');
        });

        DB::table('spmb_gelombang_penerimaan')
            ->select(['id', 'tanggal_buka', 'tanggal_tutup'])
            ->orderBy('id')
            ->chunkById(500, function ($gelombangList) {
                foreach ($gelombangList as $gelombang) {
                    DB::table('spmb_gelombang_penerimaan')
                        ->where('id', $gelombang->id)
                        ->update([
                            'tanggal_buka_siklus' => $gelombang->tanggal_buka
                                ? date('m-d', strtotime($gelombang->tanggal_buka))
                                : null,
                            'tanggal_tutup_siklus' => $gelombang->tanggal_tutup
                                ? date('m-d', strtotime($gelombang->tanggal_tutup))
                                : null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->dropColumn(['tanggal_buka_siklus', 'tanggal_tutup_siklus']);
        });
    }
};
