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
        // 1. Surat Tugas
        Schema::create('simpeg_surat_tugas', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat', 100)->nullable()->index();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
            $table->foreignId('kategori_kegiatan_id')->constrained('simpeg_master_kategori_kegiatan_tugas')->onDelete('restrict');
            $table->foreignId('jenis_transportasi_id')->constrained('simpeg_master_jenis_transportasi')->onDelete('restrict');
            $table->string('nama_kegiatan', 255);
            $table->string('tempat_berangkat', 255)->default('Kampus');
            $table->string('lokasi_tujuan', 255);
            $table->date('tanggal_berangkat')->index();
            $table->date('tanggal_kembali')->index();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('maksud_tujuan')->nullable();
            $table->string('beban_anggaran', 255)->nullable();
            $table->decimal('estimasi_biaya', 15, 2)->nullable();
            $table->decimal('biaya_realisasi', 15, 2)->nullable();
            $table->text('laporan_kegiatan')->nullable();
            $table->string('kendaraan_dinas', 255)->nullable(); // e.g. Toyota Avanza (D 1234 XY)
            $table->string('nama_driver', 150)->nullable();
            $table->string('kontak_driver', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('file_surat_tugas', 255)->nullable();
            $table->string('file_lpj', 255)->nullable();
            $table->timestamp('tanggal_upload_lpj')->nullable();
            $table->string('status', 30)->default('diajukan')->index(); // draft, diajukan, disetujui, ditolak, selesai
            $table->text('catatan_approval')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pegawai_id', 'status']);
        });

        // 2. Anggota Tim Surat Tugas
        Schema::create('simpeg_surat_tugas_anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_tugas_id')->constrained('simpeg_surat_tugas')->onDelete('cascade');
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
            $table->string('peran', 100)->default('Anggota');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->unique(['surat_tugas_id', 'pegawai_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_surat_tugas_anggota');
        Schema::dropIfExists('simpeg_surat_tugas');
    }
};
