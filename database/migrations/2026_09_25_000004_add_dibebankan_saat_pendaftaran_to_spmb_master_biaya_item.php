<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda apakah sebuah komponen biaya dibebankan sebagai beban awal
     * saat pendaftaran (true) atau baru dibebankan saat daftar ulang (false).
     */
    public function up(): void
    {
        if (!Schema::hasTable('spmb_master_biaya_item')) {
            return;
        }

        if (Schema::hasColumn('spmb_master_biaya_item', 'dibebankan_saat_pendaftaran')) {
            return;
        }

        Schema::table('spmb_master_biaya_item', function (Blueprint $table) {
            $table->boolean('dibebankan_saat_pendaftaran')
                ->default(false)
                ->after('nominal')
                ->comment('true = beban awal saat pendaftaran; false = dibebankan saat daftar ulang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('spmb_master_biaya_item')) {
            return;
        }

        if (!Schema::hasColumn('spmb_master_biaya_item', 'dibebankan_saat_pendaftaran')) {
            return;
        }

        Schema::table('spmb_master_biaya_item', function (Blueprint $table) {
            $table->dropColumn('dibebankan_saat_pendaftaran');
        });
    }
};
