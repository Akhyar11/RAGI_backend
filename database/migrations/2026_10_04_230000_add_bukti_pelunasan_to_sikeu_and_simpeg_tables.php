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
        if (Schema::hasTable('sikeu_pengajuan_pencairan_kas')) {
            Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
                if (!Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'bukti_pelunasan_path')) {
                    $table->string('bukti_pelunasan_path')->nullable()->after('bukti_pencairan_path');
                }
                if (!Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'tipe_pelunasan')) {
                    $table->string('tipe_pelunasan', 50)->nullable()->after('bukti_pelunasan_path');
                }
                if (!Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'nominal_pelunasan')) {
                    $table->decimal('nominal_pelunasan', 15, 2)->nullable()->after('tipe_pelunasan');
                }
            });
        }

        if (Schema::hasTable('simpeg_surat_tugas')) {
            Schema::table('simpeg_surat_tugas', function (Blueprint $table) {
                if (!Schema::hasColumn('simpeg_surat_tugas', 'bukti_pelunasan_path')) {
                    $table->string('bukti_pelunasan_path')->nullable()->after('file_lpj');
                }
                if (!Schema::hasColumn('simpeg_surat_tugas', 'tipe_pelunasan')) {
                    $table->string('tipe_pelunasan', 50)->nullable()->after('bukti_pelunasan_path');
                }
                if (!Schema::hasColumn('simpeg_surat_tugas', 'nominal_pelunasan')) {
                    $table->decimal('nominal_pelunasan', 15, 2)->nullable()->after('tipe_pelunasan');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sikeu_pengajuan_pencairan_kas')) {
            Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
                $table->dropColumn(['bukti_pelunasan_path', 'tipe_pelunasan', 'nominal_pelunasan']);
            });
        }

        if (Schema::hasTable('simpeg_surat_tugas')) {
            Schema::table('simpeg_surat_tugas', function (Blueprint $table) {
                $table->dropColumn(['bukti_pelunasan_path', 'tipe_pelunasan', 'nominal_pelunasan']);
            });
        }
    }
};
