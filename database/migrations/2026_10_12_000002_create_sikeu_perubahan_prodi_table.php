<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histori perpindahan program studi / perubahan jenis pendaftaran.
     *
     * Tagihan lama TIDAK diubah. Perubahan hanya mengatur konteks EFEKTIF
     * (tahun_akademik_id + semester) untuk penagihan masa depan.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sikeu_mahasiswa_perubahan_prodi')) {
            Schema::create('sikeu_mahasiswa_perubahan_prodi', function (Blueprint $table) {
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
                $table->index(['mahasiswa_id', 'status']);
                $table->index(['tahun_akademik_id', 'semester_efektif'], 'sikeu_perubahan_prodi_ta_sem_idx');
            });
        }

        // Versioning tipe tagihan agar histori jalur/kelompok terbaca saat audit.
        if (Schema::hasTable('sikeu_mahasiswa_tipe_tagihan')) {
            Schema::table('sikeu_mahasiswa_tipe_tagihan', function (Blueprint $table) {
                if (!Schema::hasColumn('sikeu_mahasiswa_tipe_tagihan', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('status_pendaftaran');
                }
                if (!Schema::hasColumn('sikeu_mahasiswa_tipe_tagihan', 'version')) {
                    $table->integer('version')->default(1)->after('is_active');
                }
                if (!Schema::hasColumn('sikeu_mahasiswa_tipe_tagihan', 'effective_tahun_akademik_id')) {
                    $table->unsignedBigInteger('effective_tahun_akademik_id')->nullable()->after('version');
                }
                if (!Schema::hasColumn('sikeu_mahasiswa_tipe_tagihan', 'effective_semester')) {
                    $table->integer('effective_semester')->nullable()->after('effective_tahun_akademik_id');
                }
                if (!Schema::hasColumn('sikeu_mahasiswa_tipe_tagihan', 'perubahan_prodi_id')) {
                    $table->unsignedBigInteger('perubahan_prodi_id')->nullable()->after('effective_semester');
                }
            });

            // Index terpisah (idempoten via try/catch nama duplikat).
            try {
                Schema::table('sikeu_mahasiswa_tipe_tagihan', function (Blueprint $table) {
                    $table->index(['mahasiswa_id', 'is_active'], 'tipe_tagihan_mhs_active_idx');
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sikeu_mahasiswa_tipe_tagihan')) {
            Schema::table('sikeu_mahasiswa_tipe_tagihan', function (Blueprint $table) {
                try {
                    $table->dropIndex('tipe_tagihan_mhs_active_idx');
                } catch (\Throwable $e) {
                }
                foreach (['perubahan_prodi_id', 'effective_semester', 'effective_tahun_akademik_id', 'version', 'is_active'] as $col) {
                    try {
                        $table->dropColumn($col);
                    } catch (\Throwable $e) {
                    }
                }
            });
        }

        Schema::dropIfExists('sikeu_mahasiswa_perubahan_prodi');
    }
};
