<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referensi billing bridge H2H BTN Syariah (Go) pada tagihan:
     * h2h_billing_id = va_billings.id (NomorPembayaran+IdTagihan),
     * h2h_id_tagihan = va_billings.id_tagihan.
     */
    public function up(): void
    {
        Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
            $table->string('h2h_billing_id', 60)->nullable()->after('nomor_tagihan');
            $table->string('h2h_id_tagihan', 20)->nullable()->after('h2h_billing_id');
            $table->index('h2h_billing_id');
        });
    }

    public function down(): void
    {
        Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
            $table->dropIndex(['h2h_billing_id']);
            $table->dropColumn(['h2h_billing_id', 'h2h_id_tagihan']);
        });
    }
};
