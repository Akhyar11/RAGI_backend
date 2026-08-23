<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for SIAKAD OBE (Outcome-Based Education).
     */
    public function up(): void
    {
        // 1. Capaian Pembelajaran Lulusan (CPL) per Program Studi
        Schema::create('siakad_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')->constrained('spmb_master_program_studi')->cascadeOnDelete();
            $table->string('kode_cpl', 50); // e.g. CPL-01, S-1, P-1, KU-1, KK-1
            $table->enum('kategori', ['sikap', 'pengetahuan', 'keterampilan_umum', 'keterampilan_khusus'])->default('pengetahuan');
            $table->text('deskripsi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['program_studi_id', 'kode_cpl']);
        });

        // 2. Capaian Pembelajaran Mata Kuliah (CPMK)
        Schema::create('siakad_cpmk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('siakad_mata_kuliah')->cascadeOnDelete();
            $table->foreignId('cpl_id')->nullable()->constrained('siakad_cpl')->nullOnDelete();
            $table->string('kode_cpmk', 50); // e.g. CPMK-1, CPMK-2
            $table->text('deskripsi');
            $table->decimal('bobot_persentase', 5, 2)->default(0); // Bobot terhadap total nilai MK
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['mata_kuliah_id', 'kode_cpmk']);
        });

        // 3. Sub-CPMK (Indikator Capaian Pembelajaran Khusus)
        Schema::create('siakad_sub_cpmk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpmk_id')->constrained('siakad_cpmk')->cascadeOnDelete();
            $table->string('kode_sub_cpmk', 50); // e.g. Sub-CPMK 1.1
            $table->text('deskripsi');
            $table->text('indikator')->nullable();
            $table->decimal('bobot_persentase', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Matrix Pemetaan CPL ke CPMK (Many-to-Many kontribusi)
        Schema::create('siakad_pemetaan_cpl_cpmk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpl_id')->constrained('siakad_cpl')->cascadeOnDelete();
            $table->foreignId('cpmk_id')->constrained('siakad_cpmk')->cascadeOnDelete();
            $table->decimal('bobot_kontribusi', 5, 2)->default(100.00);
            $table->timestamps();

            $table->unique(['cpl_id', 'cpmk_id']);
        });

        // 5. Komponen Penilaian Dinamis OBE per Kelas Perkuliahan
        Schema::create('siakad_komponen_penilaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('siakad_kelas')->cascadeOnDelete();
            $table->foreignId('cpmk_id')->nullable()->constrained('siakad_cpmk')->nullOnDelete();
            $table->foreignId('sub_cpmk_id')->nullable()->constrained('siakad_sub_cpmk')->nullOnDelete();
            $table->string('nama_komponen'); // e.g. "Tugas 1 - Logika", "Kuis 1", "UTS Evaluasi CPMK 1 & 2", "Proyek PBL", "UAS"
            $table->enum('teknik_penilaian', [
                'tes_tulis',
                'tes_lisan',
                'proyek',
                'praktikum',
                'unjuk_kerja',
                'portofolio',
                'partisipatif',
                'tugas',
                'kuis',
                'lainnya'
            ])->default('tugas');
            $table->decimal('bobot', 5, 2)->default(10.00); // Bobot terhadap 100% nilai akhir
            $table->integer('urutan')->default(1);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Nilai Mahasiswa per Komponen Asesmen Dinamis OBE
        Schema::create('siakad_nilai_komponen_mhs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_detail_id')->constrained('siakad_krs_detail')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('siakad_komponen_penilaian')->cascadeOnDelete();
            $table->decimal('nilai_angka', 5, 2)->default(0); // 0.00 - 100.00
            $table->text('catatan_feedback')->nullable();
            $table->foreignId('diinput_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['krs_detail_id', 'komponen_penilaian_id'], 'unique_krs_komponen_mhs');
        });

        // 7. Ketercapaian CPMK Mahasiswa (Hasil Kalkulasi / Evaluasi Portofolio OBE)
        Schema::create('siakad_ketercapaian_cpmk_mhs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_detail_id')->constrained('siakad_krs_detail')->cascadeOnDelete();
            $table->foreignId('cpmk_id')->constrained('siakad_cpmk')->cascadeOnDelete();
            $table->decimal('skor_ketercapaian', 5, 2)->default(0); // 0.00 - 100.00
            $table->enum('status_ketercapaian', ['tercapai', 'belum_tercapai'])->default('tercapai');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['krs_detail_id', 'cpmk_id'], 'unique_krs_cpmk_mhs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_ketercapaian_cpmk_mhs');
        Schema::dropIfExists('siakad_nilai_komponen_mhs');
        Schema::dropIfExists('siakad_komponen_penilaian');
        Schema::dropIfExists('siakad_pemetaan_cpl_cpmk');
        Schema::dropIfExists('siakad_sub_cpmk');
        Schema::dropIfExists('siakad_cpmk');
        Schema::dropIfExists('siakad_cpl');
    }
};
