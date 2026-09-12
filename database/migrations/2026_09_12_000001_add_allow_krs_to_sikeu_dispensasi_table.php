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
        if (Schema::hasTable('sikeu_dispensasi_tagihan')) {
            Schema::table('sikeu_dispensasi_tagihan', function (Blueprint $table) {
                if (!Schema::hasColumn('sikeu_dispensasi_tagihan', 'allow_krs')) {
                    $table->boolean('allow_krs')->default(true)->after('alasan');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sikeu_dispensasi_tagihan')) {
            Schema::table('sikeu_dispensasi_tagihan', function (Blueprint $table) {
                if (Schema::hasColumn('sikeu_dispensasi_tagihan', 'allow_krs')) {
                    $table->dropColumn('allow_krs');
                }
            });
        }
    }
};
