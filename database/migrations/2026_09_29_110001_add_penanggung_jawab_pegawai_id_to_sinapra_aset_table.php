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
        if (Schema::hasTable('sinapra_aset')) {
            Schema::table('sinapra_aset', function (Blueprint $table) {
                if (!Schema::hasColumn('sinapra_aset', 'penanggung_jawab_pegawai_id')) {
                    $table->unsignedBigInteger('penanggung_jawab_pegawai_id')->nullable()->after('ruangan_id');
                    $table->foreign('penanggung_jawab_pegawai_id', 'fk_sinapra_aset_pj_pegawai')
                        ->references('id')
                        ->on('simpeg_pegawai')
                        ->nullOnDelete();
                    $table->index('penanggung_jawab_pegawai_id', 'idx_sinapra_aset_pj');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sinapra_aset')) {
            Schema::table('sinapra_aset', function (Blueprint $table) {
                if (Schema::hasColumn('sinapra_aset', 'penanggung_jawab_pegawai_id')) {
                    $table->dropForeign(['penanggung_jawab_pegawai_id']);
                    $table->dropColumn('penanggung_jawab_pegawai_id');
                }
            });
        }
    }
};
