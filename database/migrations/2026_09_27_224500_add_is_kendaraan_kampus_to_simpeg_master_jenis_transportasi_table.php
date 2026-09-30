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
        if (Schema::hasTable('simpeg_master_jenis_transportasi') && !Schema::hasColumn('simpeg_master_jenis_transportasi', 'is_kendaraan_kampus')) {
            Schema::table('simpeg_master_jenis_transportasi', function (Blueprint $table) {
                $table->boolean('is_kendaraan_kampus')->default(false)->after('kode');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('simpeg_master_jenis_transportasi') && Schema::hasColumn('simpeg_master_jenis_transportasi', 'is_kendaraan_kampus')) {
            Schema::table('simpeg_master_jenis_transportasi', function (Blueprint $table) {
                $table->dropColumn('is_kendaraan_kampus');
            });
        }
    }
};
