<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_master_komponen_biaya', function (Blueprint $table): void {
            $table->string('payment_gateway_name', 50)->nullable()->after('payment_gateway_config_id');
        });

        DB::table('spmb_master_komponen_biaya as component')
            ->join('sikeu_payment_gateway_configs as gateway', 'gateway.id', '=', 'component.payment_gateway_config_id')
            ->select(['component.id', 'gateway.gateway_name'])
            ->get()
            ->each(fn (object $mapping) => DB::table('spmb_master_komponen_biaya')
                ->where('id', $mapping->id)
                ->update(['payment_gateway_name' => $mapping->gateway_name]));
    }

    public function down(): void
    {
        Schema::table('spmb_master_komponen_biaya', function (Blueprint $table): void {
            $table->dropColumn('payment_gateway_name');
        });
    }
};