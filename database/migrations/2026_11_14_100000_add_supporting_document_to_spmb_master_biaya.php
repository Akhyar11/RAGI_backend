<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spmb_master_biaya') && ! Schema::hasColumn('spmb_master_biaya', 'dokumen_pendukung_path')) {
            Schema::table('spmb_master_biaya', function (Blueprint $table): void {
                $table->string('dokumen_pendukung_path')->nullable()->after('nomor_sk');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('spmb_master_biaya') && Schema::hasColumn('spmb_master_biaya', 'dokumen_pendukung_path')) {
            Schema::table('spmb_master_biaya', function (Blueprint $table): void {
                $table->dropColumn('dokumen_pendukung_path');
            });
        }
    }
};