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
        // 1. Tambah kolom tunjangan_nominal ke simpeg_jabatan_fungsional_akademik
        if (Schema::hasTable('simpeg_jabatan_fungsional_akademik')) {
            Schema::table('simpeg_jabatan_fungsional_akademik', function (Blueprint $table) {
                if (!Schema::hasColumn('simpeg_jabatan_fungsional_akademik', 'tunjangan_nominal')) {
                    $table->decimal('tunjangan_nominal', 15, 2)->default(0)->after('golongan');
                }
            });
        }

        // 2. Tabel Skala Gaji Pokok berbasis Golongan & Rentang Masa Kerja
        if (!Schema::hasTable('simpeg_master_skala_gaji_pokok')) {
            Schema::create('simpeg_master_skala_gaji_pokok', function (Blueprint $table) {
                $table->id();
                $table->string('nama_skala'); // e.g. "Golongan III/a (Penata Muda)", "Staf Umum"
                $table->string('golongan')->nullable(); // e.g. "III/a", "III/b", "IV/a"
                $table->integer('masa_kerja_min_tahun')->default(0);
                $table->integer('masa_kerja_max_tahun')->default(99);
                $table->decimal('nominal_gaji', 15, 2)->default(0);
                $table->text('keterangan')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['golongan', 'masa_kerja_min_tahun', 'masa_kerja_max_tahun'], 'idx_skala_gaji_lookup');
            });
        }

        // 3. Tabel Bracket PPh 21 Dinamis (Tarif Efektif Rata-Rata Bulanan / TER)
        if (!Schema::hasTable('simpeg_master_bracket_pph21')) {
            Schema::create('simpeg_master_bracket_pph21', function (Blueprint $table) {
                $table->id();
                $table->string('kategori')->default('DEFAULT'); // e.g. "TER_A", "DEFAULT"
                $table->decimal('penghasilan_bruto_min', 15, 2)->default(0);
                $table->decimal('penghasilan_bruto_max', 15, 2)->nullable(); // null = unlimited
                $table->decimal('tarif_persen', 6, 4)->default(0); // 0.0150 = 1.5%
                $table->text('keterangan')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['kategori', 'penghasilan_bruto_min'], 'idx_pph21_bracket');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_master_bracket_pph21');
        Schema::dropIfExists('simpeg_master_skala_gaji_pokok');

        if (Schema::hasTable('simpeg_jabatan_fungsional_akademik')) {
            Schema::table('simpeg_jabatan_fungsional_akademik', function (Blueprint $table) {
                if (Schema::hasColumn('simpeg_jabatan_fungsional_akademik', 'tunjangan_nominal')) {
                    $table->dropColumn('tunjangan_nominal');
                }
            });
        }
    }
};
