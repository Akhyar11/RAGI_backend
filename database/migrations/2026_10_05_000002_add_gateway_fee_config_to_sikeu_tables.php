<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konfigurasi biaya payment gateway (Xendit) + pencatatan fee per pembayaran.
     */
    public function up(): void
    {
        if (Schema::hasTable('sikeu_payment_gateway_configs')) {
            Schema::table('sikeu_payment_gateway_configs', function (Blueprint $table) {
                if (! Schema::hasColumn('sikeu_payment_gateway_configs', 'va_fee')) {
                    $table->decimal('va_fee', 15, 2)->default(0)->after('gateway_name')->comment('Biaya tetap VA per transaksi (excl. PPN)');
                }
                if (! Schema::hasColumn('sikeu_payment_gateway_configs', 'vat_percent')) {
                    $table->decimal('vat_percent', 5, 2)->default(11)->after('va_fee')->comment('Persentase PPN atas biaya gateway');
                }
                if (! Schema::hasColumn('sikeu_payment_gateway_configs', 'charge_fee_to_payer')) {
                    $table->boolean('charge_fee_to_payer')->default(false)->after('vat_percent')->comment('true = biaya gateway dibebankan ke pendaftar');
                }
            });
        }

        if (Schema::hasTable('sikeu_pembayaran') && ! Schema::hasColumn('sikeu_pembayaran', 'fee_amount')) {
            Schema::table('sikeu_pembayaran', function (Blueprint $table) {
                $table->decimal('fee_amount', 15, 2)->default(0)->after('jumlah_bayar')->comment('Biaya payment gateway (fee + PPN) per pembayaran');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sikeu_pembayaran') && Schema::hasColumn('sikeu_pembayaran', 'fee_amount')) {
            Schema::table('sikeu_pembayaran', function (Blueprint $table) {
                $table->dropColumn('fee_amount');
            });
        }

        if (Schema::hasTable('sikeu_payment_gateway_configs')) {
            Schema::table('sikeu_payment_gateway_configs', function (Blueprint $table) {
                $table->dropColumn(['va_fee', 'vat_percent', 'charge_fee_to_payer']);
            });
        }
    }
};
