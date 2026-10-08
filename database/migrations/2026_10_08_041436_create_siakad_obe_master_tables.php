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
        // 1. Rumpun Mata Kuliah (per prodi atau institusi)
        if (!Schema::hasTable('siakad_rumpun_mk')) {
            Schema::create('siakad_rumpun_mk', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_studi_id')->nullable()->constrained('siakad_program_studi')->nullOnDelete();
                $table->string('kode_rumpun', 50)->unique();
                $table->string('nama_rumpun', 150);
                $table->text('deskripsi')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Jenis / Kategori CPL (Sikap, Keterampilan Umum, Keterampilan Khusus, Pengetahuan, dll)
        if (!Schema::hasTable('siakad_jenis_cpl')) {
            Schema::create('siakad_jenis_cpl', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_studi_id')->nullable()->constrained('siakad_program_studi')->nullOnDelete();
                $table->string('kode_jenis', 50)->unique();
                $table->string('nama_jenis', 150);
                $table->text('deskripsi')->nullable();
                $table->integer('urutan')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. Tambah relasi rumpun_mk_id & jenis_cpl_id ke mata kuliah & CPL jika belum ada
        if (Schema::hasTable('siakad_mata_kuliah') && !Schema::hasColumn('siakad_mata_kuliah', 'rumpun_mk_id')) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table) {
                $table->foreignId('rumpun_mk_id')->nullable()->after('kurikulum_id')->constrained('siakad_rumpun_mk')->nullOnDelete();
            });
        }

        if (Schema::hasTable('siakad_cpl') && !Schema::hasColumn('siakad_cpl', 'jenis_cpl_id')) {
            Schema::table('siakad_cpl', function (Blueprint $table) {
                $table->foreignId('jenis_cpl_id')->nullable()->after('program_studi_id')->constrained('siakad_jenis_cpl')->nullOnDelete();
            });
        }

        // 4. Rubrik Penilaian OBE (Skala & Deskriptor per Tingkat Capaian)
        if (!Schema::hasTable('siakad_obe_rubrik')) {
            Schema::create('siakad_obe_rubrik', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_studi_id')->nullable()->constrained('siakad_program_studi')->nullOnDelete();
                $table->foreignId('cpmk_id')->nullable()->constrained('siakad_cpmk')->nullOnDelete();
                $table->string('kode_rubrik', 50)->unique();
                $table->string('nama_rubrik', 255);
                $table->enum('tipe_rubrik', ['holistik', 'analitik', 'skala_persepsi'])->default('analitik');
                $table->text('deskripsi')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 5. Kriteria & Skala Skor Detail Rubrik
        if (!Schema::hasTable('siakad_obe_rubrik_kriteria')) {
            Schema::create('siakad_obe_rubrik_kriteria', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rubrik_id')->constrained('siakad_obe_rubrik')->cascadeOnDelete();
                $table->string('nama_kriteria', 255);
                $table->decimal('bobot_persen', 5, 2)->default(0);
                $table->text('deskripsi_sangat_baik')->nullable(); // Skor A (80-100)
                $table->text('deskripsi_baik')->nullable();        // Skor B (68-79)
                $table->text('deskripsi_cukup')->nullable();       // Skor C (56-67)
                $table->text('deskripsi_kurang')->nullable();      // Skor D/E (<56)
                $table->integer('urutan')->default(1);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_obe_rubrik_kriteria');
        Schema::dropIfExists('siakad_obe_rubrik');

        if (Schema::hasTable('siakad_cpl') && Schema::hasColumn('siakad_cpl', 'jenis_cpl_id')) {
            Schema::table('siakad_cpl', function (Blueprint $table) {
                $table->dropForeign(['jenis_cpl_id']);
                $table->dropColumn('jenis_cpl_id');
            });
        }

        if (Schema::hasTable('siakad_mata_kuliah') && Schema::hasColumn('siakad_mata_kuliah', 'rumpun_mk_id')) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table) {
                $table->dropForeign(['rumpun_mk_id']);
                $table->dropColumn('rumpun_mk_id');
            });
        }

        Schema::dropIfExists('siakad_jenis_cpl');
        Schema::dropIfExists('siakad_rumpun_mk');
    }
};
