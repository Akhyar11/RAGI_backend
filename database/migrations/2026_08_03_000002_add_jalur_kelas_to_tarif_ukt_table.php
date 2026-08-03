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
        if (Schema::hasTable('tarif_ukt') && !Schema::hasColumn('tarif_ukt', 'jalur_kelas')) {
            Schema::table('tarif_ukt', function (Blueprint $table) {
                $table->string('jalur_kelas')->default('Reguler')->after('tahun_angkatan');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tarif_ukt') && Schema::hasColumn('tarif_ukt', 'jalur_kelas')) {
            Schema::table('tarif_ukt', function (Blueprint $table) {
                $table->dropColumn('jalur_kelas');
            });
        }
    }
};
