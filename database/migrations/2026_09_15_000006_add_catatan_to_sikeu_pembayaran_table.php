<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sikeu_pembayaran')) {
            return;
        }

        if (!Schema::hasColumn('sikeu_pembayaran', 'catatan')) {
            Schema::table('sikeu_pembayaran', function (Blueprint $table) {
                $table->text('catatan')->nullable()->after('bank_pengirim');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sikeu_pembayaran') && Schema::hasColumn('sikeu_pembayaran', 'catatan')) {
            Schema::table('sikeu_pembayaran', function (Blueprint $table) {
                $table->dropColumn('catatan');
            });
        }
    }
};
