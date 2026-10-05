<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simpan ID Virtual Account dari Xendit (dipakai untuk simulasi pembayaran
     * di mode tes: POST /fixed_virtual_accounts/{id}/simulate).
     */
    public function up(): void
    {
        if (Schema::hasTable('sikeu_virtual_account') && ! Schema::hasColumn('sikeu_virtual_account', 'xendit_va_id')) {
            Schema::table('sikeu_virtual_account', function (Blueprint $table) {
                $table->string('xendit_va_id')->nullable()->after('va_number')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sikeu_virtual_account') && Schema::hasColumn('sikeu_virtual_account', 'xendit_va_id')) {
            Schema::table('sikeu_virtual_account', function (Blueprint $table) {
                $table->dropColumn('xendit_va_id');
            });
        }
    }
};
