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
        // 1. Tabel Master Skala Nilai / Bobot Mutu Akademik (Wajib Zero Hardcode untuk Penilaian)
        if (!Schema::hasTable('siakad_skala_nilai')) {
            Schema::create('siakad_skala_nilai', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_studi_id')->nullable()->constrained('spmb_master_program_studi')->cascadeOnDelete();
                $table->string('nilai_huruf', 5); // A, A-, B+, B, B-, C+, C, D, E
                $table->decimal('bobot_indeks', 4, 2); // 4.00, 3.75, 3.50, dst.
                $table->decimal('batas_bawah', 5, 2); // misal 85.00
                $table->decimal('batas_atas', 5, 2); // misal 100.00
                $table->boolean('is_lulus')->default(true);
                $table->string('keterangan', 100)->nullable(); // Sangat Baik, Baik, Cukup, Kurang, Gagal
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['program_studi_id', 'is_active'], 'siakad_skala_prodi_active_idx');
            });
        }

        // 2. Tambah kolom batas jadwal kegiatan akademik ke tabel master tahun akademik (Kalender KRS, Nilai, dll)
        Schema::table('spmb_master_tahun_akademik', function (Blueprint $table) {
            if (!Schema::hasColumn('spmb_master_tahun_akademik', 'krs_mulai')) {
                $table->date('krs_mulai')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('spmb_master_tahun_akademik', 'krs_selesai')) {
                $table->date('krs_selesai')->nullable()->after('krs_mulai');
            }
            if (!Schema::hasColumn('spmb_master_tahun_akademik', 'kprs_mulai')) {
                $table->date('kprs_mulai')->nullable()->after('krs_selesai');
            }
            if (!Schema::hasColumn('spmb_master_tahun_akademik', 'kprs_selesai')) {
                $table->date('kprs_selesai')->nullable()->after('kprs_mulai');
            }
            if (!Schema::hasColumn('spmb_master_tahun_akademik', 'perkuliahan_mulai')) {
                $table->date('perkuliahan_mulai')->nullable()->after('kprs_selesai');
            }
            if (!Schema::hasColumn('spmb_master_tahun_akademik', 'perkuliahan_selesai')) {
                $table->date('perkuliahan_selesai')->nullable()->after('perkuliahan_mulai');
            }
            if (!Schema::hasColumn('spmb_master_tahun_akademik', 'input_nilai_mulai')) {
                $table->date('input_nilai_mulai')->nullable()->after('perkuliahan_selesai');
            }
            if (!Schema::hasColumn('spmb_master_tahun_akademik', 'input_nilai_selesai')) {
                $table->date('input_nilai_selesai')->nullable()->after('input_nilai_mulai');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spmb_master_tahun_akademik', function (Blueprint $table) {
            $cols = ['krs_mulai', 'krs_selesai', 'kprs_mulai', 'kprs_selesai', 'perkuliahan_mulai', 'perkuliahan_selesai', 'input_nilai_mulai', 'input_nilai_selesai'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('spmb_master_tahun_akademik', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        Schema::dropIfExists('siakad_skala_nilai');
    }
};
