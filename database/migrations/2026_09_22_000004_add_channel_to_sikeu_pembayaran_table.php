<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pembayaran mahasiswa lintas kanal: unit kas sumber/diterima,
     * bukti transfer manual (mahasiswa upload), status rejected.
     */
    public function up(): void
    {
        if (Schema::hasTable('sikeu_pembayaran')) {
            Schema::table('sikeu_pembayaran', function (Blueprint $table) {
                if (!Schema::hasColumn('sikeu_pembayaran', 'unit_kas_id')) {
                    $table->foreignId('unit_kas_id')->nullable()->after('virtual_account_id')
                        ->constrained('sikeu_unit_kas')->onDelete('set null');
                }
                if (!Schema::hasColumn('sikeu_pembayaran', 'bukti_bayar_path')) {
                    $table->string('bukti_bayar_path')->nullable()->after('bank_pengirim');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE sikeu_pembayaran MODIFY status ENUM('success','pending','failed','reversed','rejected') NOT NULL DEFAULT 'success'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sikeu_pembayaran')) {
            Schema::table('sikeu_pembayaran', function (Blueprint $table) {
                if (Schema::hasColumn('sikeu_pembayaran', 'unit_kas_id')) {
                    $table->dropForeign(['unit_kas_id']);
                    $table->dropColumn('unit_kas_id');
                }
                if (Schema::hasColumn('sikeu_pembayaran', 'bukti_bayar_path')) {
                    $table->dropColumn('bukti_bayar_path');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE sikeu_pembayaran MODIFY status ENUM('success','pending','failed','reversed') NOT NULL DEFAULT 'success'");
            }
        }
    }
};
