<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan pindah program studi — pemilik: SIAKAD.
     *
     * SIAKAD adalah satu-satunya penulis mutasi prodi. Persetujuan dua sisi:
     * akademik (kaprodi/warek) + sign-off keuangan (cek tunggakan SIKEU).
     * Saat applied, event MahasiswaPindahProdi didispatch dan SIKEU
     * mencatat proyeksi konteks penagihannya via listener (queued).
     */
    public function up(): void
    {
        if (!Schema::hasTable('siakad_mahasiswa_pindah_prodi')) {
            Schema::create('siakad_mahasiswa_pindah_prodi', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mahasiswa_id')->index();
                $table->unsignedBigInteger('old_program_studi_id')->nullable()->index();
                $table->unsignedBigInteger('new_program_studi_id')->index();
                $table->unsignedBigInteger('tahun_akademik_id')->nullable()->index();
                $table->integer('semester_efektif')->nullable()->index();
                $table->date('tanggal_efektif')->nullable();
                $table->string('jenis_pendaftaran_lama', 50)->nullable();
                $table->string('jenis_pendaftaran_baru', 50)->nullable();
                $table->text('alasan');
                $table->string('referensi_surat')->nullable();
                $table->string('dokumen_path')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'applied', 'cancelled'])->default('pending')->index();
                // Sign-off keuangan (wajib sebelum approval akademik).
                $table->unsignedBigInteger('dicek_keuangan_oleh')->nullable();
                $table->timestamp('dicek_keuangan_pada')->nullable();
                $table->enum('hasil_cek_keuangan', ['layak', 'ada_tunggakan'])->nullable();
                $table->text('catatan_keuangan')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('applied_by')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->text('catatan_approval')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('requested_by')->references('id')->on('core_users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('core_users')->onDelete('set null');
                $table->foreign('applied_by')->references('id')->on('core_users')->onDelete('set null');
                $table->foreign('dicek_keuangan_oleh')->references('id')->on('core_users')->onDelete('set null');
                $table->index(['mahasiswa_id', 'status']);
                $table->index(['tahun_akademik_id', 'semester_efektif']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_mahasiswa_pindah_prodi');
    }
};
