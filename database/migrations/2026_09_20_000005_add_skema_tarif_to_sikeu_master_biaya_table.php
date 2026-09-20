<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sikeu_master_biaya') && !Schema::hasColumn('sikeu_master_biaya', 'skema_tarif')) {
            Schema::table('sikeu_master_biaya', function (Blueprint $table) {
                $table->string('skema_tarif', 20)->default('dinamis')->after('tipe');
            });

            // Backfill existing data
            DB::table('sikeu_master_biaya')
                ->whereIn('tipe', ['spmb_adm', 'wisuda', 'cuti'])
                ->orWhere('nominal_standar', '>', 0)
                ->update(['skema_tarif' => 'flat']);

            DB::table('sikeu_master_biaya')
                ->whereIn('tipe', ['spp', 'ukt', 'sks', 'praktikum'])
                ->where('nominal_standar', '<=', 0)
                ->update(['skema_tarif' => 'dinamis']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sikeu_master_biaya') && Schema::hasColumn('sikeu_master_biaya', 'skema_tarif')) {
            Schema::table('sikeu_master_biaya', function (Blueprint $table) {
                $table->dropColumn('skema_tarif');
            });
        }
    }
};
