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
        // 1. Sertifikasi Dosen
        Schema::create('simpeg_sertifikasi_dosen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
            $table->foreignId('jenis_sertifikasi_id')->constrained('simpeg_master_jenis_sertifikasi')->onDelete('restrict');
            $table->string('nama_sertifikat', 255);
            $table->string('bidang_studi', 150);
            $table->string('nomor_registrasi', 100)->nullable();
            $table->string('nomor_sk', 100)->nullable();
            $table->integer('tahun_sertifikasi')->index();
            $table->string('penyelenggara', 200);
            $table->string('file_path', 255)->nullable();
            $table->string('tautan', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pegawai_id', 'tahun_sertifikasi']);
        });

        // 2. Riwayat Tes (Bahasa & Potensi Akademik)
        Schema::create('simpeg_riwayat_tes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
            $table->foreignId('jenis_tes_id')->constrained('simpeg_master_jenis_tes')->onDelete('restrict');
            $table->string('nama_tes', 200);
            $table->string('penyelenggara', 200);
            $table->integer('tahun')->index();
            $table->decimal('skor', 8, 2);
            $table->date('masa_berlaku')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('tautan', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pegawai_id', 'jenis_tes_id', 'tahun']);
        });

        // 3. Riwayat Pelatihan, Diklat, Workshop & Seminar
        Schema::create('simpeg_riwayat_pelatihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
            $table->string('nama_kegiatan', 255);
            $table->foreignId('jenis_pelatihan_id')->nullable()->constrained('simpeg_master_jenis_pelatihan')->onDelete('set null');
            $table->foreignId('peran_id')->constrained('simpeg_master_peran_pelatihan')->onDelete('restrict');
            $table->foreignId('tingkat_id')->nullable()->constrained('simpeg_master_tingkat_kegiatan')->onDelete('set null');
            $table->date('tanggal_mulai')->index();
            $table->date('tanggal_selesai')->nullable();
            $table->integer('jumlah_jam')->nullable();
            $table->string('penyelenggara', 200);
            $table->string('tempat', 200)->nullable();
            $table->string('nomor_sertifikat', 100)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('tautan', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pegawai_id', 'tanggal_mulai']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_riwayat_pelatihan');
        Schema::dropIfExists('simpeg_riwayat_tes');
        Schema::dropIfExists('simpeg_sertifikasi_dosen');
    }
};
