<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan bimbingan per mahasiswa (masalah KRS/KHS/keuangan/pribadi + penanganan khusus)
        Schema::create('siakad_pa_catatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosen_id')->constrained('siakad_dosen')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->nullable()->constrained('siakad_tahun_akademik')->nullOnDelete();
            $table->string('kategori', 30)->default('akademik');
            $table->text('isi');
            $table->boolean('butuh_penanganan_khusus')->default(false);
            $table->string('status_tindak_lanjut', 30)->default('dipantau');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
        });

        // Laporan aktivitas PA per dosen per periode (kesimpulan + rekomendasi)
        Schema::create('siakad_pa_laporan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosen_id')->constrained('siakad_dosen')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('siakad_tahun_akademik')->cascadeOnDelete();
            $table->text('kesimpulan')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique(['dosen_id', 'tahun_akademik_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_pa_laporan');
        Schema::dropIfExists('siakad_pa_catatan');
    }
};
