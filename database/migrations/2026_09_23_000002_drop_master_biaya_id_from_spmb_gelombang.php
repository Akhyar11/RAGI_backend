<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mapping tarif SIKEU (master_biaya_id) dihapus dari master gelombang.
     * Tarif pendaftaran per gelombang+prodi diatur di master biaya SPMB
     * (spmb_master_biaya) yang dipetakan ke gelombang.
     */
    public function up(): void
    {
        if (!Schema::hasTable('spmb_gelombang_penerimaan')) {
            return;
        }

        if (!Schema::hasColumn('spmb_gelombang_penerimaan', 'master_biaya_id')) {
            return;
        }

        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->dropForeign(['master_biaya_id']);
            $table->dropColumn('master_biaya_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('spmb_gelombang_penerimaan')) {
            return;
        }

        if (Schema::hasColumn('spmb_gelombang_penerimaan', 'master_biaya_id')) {
            return;
        }

        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->foreignId('master_biaya_id')
                ->nullable()
                ->after('tahun_akademik_id')
                ->constrained('sikeu_master_biaya')
                ->nullOnDelete();
        });
    }
};
