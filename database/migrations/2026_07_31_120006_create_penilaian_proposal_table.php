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
        Schema::create('penilaian_proposal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_kegiatan_id')->constrained('reviewer_kegiatan')->cascadeOnDelete();
            $table->decimal('skor_rekam_jejak', 5, 2)->default(0.00);
            $table->decimal('skor_substansi', 5, 2)->default(0.00);
            $table->decimal('skor_rencana_anggaran', 5, 2)->default(0.00);
            $table->decimal('skor_total', 5, 2)->default(0.00);
            $table->enum('rekomendasi', ['diterima', 'revisi', 'ditolak']);
            $table->text('catatan_revisi')->nullable();
            $table->string('file_penilaian', 255)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penilaian_proposal');
    }
};
