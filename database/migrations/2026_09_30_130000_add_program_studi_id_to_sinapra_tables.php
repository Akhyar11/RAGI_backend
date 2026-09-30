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
        // 1. Tambah program_studi_id pada sinapra_ruangan
        Schema::table('sinapra_ruangan', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_ruangan', 'program_studi_id')) {
                $table->foreignId('program_studi_id')
                    ->nullable()
                    ->after('tipe_ruangan_id')
                    ->constrained('siakad_program_studi')
                    ->onDelete('set null');
                $table->index('program_studi_id', 'idx_sinapra_ruangan_prodi');
            }
        });

        // 2. Tambah program_studi_id pada sinapra_aset
        Schema::table('sinapra_aset', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_aset', 'program_studi_id')) {
                $table->foreignId('program_studi_id')
                    ->nullable()
                    ->after('ruangan_id')
                    ->constrained('siakad_program_studi')
                    ->onDelete('set null');
                $table->index('program_studi_id', 'idx_sinapra_aset_prodi');
            }
        });

        // 3. Tabel penugasan laboran ke Program Studi
        if (!Schema::hasTable('sinapra_laboran_prodi')) {
            Schema::create('sinapra_laboran_prodi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('core_users')->onDelete('cascade');
                $table->foreignId('program_studi_id')->constrained('siakad_program_studi')->onDelete('cascade');
                $table->boolean('is_primary')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'program_studi_id'], 'uq_sinapra_laboran_prodi');
                $table->index('user_id', 'idx_laboran_prodi_user');
                $table->index('program_studi_id', 'idx_laboran_prodi_prodi');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_laboran_prodi');

        Schema::table('sinapra_aset', function (Blueprint $table) {
            if (Schema::hasColumn('sinapra_aset', 'program_studi_id')) {
                $table->dropForeign(['program_studi_id']);
                $table->dropIndex('idx_sinapra_aset_prodi');
                $table->dropColumn('program_studi_id');
            }
        });

        Schema::table('sinapra_ruangan', function (Blueprint $table) {
            if (Schema::hasColumn('sinapra_ruangan', 'program_studi_id')) {
                $table->dropForeign(['program_studi_id']);
                $table->dropIndex('idx_sinapra_ruangan_prodi');
                $table->dropColumn('program_studi_id');
            }
        });
    }
};
