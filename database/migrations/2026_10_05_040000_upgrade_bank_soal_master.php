<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bank soal master upgrade Fase A (satu master: siakad_bank_soal).
     * Referensi fitur: Project/lms LmsBankSoal + LmsBankSoalKategori + LmsBankSoalOpsi.
     * Quiz LMS fase berikutnya hanya mereferensikan tabel ini (tanpa duplikasi).
     */
    public function up(): void
    {
        if (!Schema::hasTable('siakad_bank_soal_kategori')) {
            Schema::create('siakad_bank_soal_kategori', function (Blueprint $table) {
                $table->id();
                $table->string('nama', 100);
                $table->foreignId('mata_kuliah_id')->nullable()->constrained('siakad_mata_kuliah')->nullOnDelete();
                $table->foreignId('dibuat_oleh')->nullable()->constrained('core_users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['mata_kuliah_id', 'nama']);
            });
        }

        Schema::table('siakad_bank_soal', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_bank_soal', 'kategori_id')) {
                $table->foreignId('kategori_id')->nullable()->after('sub_cpmk_id')
                    ->constrained('siakad_bank_soal_kategori')->nullOnDelete();
            }
            // Tipe soal closed-set domain (tanpa tabel master) — enum sah.
            if (!Schema::hasColumn('siakad_bank_soal', 'tipe_soal')) {
                $table->enum('tipe_soal', ['pilihan_ganda', 'isian_singkat', 'uraian'])->default('uraian')->after('kategori_id');
            }
            if (!Schema::hasColumn('siakad_bank_soal', 'tingkat_kesulitan')) {
                $table->enum('tingkat_kesulitan', ['mudah', 'sedang', 'sukar'])->default('sedang')->after('tipe_soal');
            }
            if (!Schema::hasColumn('siakad_bank_soal', 'gambar_path')) {
                $table->string('gambar_path')->nullable()->after('pertanyaan');
            }
            if (!Schema::hasColumn('siakad_bank_soal', 'pembahasan')) {
                $table->text('pembahasan')->nullable()->after('kunci_jawaban');
            }
        });

        if (!Schema::hasTable('siakad_bank_soal_opsi')) {
            Schema::create('siakad_bank_soal_opsi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_soal_id')->constrained('siakad_bank_soal')->cascadeOnDelete();
                $table->text('teks');
                $table->string('gambar_path')->nullable();
                $table->boolean('is_benar')->default(false);
                $table->integer('urutan')->default(0);
                $table->timestamps();

                $table->index(['bank_soal_id', 'urutan']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_bank_soal_opsi');

        Schema::table('siakad_bank_soal', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_bank_soal', 'kategori_id')) {
                $table->dropConstrainedForeignId('kategori_id');
            }
            foreach (['pembahasan', 'gambar_path', 'tingkat_kesulitan', 'tipe_soal'] as $column) {
                if (Schema::hasColumn('siakad_bank_soal', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('siakad_bank_soal_kategori');
    }
};
