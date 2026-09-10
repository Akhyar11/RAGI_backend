<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restrukturisasi tabel spmb_tarif_ukt menjadi Biaya Daftar Ulang.
     * Field baru: nama, deskripsi, master_sikeu_biaya_id, master_program_studi_id.
     */
    public function up(): void
    {
        // Drop unique index lama (mereferensikan kolom yang akan diubah/dihapus)
        Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
            $table->dropUnique('tarif_ukt_spmb_unique_idx');
        });

        // Drop foreign key lama pada tahun_akademik_id
        Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
            $table->dropForeign(['tahun_akademik_id']);
        });

        // Rename kolom program_studi_id -> master_program_studi_id
        if (Schema::hasColumn('spmb_tarif_ukt', 'program_studi_id')) {
            Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
                $table->dropForeign(['program_studi_id']);
                $table->renameColumn('program_studi_id', 'master_program_studi_id');
            });
        }

        // Rename kolom master_biaya_id -> master_sikeu_biaya_id
        if (Schema::hasColumn('spmb_tarif_ukt', 'master_biaya_id')) {
            Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
                $table->dropForeign(['master_biaya_id']);
                $table->renameColumn('master_biaya_id', 'master_sikeu_biaya_id');
            });
        }

        // Tambah kolom nama & deskripsi
        Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
            if (!Schema::hasColumn('spmb_tarif_ukt', 'nama')) {
                $table->string('nama')->nullable()->after('id');
            }
            if (!Schema::hasColumn('spmb_tarif_ukt', 'deskripsi')) {
                $table->text('deskripsi')->nullable()->after('nama');
            }
        });

        // Hapus kolom yang tidak lagi dibutuhkan
        foreach (['tahun_akademik_id', 'kelompok_ukt', 'nominal', 'is_active'] as $col) {
            if (Schema::hasColumn('spmb_tarif_ukt', $col)) {
                Schema::table('spmb_tarif_ukt', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }

        // Tambah foreign key & index baru
        Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
            $table->foreign('master_program_studi_id')->references('id')->on('spmb_master_program_studi')->onDelete('restrict');
            $table->foreign('master_sikeu_biaya_id')->references('id')->on('sikeu_master_biaya')->onDelete('set null');
            $table->unique(['master_program_studi_id', 'master_sikeu_biaya_id'], 'biaya_daftar_ulang_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
            $table->dropForeign(['master_program_studi_id']);
            $table->dropForeign(['master_sikeu_biaya_id']);
            $table->dropUnique('biaya_daftar_ulang_unique_idx');

            if (Schema::hasColumn('spmb_tarif_ukt', 'nama')) {
                $table->dropColumn('nama');
            }
            if (Schema::hasColumn('spmb_tarif_ukt', 'deskripsi')) {
                $table->dropColumn('deskripsi');
            }

            if (Schema::hasColumn('spmb_tarif_ukt', 'master_program_studi_id')) {
                $table->renameColumn('master_program_studi_id', 'program_studi_id');
            }
            if (Schema::hasColumn('spmb_tarif_ukt', 'master_sikeu_biaya_id')) {
                $table->renameColumn('master_sikeu_biaya_id', 'master_biaya_id');
            }

            foreach (['tahun_akademik_id', 'kelompok_ukt', 'nominal', 'is_active'] as $col) {
                if (!Schema::hasColumn('spmb_tarif_ukt', $col)) {
                    $table->{$col === 'nominal' ? 'decimal' : 'string'}($col, $col === 'nominal' ? 15 : 100)->nullable();
                }
            }
        });
    }
};