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
        if (Schema::hasTable('sikeu_tagihan_mahasiswa')) {
            Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
                $table->index('mahasiswa_id', 'sikeu_tagihan_mahasiswa_id_idx');
                $table->index('status', 'sikeu_tagihan_status_idx');
                $table->index(['mahasiswa_id', 'status'], 'sikeu_tagihan_mhs_status_idx');
            });
        }

        if (Schema::hasTable('sikeu_dispensasi_tagihan')) {
            Schema::table('sikeu_dispensasi_tagihan', function (Blueprint $table) {
                $table->index('mahasiswa_id', 'sikeu_disp_mahasiswa_id_idx');
                $table->index('tagihan_id', 'sikeu_disp_tagihan_id_idx');
                $table->index('status', 'sikeu_disp_status_idx');
            });
        }

        if (Schema::hasTable('sikeu_pembayaran_mahasiswa')) {
            Schema::table('sikeu_pembayaran_mahasiswa', function (Blueprint $table) {
                $table->index('tagihan_id', 'sikeu_bayar_tagihan_id_idx');
                $table->index('status', 'sikeu_bayar_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sikeu_tagihan_mahasiswa')) {
            Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
                $table->dropIndex('sikeu_tagihan_mahasiswa_id_idx');
                $table->dropIndex('sikeu_tagihan_status_idx');
                $table->dropIndex('sikeu_tagihan_mhs_status_idx');
            });
        }

        if (Schema::hasTable('sikeu_dispensasi_tagihan')) {
            Schema::table('sikeu_dispensasi_tagihan', function (Blueprint $table) {
                $table->dropIndex('sikeu_disp_mahasiswa_id_idx');
                $table->dropIndex('sikeu_disp_tagihan_id_idx');
                $table->dropIndex('sikeu_disp_status_idx');
            });
        }

        if (Schema::hasTable('sikeu_pembayaran_mahasiswa')) {
            Schema::table('sikeu_pembayaran_mahasiswa', function (Blueprint $table) {
                $table->dropIndex('sikeu_bayar_tagihan_id_idx');
                $table->dropIndex('sikeu_bayar_status_idx');
            });
        }
    }
};
