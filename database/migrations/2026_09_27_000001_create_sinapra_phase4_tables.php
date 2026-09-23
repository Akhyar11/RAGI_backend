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
        // 1. Tabel Master Bahan Habis Pakai (BHP Lab)
        Schema::create('sinapra_lab_bhp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruangan_id')->constrained('sinapra_ruangan')->onDelete('cascade');
            $table->string('kode_bhp', 50)->unique();
            $table->string('nama_bhp', 150);
            $table->string('kategori', 50)->default('umum'); // komponen_elektronik, reagen_kimia, alat_kaca, atk_lab, dsb.
            $table->decimal('stok_saat_ini', 10, 2)->default(0);
            $table->decimal('stok_minimum', 10, 2)->default(0);
            $table->string('satuan', 30)->default('pcs');
            $table->text('spesifikasi')->nullable();
            $table->string('lokasi_penyimpanan', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ruangan_id', 'kategori']);
        });

        // 2. Tabel Transaksi Mutasi BHP (Masuk / Keluar)
        Schema::create('sinapra_lab_bhp_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bhp_id')->constrained('sinapra_lab_bhp')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('core_users')->onDelete('cascade');
            $table->enum('jenis_transaksi', ['masuk', 'keluar']);
            $table->decimal('jumlah', 10, 2);
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['bhp_id', 'jenis_transaksi', 'tanggal']);
        });

        // 3. Tabel Surat Bebas Tanggungan Laboratorium Mahasiswa
        Schema::create('sinapra_bebas_tanggungan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('core_users')->onDelete('cascade');
            $table->string('nomor_surat', 100)->nullable()->unique();
            $table->date('tanggal_pengajuan');
            $table->date('tanggal_disetujui')->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('core_users')->onDelete('set null');
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak'])->default('diajukan');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        // 4. Tabel Kalibrasi Alat Presisi Laboratorium
        Schema::create('sinapra_alat_kalibrasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_id')->constrained('sinapra_aset')->onDelete('cascade');
            $table->string('institusi_kalibrasi', 150);
            $table->string('nomor_sertifikat', 100)->nullable();
            $table->date('tanggal_kalibrasi');
            $table->date('tanggal_kadaluarsa');
            $table->enum('status_kelayakan', ['laik', 'tidak_laik', 'butuh_perbaikan'])->default('laik');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['aset_id', 'tanggal_kadaluarsa', 'status_kelayakan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_alat_kalibrasi');
        Schema::dropIfExists('sinapra_bebas_tanggungan');
        Schema::dropIfExists('sinapra_lab_bhp_transaksi');
        Schema::dropIfExists('sinapra_lab_bhp');
    }
};
