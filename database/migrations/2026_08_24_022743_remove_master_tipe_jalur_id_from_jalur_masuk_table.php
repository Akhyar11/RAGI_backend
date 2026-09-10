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
        Schema::table('spmb_jalur_masuk', function (Blueprint $table) {
            if (Schema::hasColumn('spmb_jalur_masuk', 'master_tipe_jalur_id')) {
                $table->dropForeign(['master_tipe_jalur_id']);
                $table->dropColumn('master_tipe_jalur_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spmb_jalur_masuk', function (Blueprint $table) {
            $table->foreignId('master_tipe_jalur_id')->nullable()->after('deskripsi')
                ->constrained('core_master_tipe_jalur')->nullOnDelete();
        });
    }
};
