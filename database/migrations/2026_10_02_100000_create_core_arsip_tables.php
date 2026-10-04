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
        // 1. Master Klasifikasi & Kode Unit
        Schema::create('core_arsip_klasifikasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->string('kategori', 50)->default('klasifikasi'); // unit, jenjang, klasifikasi, perihal
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kategori', 'is_active']);
        });

        // 2. Master Kop Surat (Versi Lama < 2021 vs Versi Baru >= 2021)
        Schema::create('core_arsip_kop_surat', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->enum('versi', ['lama', 'baru'])->default('baru');
            $table->unsignedSmallInteger('tahun_mulai')->default(2021);
            $table->unsignedSmallInteger('tahun_selesai')->nullable();
            $table->string('file_path');
            $table->string('nama_institusi', 200)->nullable();
            $table->string('alamat_institusi', 255)->nullable();
            $table->string('kontak_institusi', 150)->nullable();
            $table->string('website_institusi', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['versi', 'is_active']);
            $table->index(['tahun_mulai', 'tahun_selesai']);
        });

        // 3. Request Nomor Surat Lintas Modul
        Schema::create('core_arsip_request_nomor', function (Blueprint $table) {
            $table->id();
            $table->string('kode_request', 60)->unique();
            $table->string('module_origin', 50)->default('arsip'); // sinapra, simpeg, siakad, sikeu, dll.
            $table->foreignId('user_id')->constrained('core_users')->onDelete('cascade');
            $table->string('perihal', 255);
            $table->string('tujuan', 200)->nullable();
            $table->date('tanggal_surat');
            $table->string('kode_unit', 50);
            $table->string('kode_klasifikasi', 50);
            $table->unsignedInteger('jumlah_nomor')->default(1);
            $table->text('catatan_pemohon')->nullable();
            $table->string('dokumen_lampiran_path')->nullable();
            $table->enum('status', ['menunggu_verifikasi', 'disetujui', 'ditolak'])->default('menunggu_verifikasi');
            $table->foreignId('verified_by')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'module_origin']);
            $table->index(['tanggal_surat', 'kode_unit']);
        });

        // 4. Nomor Surat Definitif (Single & Bulk Generated)
        Schema::create('core_arsip_nomor_surat', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat', 150)->unique();
            $table->unsignedInteger('nomor_urut');
            $table->string('kode_unit', 50);
            $table->string('kode_klasifikasi', 50);
            $table->string('bulan_romawi', 10);
            $table->unsignedSmallInteger('tahun');
            $table->date('tanggal_surat');
            $table->string('perihal', 255);
            $table->string('tujuan', 200)->nullable();
            $table->enum('status', ['terpakai', 'direservasi', 'dibatalkan'])->default('terpakai');
            $table->string('module_origin', 50)->default('arsip');
            $table->foreignId('request_id')->nullable()->constrained('core_arsip_request_nomor')->onDelete('set null');
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('kop_surat_id')->nullable()->constrained('core_arsip_kop_surat')->onDelete('set null');
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->constrained('core_users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun', 'nomor_urut']);
            $table->index(['kode_unit', 'kode_klasifikasi']);
            $table->index(['module_origin', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_arsip_nomor_surat');
        Schema::dropIfExists('core_arsip_request_nomor');
        Schema::dropIfExists('core_arsip_kop_surat');
        Schema::dropIfExists('core_arsip_klasifikasi');
    }
};
