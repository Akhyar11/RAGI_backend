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
        // 1. Master Kategori SK Pegawai (Zero Hardcode)
        Schema::create('simpeg_master_kategori_sk', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('kode', 50)->unique();
            $table->text('deskripsi')->nullable();
            $table->integer('urutan')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Transaksional Arsip & Pelaporan SK Pegawai
        Schema::create('simpeg_sk_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
            $table->foreignId('kategori_sk_id')->constrained('simpeg_master_kategori_sk')->onDelete('restrict');
            $table->string('nomor_sk', 100)->index();
            $table->string('judul_sk', 255);
            $table->date('tanggal_sk');
            $table->date('tmt_sk')->index(); // Terhitung Mulai Tanggal
            $table->date('tmt_selesai')->nullable(); // Masa berlaku selesai jika ada
            $table->string('pejabat_penetap', 150); // e.g. Rektor, Ketua Yayasan, Dekan
            $table->string('file_sk', 255);
            $table->text('keterangan')->nullable();
            $table->enum('status_verifikasi', ['pending', 'terverifikasi', 'ditolak'])->default('pending')->index();
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pegawai_id', 'status_verifikasi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_sk_pegawai');
        Schema::dropIfExists('simpeg_master_kategori_sk');
    }
};
