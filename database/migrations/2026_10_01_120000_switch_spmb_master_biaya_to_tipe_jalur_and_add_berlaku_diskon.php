<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scope master biaya SPMB berpindah dari gelombang penerimaan ke
     * tipe jalur masuk (core_master_tipe_jalur), plus penanda diskon
     * pada tiap item komponen biaya.
     */
    public function up(): void
    {
        // Lepas foreign key lebih dulu agar unique index bisa dihapus.
        Schema::table('spmb_master_biaya', function (Blueprint $table) {
            $table->dropForeign(['gelombang_id']);
        });

        Schema::table('spmb_master_biaya', function (Blueprint $table) {
            $table->dropUnique('spmb_master_biaya_gelombang_prodi_unique');
            $table->dropColumn('gelombang_id');
            $table->foreignId('master_tipe_jalur_id')
                ->nullable()
                ->after('program_studi_id')
                ->constrained('core_master_tipe_jalur')
                ->nullOnDelete();
            $table->unique(['master_tipe_jalur_id', 'program_studi_id'], 'spmb_master_biaya_tipe_prodi_unique');
        });

        Schema::table('spmb_master_biaya_item', function (Blueprint $table) {
            $table->boolean('berlaku_diskon')->default(false)->after('dibebankan_saat_pendaftaran');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_master_biaya_item', function (Blueprint $table) {
            $table->dropColumn('berlaku_diskon');
        });

        Schema::table('spmb_master_biaya', function (Blueprint $table) {
            $table->dropForeign(['master_tipe_jalur_id']);
        });

        Schema::table('spmb_master_biaya', function (Blueprint $table) {
            $table->dropUnique('spmb_master_biaya_tipe_prodi_unique');
            $table->dropColumn('master_tipe_jalur_id');
            $table->foreignId('gelombang_id')
                ->nullable()
                ->after('program_studi_id')
                ->constrained('spmb_gelombang_penerimaan')
                ->cascadeOnDelete();
            $table->unique(['gelombang_id', 'program_studi_id'], 'spmb_master_biaya_gelombang_prodi_unique');
        });
    }
};
