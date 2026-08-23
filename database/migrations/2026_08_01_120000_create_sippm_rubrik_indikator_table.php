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
        Schema::create('sippm_rubrik_indikator', function (Blueprint $table) {
            $table->id();
            $table->enum('tipe_reviewer', ['kaprodi', 'admin']); // kaprodi (Tahap 1), admin (Tahap 2)
            $table->string('nama_indikator', 255);
            $table->text('deskripsi')->nullable();
            $table->decimal('bobot', 5, 2)->default(25.00); // Bobot dalam %
            $table->decimal('skor_minimal_default', 5, 2)->default(80.00); // Pass score default
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sippm_penilaian_rubrik_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('sippm_proposal_kegiatan')->cascadeOnDelete();
            $table->foreignId('rubrik_id')->constrained('sippm_rubrik_indikator')->cascadeOnDelete();
            $table->enum('tipe_reviewer', ['kaprodi', 'admin']);
            $table->foreignId('reviewer_pegawai_id')->nullable()->constrained('simpeg_pegawai')->nullOnDelete();
            $table->decimal('skor', 5, 2)->default(0.00); // Skor 0 - 100
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sippm_penilaian_rubrik_detail');
        Schema::dropIfExists('sippm_rubrik_indikator');
    }
};
