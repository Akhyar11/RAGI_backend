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
        if (Schema::hasTable('core_arsip_nomor_surat')) {
            Schema::table('core_arsip_nomor_surat', function (Blueprint $table) {
                if (!Schema::hasColumn('core_arsip_nomor_surat', 'is_backdate')) {
                    $table->boolean('is_backdate')->default(false)->after('tanggal_surat')->index();
                }
                if (!Schema::hasColumn('core_arsip_nomor_surat', 'alasan_backdate')) {
                    $table->text('alasan_backdate')->nullable()->after('is_backdate');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('core_arsip_nomor_surat')) {
            Schema::table('core_arsip_nomor_surat', function (Blueprint $table) {
                if (Schema::hasColumn('core_arsip_nomor_surat', 'alasan_backdate')) {
                    $table->dropColumn('alasan_backdate');
                }
                if (Schema::hasColumn('core_arsip_nomor_surat', 'is_backdate')) {
                    $table->dropIndex(['core_arsip_nomor_surat_is_backdate_index']);
                    $table->dropColumn('is_backdate');
                }
            });
        }
    }
};
