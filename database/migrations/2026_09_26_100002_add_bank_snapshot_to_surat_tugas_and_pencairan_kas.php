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
        Schema::table('simpeg_surat_tugas', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_surat_tugas', 'nama_bank')) {
                $table->string('nama_bank', 50)->nullable()->after('biaya_realisasi');
            }
            if (!Schema::hasColumn('simpeg_surat_tugas', 'nomor_rekening')) {
                $table->string('nomor_rekening', 50)->nullable()->after('nama_bank');
            }
            if (!Schema::hasColumn('simpeg_surat_tugas', 'nama_rekening')) {
                $table->string('nama_rekening', 100)->nullable()->after('nomor_rekening');
            }
        });

        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            if (!Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'nama_bank_penerima')) {
                $table->string('nama_bank_penerima', 50)->nullable()->after('nominal_disetujui');
            }
            if (!Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'nomor_rekening_penerima')) {
                $table->string('nomor_rekening_penerima', 50)->nullable()->after('nama_bank_penerima');
            }
            if (!Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'nama_rekening_penerima')) {
                $table->string('nama_rekening_penerima', 100)->nullable()->after('nomor_rekening_penerima');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_surat_tugas', function (Blueprint $table) {
            $table->dropColumn(['nama_bank', 'nomor_rekening', 'nama_rekening']);
        });

        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            $table->dropColumn(['nama_bank_penerima', 'nomor_rekening_penerima', 'nama_rekening_penerima']);
        });
    }
};
