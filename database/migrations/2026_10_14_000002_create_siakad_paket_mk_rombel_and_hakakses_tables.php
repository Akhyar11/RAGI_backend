<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paket MK per rombel per periode: BAAK/Kaprodi menetapkan daftar MK yang
        // disediakan untuk satu rombel (cth. 24A) sehingga KRS bisa diambil 1-klik.
        if (!Schema::hasTable('siakad_paket_mk_rombel')) {
            Schema::create('siakad_paket_mk_rombel', function (Blueprint $table) {
                $table->id();
                $table->foreignId('master_kelas_id')->constrained('siakad_master_kelas')->cascadeOnDelete();
                $table->foreignId('mata_kuliah_id')->constrained('siakad_mata_kuliah')->cascadeOnDelete();
                $table->foreignId('tahun_akademik_id')->nullable()->constrained('siakad_tahun_akademik')->cascadeOnDelete();
                $table->integer('semester_anjuran')->nullable();
                $table->boolean('is_wajib')->default(true);
                $table->timestamps();

                $table->unique(['master_kelas_id', 'mata_kuliah_id', 'tahun_akademik_id'], 'siakad_paket_mk_unique');
                $table->index(['master_kelas_id', 'tahun_akademik_id'], 'siakad_paket_mk_rombel_ta_idx');
            });
        }

        // Gate akses KRS per mahasiswa per periode (pengganti wsia_hakakses legacy).
        // Tanpa baris = terbuka (kompatibel mundur); baris is_dibuka=false = dikunci BAAK.
        if (!Schema::hasTable('siakad_hakakses_krs')) {
            Schema::create('siakad_hakakses_krs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->foreignId('tahun_akademik_id')->constrained('siakad_tahun_akademik')->cascadeOnDelete();
                $table->boolean('is_dibuka')->default(true);
                $table->timestamp('dibuka_at')->nullable();
                $table->timestamps();

                $table->unique(['mahasiswa_id', 'tahun_akademik_id'], 'siakad_hakakses_krs_unique');
                $table->index(['tahun_akademik_id', 'is_dibuka'], 'siakad_hakakses_krs_ta_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_hakakses_krs');
        Schema::dropIfExists('siakad_paket_mk_rombel');
    }
};
