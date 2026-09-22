<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pengajuan Operasional SIKEU v2:
     * - relasi fakultas & ruangan (master existing)
     * - kategori pengadaan_barang vs non_barang
     * - approval 4 tahap: pengaju -> sarpras -> keuangan -> direktur
     * - item rincian barang (nama, qty, harga satuan, subtotal)
     * - LPJ + sisa dana (kembali transfer / pakai lagi) + rincian tambahan
     */
    public function up(): void
    {
        // 1. Extend sikeu_pengajuan_pencairan_kas
        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            $table->unsignedBigInteger('fakultas_id')->nullable()->after('unit_kerja_id');
            $table->unsignedBigInteger('ruangan_id')->nullable()->after('fakultas_id');
            $table->enum('kategori_pengajuan', ['pengadaan_barang', 'non_barang'])->default('pengadaan_barang')->after('jenis_pengajuan');
            $table->unsignedBigInteger('approved_sarpras_by')->nullable()->after('approved_pimpinan_by');
            $table->timestamp('approved_sarpras_at')->nullable()->after('approved_sarpras_by');
            $table->unsignedBigInteger('approved_direktur_by')->nullable()->after('approved_keuangan_by');
            $table->timestamp('approved_direktur_at')->nullable()->after('approved_direktur_by');
            $table->date('tanggal_pencairan')->nullable()->after('approved_direktur_at');
            $table->string('bukti_pencairan_path')->nullable()->after('tanggal_pencairan');
            $table->decimal('total_realisasi', 15, 2)->default(0)->after('nominal_disetujui');
            $table->decimal('sisa_nominal', 15, 2)->default(0)->after('total_realisasi');
            $table->text('catatan_penolakan')->nullable()->after('file_lampiran');

            $table->index('fakultas_id');
            $table->index('ruangan_id');
            $table->index('kategori_pengajuan');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sikeu_pengajuan_pencairan_kas MODIFY COLUMN status ENUM('draft','diajukan','pending_sarpras','pending_keuangan','pending_direktur','pending_pimpinan','disetujui','ditolak','dicairkan','lpj_pending','lpj_disetujui','selesai') NOT NULL DEFAULT 'diajukan'");
        }

        // FK ke master existing (nullable, set null agar master tetap aman dihapus)
        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            $table->foreign('fakultas_id')->references('id')->on('siakad_fakultas')->onDelete('set null');
            $table->foreign('ruangan_id')->references('id')->on('sinapra_ruangan')->onDelete('set null');
        });

        // 2. Rincian item barang pengajuan
        Schema::create('sikeu_pengajuan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('sikeu_pengajuan_pencairan_kas')->onDelete('cascade');
            $table->string('nama_barang', 255);
            $table->decimal('qty', 12, 2)->default(1);
            $table->string('satuan', 50)->default('pcs');
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('pengajuan_id');
        });

        // 3. Extend laporan bukti pelaksanaan (LPJ)
        Schema::table('sikeu_laporan_bukti_pelaksanaan', function (Blueprint $table) {
            $table->foreignId('pengajuan_id')->nullable()->after('sumber_id')->constrained('sikeu_pengajuan_pencairan_kas')->onDelete('cascade');
            $table->decimal('nominal_dicairkan', 15, 2)->default(0)->after('total_realisasi');
            $table->decimal('sisa_nominal', 15, 2)->default(0)->after('nominal_dicairkan');
            $table->enum('metode_sisa', ['belum_ditentukan', 'kembali_transfer', 'pakai_lagi'])->default('belum_ditentukan')->after('sisa_nominal');
            $table->string('bukti_pengembalian_path')->nullable()->after('file_nota_kuitansi');
            $table->string('nomor_rekening_tujuan')->nullable()->after('bukti_pengembalian_path');

            $table->index('pengajuan_id');
            $table->index('status_verifikasi');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sikeu_laporan_bukti_pelaksanaan MODIFY COLUMN status_verifikasi ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending'");
        }

        // 4. Rincian tambahan LPJ (pembelian lain dari sisa / rincian realisasi per item)
        Schema::create('sikeu_lpj_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lpj_id')->constrained('sikeu_laporan_bukti_pelaksanaan')->onDelete('cascade');
            $table->enum('tipe', ['realisasi', 'tambahan'])->default('realisasi');
            $table->string('keterangan', 255);
            $table->decimal('qty', 12, 2)->default(1);
            $table->string('satuan', 50)->default('pcs');
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('file_bukti_path')->nullable();
            $table->timestamps();

            $table->index('lpj_id');
            $table->index('tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikeu_lpj_detail');

        Schema::table('sikeu_laporan_bukti_pelaksanaan', function (Blueprint $table) {
            $table->dropForeign(['pengajuan_id']);
            $table->dropColumn(['pengajuan_id', 'nominal_dicairkan', 'sisa_nominal', 'metode_sisa', 'bukti_pengembalian_path', 'nomor_rekening_tujuan']);
        });

        Schema::dropIfExists('sikeu_pengajuan_item');

        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            $table->dropForeign(['fakultas_id']);
            $table->dropForeign(['ruangan_id']);
            $table->dropColumn([
                'fakultas_id', 'ruangan_id', 'kategori_pengajuan',
                'approved_sarpras_by', 'approved_sarpras_at',
                'approved_direktur_by', 'approved_direktur_at',
                'tanggal_pencairan', 'bukti_pencairan_path',
                'total_realisasi', 'sisa_nominal', 'catatan_penolakan',
            ]);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sikeu_pengajuan_pencairan_kas MODIFY COLUMN status ENUM('draft','pending_pimpinan','pending_keuangan','disetujui','ditolak','dicairkan') NOT NULL DEFAULT 'pending_pimpinan'");
        }
    }
};
