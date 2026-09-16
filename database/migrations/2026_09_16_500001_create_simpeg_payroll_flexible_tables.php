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
        // 1. Master Komponen Gaji (Pendapatan & Potongan Dinamis)
        if (!Schema::hasTable('simpeg_master_komponen_gaji')) {
            Schema::create('simpeg_master_komponen_gaji', function (Blueprint $table) {
                $table->id();
                $table->string('kode')->unique(); // e.g. GAJI_POKOK, TUNJ_FUNGSIONAL, HONOR_SKS, POT_BPJS_KES, POT_PPH21
                $table->string('nama');
                $table->enum('jenis', ['pendapatan', 'potongan'])->default('pendapatan');
                $table->enum('tipe_nilai', ['tetap', 'rumus_sks', 'rumus_kehadiran', 'rumus_pph21', 'persentase'])->default('tetap');
                $table->decimal('nilai_default', 15, 2)->default(0);
                $table->boolean('is_taxable')->default(true);
                $table->boolean('is_active')->default(true);
                $table->integer('urutan')->default(1);
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        // 2. Pemetaan Komponen Gaji per Pegawai
        if (!Schema::hasTable('simpeg_pegawai_komponen_gaji')) {
            Schema::create('simpeg_pegawai_komponen_gaji', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
                $table->foreignId('komponen_gaji_id')->constrained('simpeg_master_komponen_gaji')->onDelete('cascade');
                $table->decimal('nominal_kustom', 15, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('catatan')->nullable();
                $table->timestamps();

                $table->unique(['pegawai_id', 'komponen_gaji_id'], 'pegawai_komponen_unique');
            });
        }

        // 3. Ekstensi Kolom Tabel simpeg_gaji_pegawai
        if (Schema::hasTable('simpeg_gaji_pegawai')) {
            Schema::table('simpeg_gaji_pegawai', function (Blueprint $table) {
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'total_honor_sks')) {
                    $table->decimal('total_honor_sks', 15, 2)->default(0)->after('total_biaya_transport');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'total_sks_diampu')) {
                    $table->decimal('total_sks_diampu', 5, 2)->default(0)->after('total_honor_sks');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'total_tunjangan_fungsional')) {
                    $table->decimal('total_tunjangan_fungsional', 15, 2)->default(0)->after('total_sks_diampu');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'total_pph21')) {
                    $table->decimal('total_pph21', 15, 2)->default(0)->after('total_potongan');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'total_bpjs')) {
                    $table->decimal('total_bpjs', 15, 2)->default(0)->after('total_pph21');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'jurnal_id')) {
                    $table->foreignId('jurnal_id')->nullable()->after('status_transfer')->constrained('sikeu_jurnal_umum')->onDelete('set null');
                }
                if (!Schema::hasColumn('simpeg_gaji_pegawai', 'pengeluaran_kampus_id')) {
                    $table->foreignId('pengeluaran_kampus_id')->nullable()->after('jurnal_id')->constrained('sikeu_pengeluaran_kampus')->onDelete('set null');
                }
            });
        }

        // 4. Tabel Detail Rincian Komponen per Slip Gaji
        if (!Schema::hasTable('simpeg_gaji_detail')) {
            Schema::create('simpeg_gaji_detail', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gaji_pegawai_id')->constrained('simpeg_gaji_pegawai')->onDelete('cascade');
                $table->foreignId('komponen_gaji_id')->nullable()->constrained('simpeg_master_komponen_gaji')->onDelete('set null');
                $table->string('nama_komponen');
                $table->enum('jenis', ['pendapatan', 'potongan'])->default('pendapatan');
                $table->decimal('nominal', 15, 2)->default(0);
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index(['gaji_pegawai_id', 'jenis']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_gaji_detail');

        if (Schema::hasTable('simpeg_gaji_pegawai')) {
            Schema::table('simpeg_gaji_pegawai', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'pengeluaran_kampus_id')) {
                    $table->dropForeign(['pengeluaran_kampus_id']);
                    $cols[] = 'pengeluaran_kampus_id';
                }
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'jurnal_id')) {
                    $table->dropForeign(['jurnal_id']);
                    $cols[] = 'jurnal_id';
                }
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'total_honor_sks')) $cols[] = 'total_honor_sks';
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'total_sks_diampu')) $cols[] = 'total_sks_diampu';
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'total_tunjangan_fungsional')) $cols[] = 'total_tunjangan_fungsional';
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'total_pph21')) $cols[] = 'total_pph21';
                if (Schema::hasColumn('simpeg_gaji_pegawai', 'total_bpjs')) $cols[] = 'total_bpjs';

                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        Schema::dropIfExists('simpeg_pegawai_komponen_gaji');
        Schema::dropIfExists('simpeg_master_komponen_gaji');
    }
};
