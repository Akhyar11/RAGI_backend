<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master Kategori SKP (Pendidikan, Penelitian, Pengabdian, Penunjang, Tugas Tambahan)
        Schema::create('simpeg_master_kategori_skp', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->text('deskripsi')->nullable();
            $table->integer('urutan')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Tambah kolom alur & evaluator pada simpeg_penilaian_kinerja
        Schema::table('simpeg_penilaian_kinerja', function (Blueprint $table) {
            $table->enum('status', ['draft', 'diajukan', 'disetujui', 'dinilai'])->default('draft')->after('semester');
            $table->foreignId('pejabat_penilai_id')->nullable()->after('status')->constrained('simpeg_pegawai')->nullOnDelete();
            $table->dateTime('tanggal_pengajuan')->nullable()->after('pejabat_penilai_id');
            $table->dateTime('tanggal_persetujuan')->nullable()->after('tanggal_pengajuan');
            $table->dateTime('evaluated_at')->nullable()->after('catatan_evaluator');
        });

        // 3. Tabel Butir SKP Item (Target & Realisasi Butir-per-Butir)
        Schema::create('simpeg_skp_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penilaian_kinerja_id')->constrained('simpeg_penilaian_kinerja')->onDelete('cascade');
            $table->foreignId('kategori_skp_id')->constrained('simpeg_master_kategori_skp')->onDelete('restrict');
            $table->text('uraian_tugas');
            
            // Target
            $table->string('target_output');
            $table->decimal('target_mutu', 5, 2)->default(100.00);
            $table->string('target_waktu'); // e.g., "6 Bulan" atau "180 Hari"
            $table->decimal('target_biaya', 15, 2)->nullable();

            // Realisasi
            $table->string('realisasi_output')->nullable();
            $table->decimal('realisasi_mutu', 5, 2)->nullable();
            $table->string('realisasi_waktu')->nullable();
            $table->decimal('realisasi_biaya', 15, 2)->nullable();

            // Capaian & Verifikasi
            $table->decimal('nilai_capaian', 5, 2)->nullable();
            $table->string('berkas_bukti')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('penilaian_kinerja_id');
            $table->index('kategori_skp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simpeg_skp_item');

        Schema::table('simpeg_penilaian_kinerja', function (Blueprint $table) {
            $table->dropForeign(['pejabat_penilai_id']);
            $table->dropColumn([
                'status',
                'pejabat_penilai_id',
                'tanggal_pengajuan',
                'tanggal_persetujuan',
                'evaluated_at',
            ]);
        });

        Schema::dropIfExists('simpeg_master_kategori_skp');
    }
};
