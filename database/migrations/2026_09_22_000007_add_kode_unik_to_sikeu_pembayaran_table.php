<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kode unik transfer manual (1-499) sebagai pembeda antar pembayar agar
     * keuangan cepat menemukan mutasi bank: nominal_transfer = jumlah_bayar + kode_unik.
     * Kode hanya aktif selama pembayaran berstatus pending.
     */
    public function up(): void
    {
        if (Schema::hasTable('sikeu_pembayaran')) {
            Schema::table('sikeu_pembayaran', function (Blueprint $table) {
                if (!Schema::hasColumn('sikeu_pembayaran', 'kode_unik')) {
                    $table->unsignedSmallInteger('kode_unik')->nullable()->after('jumlah_bayar');
                    $table->index('kode_unik');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sikeu_pembayaran')) {
            Schema::table('sikeu_pembayaran', function (Blueprint $table) {
                if (Schema::hasColumn('sikeu_pembayaran', 'kode_unik')) {
                    $table->dropIndex(['kode_unik']);
                    $table->dropColumn('kode_unik');
                }
            });
        }
    }
};
