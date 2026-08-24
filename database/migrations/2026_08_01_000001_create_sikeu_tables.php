<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for SIKEU module tables.
     */
    public function up(): void
    {
        // 1. Jenis Biaya (Master Kategori Biaya)
        Schema::create('sikeu_master_biaya', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->enum('tipe', ['spp', 'sks', 'praktikum', 'wisuda', 'spmb_adm', 'lainnya'])->default('lainnya');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_recurring')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });



        // 5. Tagihan Mahasiswa
        Schema::create('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mahasiswa_id');
            $table->unsignedBigInteger('tahun_akademik_id')->nullable();
            $table->string('nomor_tagihan')->unique();
            $table->decimal('total_tagihan', 15, 2)->default(0);
            $table->decimal('total_potongan', 15, 2)->default(0);
            $table->decimal('total_denda', 15, 2)->default(0);
            $table->decimal('total_bayar', 15, 2)->default(0);
            $table->enum('status', ['belum_bayar', 'sebagian', 'lunas', 'dispensasi', 'pending_approval', 'batal'])->default('belum_bayar');
            $table->boolean('requires_approval')->default(false);
            $table->enum('status_approval', ['pending', 'approved', 'rejected'])->default('approved');
            $table->unsignedBigInteger('disetujui_oleh')->nullable();
            $table->timestamp('tanggal_approval')->nullable();
            $table->text('catatan_approval')->nullable();
            $table->string('source_system')->default('SIAKAD');
            $table->date('jatuh_tempo')->nullable();
            $table->timestamps();

            $table->unique(['mahasiswa_id', 'tahun_akademik_id', 'nomor_tagihan'], 'tagihan_mhs_ta_nomor_unique');
        });

        // 6. Detail Tagihan
        Schema::create('sikeu_detail_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('sikeu_tagihan_mahasiswa')->onDelete('cascade');
            $table->foreignId('master_biaya_id')->constrained('sikeu_master_biaya')->onDelete('cascade');
            $table->decimal('nominal', 15, 2)->default(0);
            $table->decimal('potongan', 15, 2)->default(0);
            $table->decimal('nominal_bersih', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // 7. Potongan Tagihan
        Schema::create('sikeu_potongan_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('sikeu_tagihan_mahasiswa')->onDelete('cascade');
            $table->enum('tipe', ['diskon', 'subsidi', 'lainnya'])->default('diskon');
            $table->decimal('nominal_potongan', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('diinput_oleh')->nullable();
            $table->timestamps();
        });

        // 8. Denda Tagihan
        Schema::create('sikeu_denda_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('sikeu_tagihan_mahasiswa')->onDelete('cascade');
            $table->enum('tipe_denda', ['keterlambatan', 'lainnya'])->default('keterlambatan');
            $table->decimal('nominal_denda', 15, 2)->default(0);
            $table->date('tanggal_denda')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // 9. Dispensasi Tagihan
        Schema::create('sikeu_dispensasi_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('sikeu_tagihan_mahasiswa')->onDelete('cascade');
            $table->unsignedBigInteger('mahasiswa_id');
            $table->enum('tipe_dispensasi', ['penundaan_jatuh_tempo', 'cicilan', 'keringanan_khusus'])->default('penundaan_jatuh_tempo');
            $table->date('jatuh_tempo_baru')->nullable();
            $table->integer('jumlah_cicilan')->default(1);
            $table->decimal('nominal_per_cicilan', 15, 2)->default(0);
            $table->text('alasan');
            $table->string('dokumen_pendukung')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('diajukan_oleh')->nullable();
            $table->unsignedBigInteger('disetujui_oleh')->nullable();
            $table->timestamp('tanggal_persetujuan')->nullable();
            $table->text('catatan_pimpinan')->nullable();
            $table->timestamps();
        });

        // 10. Virtual Account
        Schema::create('sikeu_virtual_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('sikeu_tagihan_mahasiswa')->onDelete('cascade');
            $table->string('va_number')->unique();
            $table->string('bank_kode')->default('BNI');
            $table->string('bank_nama')->default('Bank BNI');
            $table->decimal('nominal', 15, 2)->default(0);
            $table->timestamp('expired_at')->nullable();
            $table->enum('status', ['aktif', 'kadaluarsa', 'dibayar'])->default('aktif');
            $table->timestamps();
        });

        // 11. Pembayaran
        Schema::create('sikeu_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('sikeu_tagihan_mahasiswa')->onDelete('cascade');
            $table->foreignId('virtual_account_id')->nullable()->constrained('sikeu_virtual_account')->onDelete('set null');
            $table->string('kode_transaksi')->unique();
            $table->decimal('jumlah_bayar', 15, 2)->default(0);
            $table->timestamp('waktu_bayar')->nullable();
            $table->string('channel_bayar')->default('VA_BANK');
            $table->string('bank_pengirim')->nullable();
            $table->enum('status', ['success', 'pending', 'failed', 'reversed'])->default('success');
            $table->unsignedBigInteger('diverifikasi_oleh')->nullable();
            $table->timestamps();
        });

        // 12. Callback Payment Gateway
        Schema::create('sikeu_callback_payment_gateway', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('payment_type')->nullable();
            $table->json('raw_payload')->nullable();
            $table->enum('status', ['received', 'processed', 'failed'])->default('received');
            $table->foreignId('pembayaran_id')->nullable()->constrained('sikeu_pembayaran')->onDelete('set null');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // 13. Rekonsiliasi Pembayaran
        Schema::create('sikeu_rekonsiliasi_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_rekonsiliasi');
            $table->string('bank_kode')->default('BNI');
            $table->integer('total_transaksi')->default(0);
            $table->decimal('total_nominal_sistem', 15, 2)->default(0);
            $table->decimal('total_nominal_bank', 15, 2)->default(0);
            $table->decimal('selisih', 15, 2)->default(0);
            $table->enum('status', ['cocok', 'tidak_cocok', 'dalam_review'])->default('cocok');
            $table->string('file_laporan_bank')->nullable();
            $table->unsignedBigInteger('diproses_oleh')->nullable();
            $table->timestamps();
        });

        // 14. Unit Kas (Petty Cash & Kas Utama)
        Schema::create('sikeu_unit_kas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->string('nama_kas');
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->decimal('saldo_saat_ini', 15, 2)->default(0);
            $table->unsignedBigInteger('penanggung_jawab_id')->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // 15. Akun Keuangan (Chart of Accounts / COA)
        Schema::create('sikeu_akun_keuangan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_akun')->unique();
            $table->string('nama_akun');
            $table->enum('kelompok', ['aset', 'liabilitas', 'ekuitas', 'pendapatan', 'beban'])->default('aset');
            $table->enum('saldo_normal', ['debet', 'kredit'])->default('debet');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 16. Periode Akuntansi (Penguncian Periode Akuntansi)
        Schema::create('sikeu_periode_akuntansi', function (Blueprint $table) {
            $table->id();
            $table->string('nama_periode');
            $table->integer('tahun');
            $table->integer('bulan');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('status', ['terbuka', 'ditutup'])->default('terbuka');
            $table->unsignedBigInteger('ditutup_oleh')->nullable();
            $table->timestamp('ditutup_pada')->nullable();
            $table->timestamps();
        });

        // 17. Pemasukan Kampus (Hibah SIPPM, Donatur, Kerjasama)
        Schema::create('sikeu_pemasukan_kampus', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_transaksi')->unique();
            $table->enum('sumber_pemasukan', ['hibah_sippm', 'donatur', 'kerjasama', 'pendapatan_lainnya'])->default('pendapatan_lainnya');
            $table->foreignId('unit_kas_id')->nullable()->constrained('sikeu_unit_kas')->onDelete('set null');
            $table->foreignId('akun_pendapatan_id')->nullable()->constrained('sikeu_akun_keuangan')->onDelete('set null');
            $table->decimal('nominal', 15, 2)->default(0);
            $table->date('tanggal_terima');
            $table->string('nama_donor_instansi')->nullable();
            $table->string('nomor_kontrak_ref')->nullable();
            $table->string('file_bukti_transfer')->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // 18. Pengajuan Pencairan Kas Unit
        Schema::create('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pengajuan')->unique();
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->foreignId('unit_kas_id')->constrained('sikeu_unit_kas')->onDelete('cascade');
            $table->unsignedBigInteger('pemohon_id')->nullable();
            $table->string('judul_pengajuan');
            $table->text('deskripsi')->nullable();
            $table->decimal('nominal_diajukan', 15, 2)->default(0);
            $table->decimal('nominal_disetujui', 15, 2)->default(0);
            $table->enum('jenis_pengajuan', ['operasional', 'kegiatan', 'reimbursement', 'lainnya'])->default('operasional');
            $table->string('file_lampiran')->nullable();
            $table->enum('status', ['draft', 'pending_pimpinan', 'pending_keuangan', 'disetujui', 'ditolak', 'dicairkan'])->default('pending_pimpinan');
            $table->unsignedBigInteger('approved_pimpinan_by')->nullable();
            $table->timestamp('approved_pimpinan_at')->nullable();
            $table->unsignedBigInteger('approved_keuangan_by')->nullable();
            $table->timestamp('approved_keuangan_at')->nullable();
            $table->timestamps();
        });

        // 19. Transaksi Kas Unit (Log Mutasi Debet / Kredit)
        Schema::create('sikeu_transaksi_kas_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kas_id')->constrained('sikeu_unit_kas')->onDelete('cascade');
            $table->foreignId('pengajuan_pencairan_id')->nullable()->constrained('sikeu_pengajuan_pencairan_kas')->onDelete('set null');
            $table->string('kode_transaksi')->unique();
            $table->enum('jenis_transaksi', ['debet_pemasukan', 'kredit_pengeluaran'])->default('kredit_pengeluaran');
            $table->decimal('nominal', 15, 2)->default(0);
            $table->decimal('saldo_sebelum', 15, 2)->default(0);
            $table->decimal('saldo_sesudah', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->date('tanggal_transaksi');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // 20. Approval History Pencairan
        Schema::create('sikeu_approval_history_pencairan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('sikeu_pengajuan_pencairan_kas')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->string('role_approver')->default('pimpinan');
            $table->enum('status_action', ['approved', 'rejected', 'revision_requested'])->default('approved');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        // 21. Jurnal Umum Header
        Schema::create('sikeu_jurnal_umum', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_jurnal')->unique();
            $table->date('tanggal_jurnal');
            $table->foreignId('periode_id')->nullable()->constrained('sikeu_periode_akuntansi')->onDelete('set null');
            $table->enum('jenis_sumber', ['pembayaran_mahasiswa', 'pemasukan_hibah', 'pencairan_kas', 'pengeluaran_manual', 'penyesuaian', 'penutupan'])->default('penyesuaian');
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->enum('status_posting', ['draft', 'posted'])->default('posted');
            $table->decimal('total_debet', 15, 2)->default(0);
            $table->decimal('total_kredit', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        // 22. Detail Jurnal Umum
        Schema::create('sikeu_detail_jurnal_umum', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurnal_id')->constrained('sikeu_jurnal_umum')->onDelete('cascade');
            $table->foreignId('akun_id')->constrained('sikeu_akun_keuangan')->onDelete('cascade');
            $table->decimal('debet', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // 23. Pengeluaran Kampus Manual (Dengan Pajak PPh/PPN)
        Schema::create('sikeu_pengeluaran_kampus', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_transaksi')->unique();
            $table->string('kategori')->default('operasional');
            $table->foreignId('akun_beban_id')->nullable()->constrained('sikeu_akun_keuangan')->onDelete('set null');
            $table->foreignId('akun_kas_id')->nullable()->constrained('sikeu_akun_keuangan')->onDelete('set null');
            $table->decimal('nominal', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->date('tanggal_transaksi');
            $table->string('nama_vendor')->nullable();
            $table->string('npwp_vendor')->nullable();
            $table->enum('jenis_pajak', ['tanpa_pajak', 'pph_21', 'pph_23', 'ppn_11'])->default('tanpa_pajak');
            $table->decimal('tarif_pajak_persen', 5, 2)->default(0);
            $table->decimal('nominal_pajak', 15, 2)->default(0);
            $table->decimal('net_dibayarkan', 15, 2)->default(0);
            $table->enum('status_pembayaran', ['lunas', 'pending', 'batal'])->default('lunas');
            $table->string('file_bukti_bayar')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // 24. Laporan Bukti Pelaksanaan (LPJ / Nota Realisasi)
        Schema::create('sikeu_laporan_bukti_pelaksanaan', function (Blueprint $table) {
            $table->id();
            $table->enum('sumber_tipe', ['pengajuan_pencairan', 'pengeluaran_kampus'])->default('pengajuan_pencairan');
            $table->unsignedBigInteger('sumber_id');
            $table->string('nomor_bukti')->unique();
            $table->date('tanggal_pelaksanaan');
            $table->decimal('total_realisasi', 15, 2)->default(0);
            $table->string('file_nota_kuitansi')->nullable();
            $table->text('rincian_keterangan')->nullable();
            $table->enum('status_verifikasi', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->unsignedBigInteger('diverifikasi_oleh')->nullable();
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sikeu_laporan_bukti_pelaksanaan');
        Schema::dropIfExists('sikeu_pengeluaran_kampus');
        Schema::dropIfExists('sikeu_detail_jurnal_umum');
        Schema::dropIfExists('sikeu_jurnal_umum');
        Schema::dropIfExists('sikeu_approval_history_pencairan');
        Schema::dropIfExists('sikeu_transaksi_kas_unit');
        Schema::dropIfExists('sikeu_pengajuan_pencairan_kas');
        Schema::dropIfExists('sikeu_pemasukan_kampus');
        Schema::dropIfExists('sikeu_periode_akuntansi');
        Schema::dropIfExists('sikeu_akun_keuangan');
        Schema::dropIfExists('sikeu_unit_kas');
        Schema::dropIfExists('sikeu_rekonsiliasi_pembayaran');
        Schema::dropIfExists('sikeu_callback_payment_gateway');
        Schema::dropIfExists('sikeu_pembayaran');
        Schema::dropIfExists('sikeu_virtual_account');
        Schema::dropIfExists('sikeu_dispensasi_tagihan');
        Schema::dropIfExists('sikeu_denda_tagihan');
        Schema::dropIfExists('sikeu_potongan_tagihan');
        Schema::dropIfExists('sikeu_detail_tagihan');
        Schema::dropIfExists('sikeu_tagihan_mahasiswa');

        Schema::dropIfExists('sikeu_master_biaya');
    }
};
