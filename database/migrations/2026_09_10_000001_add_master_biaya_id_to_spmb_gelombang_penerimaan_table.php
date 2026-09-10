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
        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->foreignId('master_biaya_id')
                ->nullable()
                ->after('tahun_akademik_id')
                ->constrained('sikeu_master_biaya')
                ->nullOnDelete();
        });

        // Set default master_biaya_id untuk data gelombang yang sudah ada jika ada master biaya SPMB_ADM
        $spmbAdm = DB::table('sikeu_master_biaya')->where('kode', 'SPMB_ADM')->first();
        if ($spmbAdm) {
            DB::table('spmb_gelombang_penerimaan')
                ->whereNull('master_biaya_id')
                ->update(['master_biaya_id' => $spmbAdm->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->dropForeign(['master_biaya_id']);
            $table->dropColumn('master_biaya_id');
        });
    }
};
