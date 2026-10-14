<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('spmb_master_biaya') && !Schema::hasColumn('spmb_master_biaya', 'nomor_sk')) {
            Schema::table('spmb_master_biaya', function (Blueprint $table) {
                $table->string('nomor_sk', 150)->nullable()->after('master_tipe_jalur_id')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('spmb_master_biaya') && Schema::hasColumn('spmb_master_biaya', 'nomor_sk')) {
            Schema::table('spmb_master_biaya', function (Blueprint $table) {
                $table->dropIndex(['nomor_sk']);
                $table->dropColumn('nomor_sk');
            });
        }
    }
};
