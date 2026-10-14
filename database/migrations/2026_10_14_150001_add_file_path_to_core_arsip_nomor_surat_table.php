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
        if (Schema::hasTable('core_arsip_nomor_surat') && !Schema::hasColumn('core_arsip_nomor_surat', 'file_path')) {
            Schema::table('core_arsip_nomor_surat', function (Blueprint $table) {
                $table->string('file_path', 255)->nullable()->after('kop_surat_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('core_arsip_nomor_surat') && Schema::hasColumn('core_arsip_nomor_surat', 'file_path')) {
            Schema::table('core_arsip_nomor_surat', function (Blueprint $table) {
                $table->dropColumn('file_path');
            });
        }
    }
};
