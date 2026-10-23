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
        if (Schema::hasTable('siakad_paket_mk_rombel')) {
            Schema::table('siakad_paket_mk_rombel', function (Blueprint $table) {
                if (!Schema::hasColumn('siakad_paket_mk_rombel', 'nomor_surat_id')) {
                    $table->foreignId('nomor_surat_id')
                        ->nullable()
                        ->after('tahun_akademik_id')
                        ->constrained('core_arsip_nomor_surat')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('siakad_paket_mk_rombel', 'nomor_sk')) {
                    $table->string('nomor_sk', 150)->nullable()->after('nomor_surat_id')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('siakad_paket_mk_rombel')) {
            Schema::table('siakad_paket_mk_rombel', function (Blueprint $table) {
                if (Schema::hasColumn('siakad_paket_mk_rombel', 'nomor_surat_id')) {
                    $table->dropForeign(['nomor_surat_id']);
                    $table->dropColumn('nomor_surat_id');
                }
                if (Schema::hasColumn('siakad_paket_mk_rombel', 'nomor_sk')) {
                    $table->dropIndex(['nomor_sk']);
                    $table->dropColumn('nomor_sk');
                }
            });
        }
    }
};
