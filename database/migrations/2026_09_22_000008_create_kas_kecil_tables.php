<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur Kas Kecil (Petty Cash) SIKEU:
     * - Unit kas kecil memakai tabel existing `sikeu_unit_kas` dengan `tipe_kas='petty_cash'`,
     *   ditambah kolom `fakultas_id` agar unit dipetakan per fakultas.
     * - `sikeu_kas_kecil_transaksi` : transaksi pengeluaran kas kecil oleh Petugas Kas Kecil
     *   (kategori dari master referensi `spmb_master_referensi`, bukti file, dsb.).
     * - `sikeu_kas_kecil_pengajuan`  : pengajuan kas langsung / top-up ke kas kecil,
     *   disetujui / ditolak oleh Admin Keuangan; saat disetujui saldo & jurnal otomatis.
     */
    public function up(): void
    {
        Schema::table('sikeu_unit_kas', function (Blueprint $table) {
            $table->unsignedBigInteger('fakultas_id')->nullable()->after('unit_kerja_id');
            $table->index('fakultas_id');
        });

        Schema::table('sikeu_unit_kas', function (Blueprint $table) {
            $table->foreign('fakultas_id')->references('id')->on('siakad_fakultas')->onDelete('set null');
        });

        // Transaksi pengeluaran kas kecil
        Schema::create('sikeu_kas_kecil_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kas_id')->constrained('sikeu_unit_kas')->onDelete('cascade');
            $table->foreignId('transaksi_kas_unit_id')->nullable()->constrained('sikeu_transaksi_kas_unit')->onDelete('set null');
            $table->string('nomor_transaksi', 50)->unique();
            $table->foreignId('referensi_kategori_id')->nullable()->constrained('spmb_master_referensi')->onDelete('set null');
            $table->text('uraian');
            $table->string('penerima', 191)->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->date('tanggal_transaksi');
            $table->string('file_bukti_path')->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('unit_kas_id');
            $table->index('referensi_kategori_id');
            $table->index('tanggal_transaksi');
        });

        // Pengajuan kas langsung / top-up kas kecil
        Schema::create('sikeu_kas_kecil_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kas_id')->constrained('sikeu_unit_kas')->onDelete('cascade');
            $table->foreignId('transaksi_kas_unit_id')->nullable()->constrained('sikeu_transaksi_kas_unit')->onDelete('set null');
            $table->string('nomor_pengajuan', 50)->unique();
            $table->string('judul_pengajuan', 255);
            $table->text('keperluan')->nullable();
            $table->decimal('nominal_diajukan', 15, 2)->default(0);
            $table->decimal('nominal_disetujui', 15, 2)->default(0);
            $table->enum('status', ['pending_keuangan', 'disetujui', 'ditolak'])->default('pending_keuangan');
            $table->text('catatan_penolakan')->nullable();
            $table->unsignedBigInteger('pemohon_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('unit_kas_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikeu_kas_kecil_pengajuan');
        Schema::dropIfExists('sikeu_kas_kecil_transaksi');

        Schema::table('sikeu_unit_kas', function (Blueprint $table) {
            $table->dropForeign(['fakultas_id']);
            $table->dropIndex(['fakultas_id']);
            $table->dropColumn('fakultas_id');
        });
    }
};