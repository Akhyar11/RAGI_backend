<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antrean pengajuan terpadu (trial): batch penggajian SIMPEG menjadi satu
     * dokumen PengajuanPencairanKas agar SIKEU cukup verifikasi–setujui–cairkan
     * tanpa input ulang (sumber baru = badge baru, bukan tab baru).
     */
    public function up(): void
    {
        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            if (!Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'sumber_type')) {
                $table->string('sumber_type', 30)->nullable()->after('jenis_pengajuan');
            }
            if (!Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'sumber_id')) {
                $table->string('sumber_id', 30)->nullable()->after('sumber_type');
            }
            $table->index(['sumber_type', 'sumber_id'], 'idx_pengajuan_sumber');
            // Idempotensi batch per (sumber, id) — mis. satu batch per periode gaji.
            // Baris non-batch (NULL) tidak terpengaruh unique ini.
            $table->unique(['sumber_type', 'sumber_id'], 'uniq_pengajuan_sumber');
        });

        Schema::table('sikeu_pengajuan_item', function (Blueprint $table) {
            if (!Schema::hasColumn('sikeu_pengajuan_item', 'gaji_pegawai_id')) {
                // Trial: kolom indeks biasa (tanpa FK) agar kompatibel SQLite & MySQL.
                $table->unsignedBigInteger('gaji_pegawai_id')->nullable()->after('pengajuan_id');
            }
            $table->index(['gaji_pegawai_id'], 'idx_pengajuan_item_gaji');
        });
    }

    public function down(): void
    {
        Schema::table('sikeu_pengajuan_item', function (Blueprint $table) {
            $table->dropIndex('idx_pengajuan_item_gaji');
            $table->dropColumn('gaji_pegawai_id');
        });

        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            $table->dropUnique('uniq_pengajuan_sumber');
            $table->dropIndex('idx_pengajuan_sumber');
            $table->dropColumn(['sumber_type', 'sumber_id']);
        });
    }
};
