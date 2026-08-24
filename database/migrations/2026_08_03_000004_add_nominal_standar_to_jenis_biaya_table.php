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
        if (Schema::hasTable('sikeu_master_biaya') && !Schema::hasColumn('sikeu_master_biaya', 'nominal_standar')) {
            Schema::table('sikeu_master_biaya', function (Blueprint $table) {
                $table->decimal('nominal_standar', 15, 2)->default(0)->after('is_recurring')->comment('Nominal dasar tagihan jika tidak ada tarif spesifik');
            });
        }



        if (!Schema::hasTable('core_master_jalur_kelas')) {
            Schema::create('core_master_jalur_kelas', function (Blueprint $table) {
                $table->id();
                $table->string('kode')->unique();
                $table->string('nama_jalur');
                $table->text('deskripsi')->nullable();
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
        // 1. Rollback tabel mahasiswa_beasiswa
        if (Schema::hasTable('sikeu_mahasiswa_beasiswa')) {
            Schema::table('sikeu_mahasiswa_beasiswa', function (Blueprint $table) {
                if (Schema::hasColumn('sikeu_mahasiswa_beasiswa', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('sikeu_mahasiswa_beasiswa', 'file_sk_beasiswa')) {
                    $table->dropColumn('file_sk_beasiswa');
                }
                if (Schema::hasColumn('sikeu_mahasiswa_beasiswa', 'catatan')) {
                    $table->dropColumn('catatan');
                }
            });
        }

        // 2. Rollback tabel beasiswa
        if (Schema::hasTable('sikeu_beasiswa')) {
            Schema::table('sikeu_beasiswa', function (Blueprint $table) {
                if (Schema::hasColumn('sikeu_beasiswa', 'tipe_potongan')) {
                    $table->dropColumn('tipe_potongan');
                }
                if (Schema::hasColumn('sikeu_beasiswa', 'nilai_potongan')) {
                    $table->dropColumn('nilai_potongan');
                }
            });
        }

        // 3. Rollback tabel jenis_biaya
        if (Schema::hasTable('sikeu_master_biaya') && Schema::hasColumn('sikeu_master_biaya', 'nominal_standar')) {
            Schema::table('sikeu_master_biaya', function (Blueprint $table) {
                $table->dropColumn('nominal_standar');
            });
        }



        Schema::dropIfExists('core_master_jalur_kelas');
    }
};
