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
        if (!Schema::hasTable('siakad_tugas_akhir')) {
            Schema::create('siakad_tugas_akhir', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mahasiswa_id');
                $table->unsignedBigInteger('tahun_akademik_id');
                $table->unsignedBigInteger('program_studi_id');
                $table->string('judul_utama', 255);
                $table->string('judul_inggris', 255)->nullable();
                $table->string('bidang_kajian', 100)->nullable();
                $table->text('abstrak')->nullable();
                $table->unsignedBigInteger('dosen_pembimbing_1_id')->nullable();
                $table->unsignedBigInteger('dosen_pembimbing_2_id')->nullable();
                $table->enum('tahap', ['proposal', 'penelitian', 'seminar_hasil', 'sidang_skripsi', 'revisi_akhir', 'lulus'])->default('proposal');
                $table->enum('status', ['draft', 'diajukan', 'disetujui_prodi', 'sedang_bimbingan', 'siap_sidang', 'revisi', 'selesai', 'ditolak'])->default('draft');
                $table->string('nomor_sk_pembimbing', 100)->nullable();
                $table->date('tanggal_sk_pembimbing')->nullable();
                $table->string('berkas_proposal', 255)->nullable();
                $table->string('berkas_laporan_akhir', 255)->nullable();
                $table->string('berkas_persetujuan_sidang', 255)->nullable();
                $table->integer('total_sesi_bimbingan')->default(0);
                $table->decimal('nilai_angka_akhir', 5, 2)->nullable();
                $table->string('nilai_huruf_akhir', 3)->nullable();
                $table->decimal('bobot_mutu_akhir', 3, 2)->nullable();
                $table->text('catatan_prodi')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('mahasiswa_id', 'fk_siakad_ta_mhs')->references('id')->on('siakad_mahasiswa')->onDelete('cascade');
                $table->foreign('tahun_akademik_id', 'fk_siakad_ta_ta')->references('id')->on('siakad_tahun_akademik');
                $table->foreign('program_studi_id', 'fk_siakad_ta_prodi')->references('id')->on('siakad_program_studi');
                $table->foreign('dosen_pembimbing_1_id', 'fk_siakad_ta_pemb1')->references('id')->on('siakad_dosen')->onDelete('set null');
                $table->foreign('dosen_pembimbing_2_id', 'fk_siakad_ta_pemb2')->references('id')->on('siakad_dosen')->onDelete('set null');

                $table->index(['mahasiswa_id', 'status'], 'idx_ta_mhs_status');
                $table->index(['program_studi_id', 'tahap', 'status'], 'idx_ta_prodi_tahap');
                $table->index('dosen_pembimbing_1_id', 'idx_ta_pemb1');
                $table->index('dosen_pembimbing_2_id', 'idx_ta_pemb2');
            });
        }

        if (!Schema::hasTable('siakad_ta_bimbingan')) {
            Schema::create('siakad_ta_bimbingan', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tugas_akhir_id');
                $table->unsignedBigInteger('dosen_id');
                $table->date('tanggal');
                $table->string('bab_pembahasan', 100);
                $table->text('uraian_konsultasi');
                $table->text('arahan_revisi')->nullable();
                $table->string('berkas_draft', 255)->nullable();
                $table->enum('status_validasi', ['menunggu', 'acc', 'revisi'])->default('menunggu');
                $table->timestamps();

                $table->foreign('tugas_akhir_id', 'fk_siakad_ta_bimb_ta')->references('id')->on('siakad_tugas_akhir')->onDelete('cascade');
                $table->foreign('dosen_id', 'fk_siakad_ta_bimb_dosen')->references('id')->on('siakad_dosen')->onDelete('restrict');
                $table->index(['tugas_akhir_id', 'tanggal'], 'idx_ta_bimb_ta_tgl');
            });
        }

        if (!Schema::hasTable('siakad_ta_sidang')) {
            Schema::create('siakad_ta_sidang', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tugas_akhir_id');
                $table->enum('jenis_sidang', ['proposal', 'seminar_hasil', 'sidang_skripsi']);
                $table->string('periode_sidang', 50)->nullable();
                $table->date('tanggal_sidang');
                $table->time('jam_mulai');
                $table->time('jam_selesai');
                $table->unsignedBigInteger('ruangan_id')->nullable();
                $table->string('ruangan_nama_manual', 100)->nullable();
                $table->unsignedBigInteger('ketua_sidang_id')->nullable();
                $table->unsignedBigInteger('sekretaris_sidang_id')->nullable();
                $table->enum('status_kelulusan', ['belum_sidang', 'lulus_tanpa_revisi', 'lulus_revisi_ringan', 'lulus_revisi_berat', 'mengulang'])->default('belum_sidang');
                $table->date('batas_waktu_revisi')->nullable();
                $table->text('catatan_sidang')->nullable();
                $table->string('berkas_bap', 255)->nullable();
                $table->boolean('is_locked')->default(false);
                $table->timestamps();

                $table->foreign('tugas_akhir_id', 'fk_siakad_ta_sidang_ta')->references('id')->on('siakad_tugas_akhir')->onDelete('cascade');
                $table->foreign('ketua_sidang_id', 'fk_siakad_ta_sidang_ketua')->references('id')->on('siakad_dosen')->onDelete('set null');
                $table->foreign('sekretaris_sidang_id', 'fk_siakad_ta_sidang_sekre')->references('id')->on('siakad_dosen')->onDelete('set null');
                $table->foreign('ruangan_id', 'fk_siakad_ta_sidang_ruangan')->references('id')->on('sinapra_ruangan')->onDelete('set null');
                $table->index(['tanggal_sidang', 'jenis_sidang'], 'idx_ta_sidang_tgl');
            });
        }

        if (!Schema::hasTable('siakad_ta_penguji')) {
            Schema::create('siakad_ta_penguji', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sidang_id');
                $table->unsignedBigInteger('dosen_id');
                $table->enum('peran_penguji', ['ketua', 'sekretaris', 'penguji_1', 'penguji_2', 'penguji_luar']);
                $table->enum('kehadiran', ['hadir', 'izin', 'tidak_hadir'])->default('hadir');
                $table->timestamps();

                $table->foreign('sidang_id', 'fk_siakad_ta_penguji_sidang')->references('id')->on('siakad_ta_sidang')->onDelete('cascade');
                $table->foreign('dosen_id', 'fk_siakad_ta_penguji_dosen')->references('id')->on('siakad_dosen')->onDelete('restrict');
                $table->unique(['sidang_id', 'dosen_id'], 'uk_sidang_dosen');
            });
        }

        if (!Schema::hasTable('siakad_ta_nilai')) {
            Schema::create('siakad_ta_nilai', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('penguji_id')->unique('uk_siakad_ta_nilai_penguji');
                $table->decimal('skor_metodologi', 5, 2)->default(0.00);
                $table->decimal('skor_materi_skripsi', 5, 2)->default(0.00);
                $table->decimal('skor_presentasi', 5, 2)->default(0.00);
                $table->decimal('skor_penguasaan_materi', 5, 2)->default(0.00);
                $table->decimal('skor_total', 5, 2)->default(0.00);
                $table->text('catatan_revisi')->nullable();
                $table->boolean('is_submitted')->default(false);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->foreign('penguji_id', 'fk_siakad_ta_nilai_penguji')->references('id')->on('siakad_ta_penguji')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_ta_nilai');
        Schema::dropIfExists('siakad_ta_penguji');
        Schema::dropIfExists('siakad_ta_sidang');
        Schema::dropIfExists('siakad_ta_bimbingan');
        Schema::dropIfExists('siakad_tugas_akhir');
    }
};
