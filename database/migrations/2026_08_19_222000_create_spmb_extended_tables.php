<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Sekolah Mitra (Untuk Jalur Undangan / Kerjasama)
        Schema::create('spmb_sekolah_mitra', function (Blueprint $table) {
            $table->id();
            $table->string('npsn', 20)->unique()->nullable();
            $table->string('nama_sekolah');
            $table->text('alamat')->nullable();
            $table->string('akreditasi', 10)->nullable(); // A, B, C, dll
            $table->string('telepon', 20)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Tabel Riwayat Rapor (Untuk Jalur Rapor / Prestasi)
        Schema::create('spmb_riwayat_rapor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->integer('semester'); // 1, 2, 3, 4, 5
            $table->string('mata_pelajaran');
            $table->decimal('nilai', 5, 2);
            $table->string('bukti_scan_path')->nullable(); // Path to PDF/Image scan rapor
            $table->timestamps();
        });

        // 3. Tabel Syarat Khusus Prodi SPMB
        Schema::create('spmb_syarat_prodi', function (Blueprint $table) {
            $table->id();
            // The master table for Siakad prodi is usually 'master_program_studi' or we use raw table if not created yet
            // Based on modularity, it's safer not to strictly constrain if the table name is volatile.
            // $table->foreignId('program_studi_id')->constrained('program_studi')->cascadeOnDelete();
            $table->unsignedBigInteger('program_studi_id');
            $table->text('syarat_text'); // Contoh: "Tidak Buta Warna", "Lulusan SMA IPA"
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Tabel Tahapan Seleksi (Multi-stage Selection)
        Schema::create('spmb_tahapan_seleksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_id')->constrained('gelombang_penerimaan')->cascadeOnDelete();
            $table->string('nama_tahap'); // e.g. "Ujian Tulis", "Wawancara", "Kesehatan"
            $table->integer('urutan')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Tabel Nilai Per Tahapan
        Schema::create('spmb_nilai_tahapan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->foreignId('tahapan_id')->constrained('spmb_tahapan_seleksi')->cascadeOnDelete();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->string('status_lulus', 30)->nullable(); // e.g., 'lulus', 'tidak_lulus' (string not enum)
            $table->foreignId('dinilai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        // 6. Tabel Pengajuan Beasiswa
        Schema::create('spmb_pengajuan_beasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->string('jenis_beasiswa'); // e.g. "KIP-K", "Prestasi", "Tahfidz"
            $table->text('alasan')->nullable();
            $table->string('file_pendukung_path')->nullable(); // Dokumen bukti beasiswa
            $table->string('status_pengajuan', 30)->default('pending'); // 'pending', 'disetujui', 'ditolak'
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->text('catatan_reviewer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_pengajuan_beasiswa');
        Schema::dropIfExists('spmb_nilai_tahapan');
        Schema::dropIfExists('spmb_tahapan_seleksi');
        Schema::dropIfExists('spmb_syarat_prodi');
        Schema::dropIfExists('spmb_riwayat_rapor');
        Schema::dropIfExists('spmb_sekolah_mitra');
    }
};
