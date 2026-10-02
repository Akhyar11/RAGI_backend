<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda komponen biaya yang otomatis dimuat saat membuat master
     * biaya SPMB (per tipe jalur masuk + program studi).
     */
    public function up(): void
    {
        Schema::table('spmb_master_komponen_biaya', function (Blueprint $table) {
            $table->boolean('is_default_master_biaya')
                ->default(false)
                ->after('is_referral_reward')
                ->comment('true jika komponen otomatis dimuat di master biaya SPMB');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_master_komponen_biaya', function (Blueprint $table) {
            $table->dropColumn('is_default_master_biaya');
        });
    }
};
