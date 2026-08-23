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
        Schema::create('sippm_reviewer_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('sippm_proposal_kegiatan')->cascadeOnDelete();
            $table->foreignId('reviewer_pegawai_id')->constrained('simpeg_pegawai')->cascadeOnDelete();
            $table->date('tgl_penugasan');
            $table->enum('status_review', ['pending', 'proses', 'selesai'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sippm_reviewer_kegiatan');
    }
};
