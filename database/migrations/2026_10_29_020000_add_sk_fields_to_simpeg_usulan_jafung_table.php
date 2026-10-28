<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simpeg_usulan_jafung')) {
            Schema::table('simpeg_usulan_jafung', function (Blueprint $table) {
                if (!Schema::hasColumn('simpeg_usulan_jafung', 'nomor_sk')) {
                    $table->string('nomor_sk', 150)->nullable()->after('jafung_tujuan_id')->index();
                }
                if (!Schema::hasColumn('simpeg_usulan_jafung', 'tanggal_mulai_berlaku')) {
                    $table->date('tanggal_mulai_berlaku')->nullable()->after('nomor_sk')->index();
                }
                if (Schema::hasColumn('simpeg_usulan_jafung', 'angka_kredit_usulan')) {
                    $table->integer('angka_kredit_usulan')->default(0)->change();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('simpeg_usulan_jafung')) {
            Schema::table('simpeg_usulan_jafung', function (Blueprint $table) {
                if (Schema::hasColumn('simpeg_usulan_jafung', 'nomor_sk')) {
                    $table->dropIndex(['nomor_sk']);
                    $table->dropColumn('nomor_sk');
                }
                if (Schema::hasColumn('simpeg_usulan_jafung', 'tanggal_mulai_berlaku')) {
                    $table->dropIndex(['tanggal_mulai_berlaku']);
                    $table->dropColumn('tanggal_mulai_berlaku');
                }
            });
        }
    }
};
