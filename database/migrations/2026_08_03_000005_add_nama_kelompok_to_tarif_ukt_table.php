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
        if (Schema::hasTable('tarif_ukt') && !Schema::hasColumn('tarif_ukt', 'nama_kelompok')) {
            Schema::table('tarif_ukt', function (Blueprint $table) {
                $table->string('nama_kelompok')->nullable()->after('kelompok_ukt');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tarif_ukt') && Schema::hasColumn('tarif_ukt', 'nama_kelompok')) {
            Schema::table('tarif_ukt', function (Blueprint $table) {
                $table->dropColumn('nama_kelompok');
            });
        }
    }
};
