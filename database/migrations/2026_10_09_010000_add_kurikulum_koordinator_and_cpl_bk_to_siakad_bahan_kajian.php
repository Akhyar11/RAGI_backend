<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penetapan Bahan Kajian (BK):
     * - Tambahkan konteks kurikulum & dosen koordinator pada `siakad_bahan_kajian`.
     * - Pivot CPL ↔ BK untuk matriks pemetaan CPL-BK.
     */
    public function up(): void
    {
        if (Schema::hasTable('siakad_bahan_kajian')) {
            Schema::table('siakad_bahan_kajian', function (Blueprint $table) {
                if (!Schema::hasColumn('siakad_bahan_kajian', 'kurikulum_id')) {
                    $table->foreignId('kurikulum_id')
                        ->nullable()
                        ->after('program_studi_id')
                        ->constrained('siakad_kurikulum')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('siakad_bahan_kajian', 'koordinator_id')) {
                    $table->foreignId('koordinator_id')
                        ->nullable()
                        ->after('nama_bk')
                        ->constrained('siakad_dosen')
                        ->nullOnDelete();
                }
            });
        }

        if (!Schema::hasTable('siakad_cpl_bahan_kajian')) {
            Schema::create('siakad_cpl_bahan_kajian', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cpl_id')->constrained('siakad_cpl')->cascadeOnDelete();
                $table->foreignId('bahan_kajian_id')->constrained('siakad_bahan_kajian')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['cpl_id', 'bahan_kajian_id'], 'cpl_bk_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_cpl_bahan_kajian');

        if (Schema::hasTable('siakad_bahan_kajian')) {
            Schema::table('siakad_bahan_kajian', function (Blueprint $table) {
                if (Schema::hasColumn('siakad_bahan_kajian', 'koordinator_id')) {
                    $table->dropForeign(['koordinator_id']);
                    $table->dropColumn('koordinator_id');
                }
                if (Schema::hasColumn('siakad_bahan_kajian', 'kurikulum_id')) {
                    $table->dropForeign(['kurikulum_id']);
                    $table->dropColumn('kurikulum_id');
                }
            });
        }
    }
};
