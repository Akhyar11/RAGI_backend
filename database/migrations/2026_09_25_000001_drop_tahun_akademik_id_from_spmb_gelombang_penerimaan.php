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
        if (!Schema::hasTable('spmb_gelombang_penerimaan')) {
            return;
        }

        if (!Schema::hasColumn('spmb_gelombang_penerimaan', 'tahun_akademik_id')) {
            return;
        }

        try {
            Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
                $table->dropForeign(['tahun_akademik_id']);
            });
        } catch (\Throwable $e) {
            // Foreign key might not exist.
        }

        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->dropColumn('tahun_akademik_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('spmb_gelombang_penerimaan')) {
            return;
        }

        if (Schema::hasColumn('spmb_gelombang_penerimaan', 'tahun_akademik_id')) {
            return;
        }

        Schema::table('spmb_gelombang_penerimaan', function (Blueprint $table) {
            $table->foreignId('tahun_akademik_id')->nullable()->after('jalur_masuk_id');
        });
    }
};
