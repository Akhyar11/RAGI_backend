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
        // 1. Rumpun MK: Tambah dosen_koordinator_id
        if (Schema::hasTable('siakad_rumpun_mk') && !Schema::hasColumn('siakad_rumpun_mk', 'dosen_koordinator_id')) {
            Schema::table('siakad_rumpun_mk', function (Blueprint $table) {
                $table->foreignId('dosen_koordinator_id')->nullable()->after('nama_rumpun')->constrained('siakad_dosen')->nullOnDelete();
            });
        }

        // 2. Mata Kuliah: Tambah kategori (Wajib/Pilihan), jumlah_pertemuan
        if (Schema::hasTable('siakad_mata_kuliah')) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table) {
                if (!Schema::hasColumn('siakad_mata_kuliah', 'kategori')) {
                    $table->string('kategori', 50)->nullable()->after('tipe');
                }
                if (!Schema::hasColumn('siakad_mata_kuliah', 'jumlah_pertemuan')) {
                    $table->integer('jumlah_pertemuan')->default(16)->after('total_sks');
                }
            });
        }

        // 3. Rubrik Kriteria: Tambah skor_min, skor_max, deskripsi
        if (Schema::hasTable('siakad_obe_rubrik_kriteria')) {
            Schema::table('siakad_obe_rubrik_kriteria', function (Blueprint $table) {
                if (!Schema::hasColumn('siakad_obe_rubrik_kriteria', 'skor_min')) {
                    $table->decimal('skor_min', 5, 2)->default(0)->after('nama_kriteria');
                }
                if (!Schema::hasColumn('siakad_obe_rubrik_kriteria', 'skor_max')) {
                    $table->decimal('skor_max', 5, 2)->default(100)->after('skor_min');
                }
                if (!Schema::hasColumn('siakad_obe_rubrik_kriteria', 'deskripsi')) {
                    $table->text('deskripsi')->nullable()->after('skor_max');
                }
            });
        }

        // 4. Tabel Tambah Mengajar / Distribusi Mengajar Dosen
        if (!Schema::hasTable('siakad_distribusi_mengajar')) {
            Schema::create('siakad_distribusi_mengajar', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_studi_id')->constrained('siakad_program_studi')->cascadeOnDelete();
                $table->foreignId('tahun_akademik_id')->nullable()->constrained('siakad_tahun_akademik')->nullOnDelete();
                $table->foreignId('kurikulum_id')->nullable()->constrained('siakad_kurikulum')->nullOnDelete();
                $table->foreignId('mata_kuliah_id')->constrained('siakad_mata_kuliah')->cascadeOnDelete();
                $table->integer('semester')->default(1);
                $table->foreignId('dosen_koordinator_id')->nullable()->constrained('siakad_dosen')->nullOnDelete();
                $table->json('dosen_anggota_ids')->nullable(); // array of dosen IDs
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siakad_distribusi_mengajar');
    }
};

