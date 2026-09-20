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
        if (Schema::hasTable('sikeu_potongan_mahasiswa')) {
            Schema::table('sikeu_potongan_mahasiswa', function (Blueprint $table) {
                if (Schema::hasColumn('sikeu_potongan_mahasiswa', 'mahasiswa_id')) {
                    $table->unsignedBigInteger('mahasiswa_id')->nullable()->change();
                }
                if (!Schema::hasColumn('sikeu_potongan_mahasiswa', 'calon_mahasiswa_id')) {
                    $table->unsignedBigInteger('calon_mahasiswa_id')->nullable()->after('mahasiswa_id')->index();
                }
                if (!Schema::hasColumn('sikeu_potongan_mahasiswa', 'tipe_referensi')) {
                    $table->string('tipe_referensi', 30)->default('mahasiswa')->after('calon_mahasiswa_id');
                }
            });
        }

        if (Schema::hasTable('sikeu_potongan_tagihan')) {
            Schema::table('sikeu_potongan_tagihan', function (Blueprint $table) {
                if (!Schema::hasColumn('sikeu_potongan_tagihan', 'potongan_mahasiswa_id')) {
                    $table->unsignedBigInteger('potongan_mahasiswa_id')->nullable()->after('tagihan_id')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sikeu_potongan_tagihan')) {
            Schema::table('sikeu_potongan_tagihan', function (Blueprint $table) {
                if (Schema::hasColumn('sikeu_potongan_tagihan', 'potongan_mahasiswa_id')) {
                    $table->dropColumn('potongan_mahasiswa_id');
                }
            });
        }

        if (Schema::hasTable('sikeu_potongan_mahasiswa')) {
            Schema::table('sikeu_potongan_mahasiswa', function (Blueprint $table) {
                if (Schema::hasColumn('sikeu_potongan_mahasiswa', 'tipe_referensi')) {
                    $table->dropColumn('tipe_referensi');
                }
                if (Schema::hasColumn('sikeu_potongan_mahasiswa', 'calon_mahasiswa_id')) {
                    $table->dropColumn('calon_mahasiswa_id');
                }
                if (Schema::hasColumn('sikeu_potongan_mahasiswa', 'mahasiswa_id')) {
                    $table->unsignedBigInteger('mahasiswa_id')->nullable(false)->change();
                }
            });
        }
    }
};
