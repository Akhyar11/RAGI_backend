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
        if (!Schema::hasTable('siakad_pkl')) {
            Schema::create('siakad_pkl', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mahasiswa_id');
                $table->unsignedBigInteger('tahun_akademik_id');
                $table->unsignedBigInteger('program_studi_id');
                $table->unsignedBigInteger('dosen_pembimbing_id')->nullable();
                $table->enum('jenis', ['pkl_reguler', 'magang_industri', 'magang_mbkm', 'studi_independen'])->default('pkl_reguler');
                $table->string('judul_laporan', 255)->nullable();
                $table->string('instansi_nama', 200);
                $table->text('instansi_alamat')->nullable();
                $table->string('instansi_kota', 100)->nullable();
                $table->string('pembimbing_lapangan_nama', 150)->nullable();
                $table->string('pembimbing_lapangan_kontak', 50)->nullable();
                $table->string('pembimbing_lapangan_jabatan', 100)->nullable();
                $table->date('tanggal_mulai');
                $table->date('tanggal_selesai');
                $table->enum('status', ['draft', 'diajukan', 'disetujui', 'sedang_berjalan', 'laporan_selesai', 'dinilai', 'ditolak'])->default('draft');
                $table->text('catatan_penolakan')->nullable();
                $table->string('berkas_permohonan', 255)->nullable();
                $table->string('berkas_laporan_akhir', 255)->nullable();
                $table->string('berkas_sertifikat', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('mahasiswa_id', 'fk_siakad_pkl_mahasiswa')->references('id')->on('siakad_mahasiswa')->onDelete('cascade');
                $table->foreign('tahun_akademik_id', 'fk_siakad_pkl_ta')->references('id')->on('siakad_tahun_akademik');
                $table->foreign('program_studi_id', 'fk_siakad_pkl_prodi')->references('id')->on('siakad_program_studi');
                $table->foreign('dosen_pembimbing_id', 'fk_siakad_pkl_dosen')->references('id')->on('siakad_dosen')->onDelete('set null');

                $table->index(['mahasiswa_id', 'status'], 'idx_pkl_mhs_status');
                $table->index(['program_studi_id', 'tahun_akademik_id'], 'idx_pkl_prodi_ta');
                $table->index('dosen_pembimbing_id', 'idx_pkl_dosen');
            });
        }

        if (!Schema::hasTable('siakad_pkl_bimbingan')) {
            Schema::create('siakad_pkl_bimbingan', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pkl_id');
                $table->date('tanggal');
                $table->text('aktivitas');
                $table->text('target_capaian')->nullable();
                $table->text('kendala')->nullable();
                $table->string('file_lampiran', 255)->nullable();
                $table->enum('status_verifikasi', ['pending', 'disetujui', 'revisi'])->default('pending');
                $table->text('catatan_dosen')->nullable();
                $table->timestamp('diverifikasi_oleh_dosen_at')->nullable();
                $table->timestamps();

                $table->foreign('pkl_id', 'fk_siakad_pkl_bimb_pkl')->references('id')->on('siakad_pkl')->onDelete('cascade');
                $table->index(['pkl_id', 'tanggal'], 'idx_pkl_bimbingan_tanggal');
            });
        }

        if (!Schema::hasTable('siakad_pkl_penilaian')) {
            Schema::create('siakad_pkl_penilaian', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pkl_id')->unique('uk_siakad_pkl_penilaian_pkl');
                $table->unsignedBigInteger('penilai_dosen_id')->nullable();
                $table->decimal('nilai_lapangan_kedisiplinan', 5, 2)->default(0.00);
                $table->decimal('nilai_lapangan_kinerja', 5, 2)->default(0.00);
                $table->decimal('nilai_lapangan_kemampuan_teknis', 5, 2)->default(0.00);
                $table->decimal('nilai_lapangan_inisiatif', 5, 2)->default(0.00);
                $table->decimal('nilai_lapangan_total', 5, 2)->default(0.00);
                $table->string('berkas_nilai_lapangan', 255)->nullable();
                $table->text('catatan_lapangan')->nullable();
                $table->decimal('nilai_dosen_bimbingan', 5, 2)->default(0.00);
                $table->decimal('nilai_dosen_laporan', 5, 2)->default(0.00);
                $table->decimal('nilai_dosen_presentasi', 5, 2)->default(0.00);
                $table->decimal('nilai_dosen_total', 5, 2)->default(0.00);
                $table->text('catatan_dosen')->nullable();
                $table->decimal('bobot_lapangan_persen', 4, 2)->default(40.00);
                $table->decimal('bobot_dosen_persen', 4, 2)->default(60.00);
                $table->decimal('nilai_angka_akhir', 5, 2)->default(0.00);
                $table->string('nilai_huruf_akhir', 3)->nullable();
                $table->decimal('bobot_mutu_akhir', 3, 2)->default(0.00);
                $table->boolean('is_finalized')->default(false);
                $table->timestamp('finalized_at')->nullable();
                $table->unsignedBigInteger('finalized_by')->nullable();
                $table->timestamps();

                $table->foreign('pkl_id', 'fk_siakad_pkl_nilai_pkl')->references('id')->on('siakad_pkl')->onDelete('cascade');
                $table->foreign('penilai_dosen_id', 'fk_siakad_pkl_nilai_dosen')->references('id')->on('siakad_dosen')->onDelete('set null');
                $table->foreign('finalized_by', 'fk_siakad_pkl_nilai_finalizer')->references('id')->on('core_users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_pkl_penilaian');
        Schema::dropIfExists('siakad_pkl_bimbingan');
        Schema::dropIfExists('siakad_pkl');
    }
};
