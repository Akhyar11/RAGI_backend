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
        if (Schema::hasTable('tarif_ukt') && !Schema::hasColumn('tarif_ukt', 'tahun_angkatan')) {
            Schema::table('tarif_ukt', function (Blueprint $table) {
                $table->integer('tahun_angkatan')->nullable()->after('tahun_akademik_id');
            });
        }

        if (Schema::hasTable('unit_kas') && !Schema::hasColumn('unit_kas', 'is_kabag_kas')) {
            Schema::table('unit_kas', function (Blueprint $table) {
                $table->boolean('is_kabag_kas')->default(false)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tarif_ukt') && Schema::hasColumn('tarif_ukt', 'tahun_angkatan')) {
            Schema::table('tarif_ukt', function (Blueprint $table) {
                $table->dropColumn('tahun_angkatan');
            });
        }

        if (Schema::hasTable('unit_kas') && Schema::hasColumn('unit_kas', 'is_kabag_kas')) {
            Schema::table('unit_kas', function (Blueprint $table) {
                $table->dropColumn('is_kabag_kas');
            });
        }
    }
};
