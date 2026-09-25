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
        // 1. Pengaturan LMS per Kelas (override setting sistem)
        if (!Schema::hasTable('lms_kelas_setting')) {
            Schema::create('lms_kelas_setting', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kelas_id')->unique()->constrained('siakad_kelas')->cascadeOnDelete();
                $table->integer('total_pertemuan')->default(16);
                $table->enum('metode_absensi', ['manual_dosen', 'token_mahasiswa', 'keduanya'])->default('keduanya');
                $table->integer('batas_min_hadir_persen')->default(75);
                $table->boolean('allow_late_submission')->default(true);
                $table->boolean('show_nilai_to_mahasiswa')->default(false);
                $table->string('storage_disk', 50)->nullable(); // null = ikuti konfigurasi sistem
                $table->timestamps();
            });
        }

        // 2. Materi Pembelajaran per Pertemuan
        if (!Schema::hasTable('lms_materi_pertemuan')) {
            Schema::create('lms_materi_pertemuan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pertemuan_id')->constrained('siakad_pertemuan')->cascadeOnDelete();
                $table->string('judul');
                $table->longText('deskripsi')->nullable();
                $table->enum('tipe_konten', ['teks', 'file', 'link_eksternal', 'video_embed'])->default('teks');
                $table->string('link_eksternal')->nullable();
                $table->integer('urutan')->default(1);
                $table->boolean('is_published')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['pertemuan_id', 'is_published', 'urutan']);
            });
        }

        // 3. File Lampiran Materi Pembelajaran
        if (!Schema::hasTable('lms_materi_file')) {
            Schema::create('lms_materi_file', function (Blueprint $table) {
                $table->id();
                $table->foreignId('materi_id')->constrained('lms_materi_pertemuan')->cascadeOnDelete();
                $table->string('nama_file');
                $table->string('file_path');
                $table->string('disk', 50)->default('r2');
                $table->string('mime_type', 100)->nullable();
                $table->bigInteger('ukuran_bytes')->nullable();
                $table->timestamps();
            });
        }

        // 4. Tugas / Assignment Perkuliahan
        if (!Schema::hasTable('lms_tugas')) {
            Schema::create('lms_tugas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pertemuan_id')->constrained('siakad_pertemuan')->cascadeOnDelete();
                $table->foreignId('komponen_penilaian_id')->nullable()->constrained('siakad_komponen_penilaian')->nullOnDelete();
                $table->string('judul');
                $table->longText('deskripsi')->nullable();
                $table->dateTime('deadline')->nullable();
                $table->integer('maks_nilai')->default(100);
                $table->boolean('allow_late_submission')->default(true);
                $table->boolean('is_published')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['pertemuan_id', 'is_published', 'deadline']);
            });
        }

        // 5. Pengumpulan Tugas Mahasiswa
        if (!Schema::hasTable('lms_pengumpulan_tugas')) {
            Schema::create('lms_pengumpulan_tugas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tugas_id')->constrained('lms_tugas')->cascadeOnDelete();
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->text('catatan_mahasiswa')->nullable();
                $table->string('file_path')->nullable();
                $table->string('disk', 50)->default('r2');
                $table->string('nama_file_asli')->nullable();
                $table->bigInteger('ukuran_bytes')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->boolean('is_late')->default(false);
                $table->decimal('nilai', 5, 2)->nullable();
                $table->text('feedback_dosen')->nullable();
                $table->timestamp('dinilai_at')->nullable();
                $table->foreignId('dinilai_oleh')->nullable()->constrained('core_users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tugas_id', 'mahasiswa_id']);
                $table->index(['mahasiswa_id', 'nilai']);
            });
        }

        // 6. Pengajuan Izin / Sakit Mahasiswa untuk Absensi
        if (!Schema::hasTable('lms_izin_absensi')) {
            Schema::create('lms_izin_absensi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pertemuan_id')->constrained('siakad_pertemuan')->cascadeOnDelete();
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->enum('tipe_izin', ['sakit', 'izin'])->default('izin');
                $table->text('alasan');
                $table->string('surat_path')->nullable();
                $table->string('disk', 50)->nullable();
                $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
                $table->text('catatan_dosen')->nullable();
                $table->foreignId('diproses_oleh')->nullable()->constrained('core_users')->nullOnDelete();
                $table->timestamp('diproses_at')->nullable();
                $table->timestamps();

                $table->unique(['pertemuan_id', 'mahasiswa_id']);
                $table->index(['pertemuan_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_izin_absensi');
        Schema::dropIfExists('lms_pengumpulan_tugas');
        Schema::dropIfExists('lms_tugas');
        Schema::dropIfExists('lms_materi_file');
        Schema::dropIfExists('lms_materi_pertemuan');
        Schema::dropIfExists('lms_kelas_setting');
    }
};
