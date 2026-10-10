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
        if (Schema::hasTable('simpeg_pegawai')) {
            Schema::table('simpeg_pegawai', function (Blueprint $table) {
                if (!Schema::hasColumn('simpeg_pegawai', 'terdampak_absensi')) {
                    $table->boolean('terdampak_absensi')->default(true)->after('is_active');
                }
            });
        }

        if (Schema::hasTable('simpeg_master_komponen_gaji')) {
            Schema::table('simpeg_master_komponen_gaji', function (Blueprint $table) {
                if (!Schema::hasColumn('simpeg_master_komponen_gaji', 'is_kolom_aktif')) {
                    $table->boolean('is_kolom_aktif')->default(true)->after('is_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('simpeg_pegawai')) {
            Schema::table('simpeg_pegawai', function (Blueprint $table) {
                if (Schema::hasColumn('simpeg_pegawai', 'terdampak_absensi')) {
                    $table->dropColumn('terdampak_absensi');
                }
            });
        }

        if (Schema::hasTable('simpeg_master_komponen_gaji')) {
            Schema::table('simpeg_master_komponen_gaji', function (Blueprint $table) {
                if (Schema::hasColumn('simpeg_master_komponen_gaji', 'is_kolom_aktif')) {
                    $table->dropColumn('is_kolom_aktif');
                }
            });
        }
    }
};
