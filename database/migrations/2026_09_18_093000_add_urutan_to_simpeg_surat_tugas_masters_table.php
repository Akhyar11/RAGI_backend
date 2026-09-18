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
        if (Schema::hasTable('simpeg_master_jenis_transportasi') && !Schema::hasColumn('simpeg_master_jenis_transportasi', 'urutan')) {
            Schema::table('simpeg_master_jenis_transportasi', function (Blueprint $table) {
                $table->integer('urutan')->default(1)->after('deskripsi');
            });
        }

        if (Schema::hasTable('simpeg_master_kategori_kegiatan_tugas') && !Schema::hasColumn('simpeg_master_kategori_kegiatan_tugas', 'urutan')) {
            Schema::table('simpeg_master_kategori_kegiatan_tugas', function (Blueprint $table) {
                $table->integer('urutan')->default(1)->after('deskripsi');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('simpeg_master_jenis_transportasi') && Schema::hasColumn('simpeg_master_jenis_transportasi', 'urutan')) {
            Schema::table('simpeg_master_jenis_transportasi', function (Blueprint $table) {
                $table->dropColumn('urutan');
            });
        }

        if (Schema::hasTable('simpeg_master_kategori_kegiatan_tugas') && Schema::hasColumn('simpeg_master_kategori_kegiatan_tugas', 'urutan')) {
            Schema::table('simpeg_master_kategori_kegiatan_tugas', function (Blueprint $table) {
                $table->dropColumn('urutan');
            });
        }
    }
};
